# JSONDOC
This library implements [JSON Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03) and by extension [JSON Pointer](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04) (JSON Reference requires JSON Pointer) for PHP. This library replaces JSON References in a loaded document with native PHP references to parts of the same document, or parts of some other document referred to by URL. It supports doing this on an existing deserialized JSON document data structure, or loading and deserializing the document from a URL.

*NOTE: Serialization of object is not yet supported but is a goal of this project.*

# SYNOPSIS
Following show various ways of loading and dereferencing a JSON document. The result of dereferencing is a `JSONDocs` object.

```
<?php
require_once './vendor/autoload.php';
use JSONDoc\JSONDocs;
use JSONDoc\JSONLoader;

// Loader is optional. The default loader will throw an exception if any non local refs are encountered.
$jsonDocs = new JSONDocs(new JSONLoader());
$myUri = 'file://' . realpath('./tests/test-data/basic-refs.json');

$doc = $jsonDocs->loadUri($myUri);
var_dump($doc);
$doc2 = $jsonDocs->loadUri($myUri);
var_dump($doc === $doc2); // true

$strDoc = file_get_contents(realpath('./tests/test-data/basic-refs.json'));
$doc = $jsonDocs->loadDocStr($strDoc, 'file:///tmp/some/fake/unique/path');
var_dump($doc);

// Or if the doc is already decoded.
$objDoc = json_decode($strDoc);
$doc = $jsonDocs->loadDocObj($objDoc, 'file:///tmp/some/fake/unique/path2');
var_dump($doc);
```

# INSTALLATION

```
composer install
```

# TESTS

```
composer test
```

# JSON REFERENCE BASICS
There is two special JSON keys in JSON References: `$ref` and `$id`. `{ "$ref": <POINTER> }` is *replaced* by the value pointed to by the JSON pointer `<POINTER>`. `<POINTER>` can point to external documents. Whether they are loaded depends on the configuration, since automatically loading remote resources could be undesirable. All other properties of an object containing a `$ref` key **are ignored**. `{ $id: <ID> }` establishes an identifier for the object in which it occurs. `$id` may be used in a JSON Reference pointers to refer to the object from with **the same** document. `$id` must be unique within a given document.

# WHAT JSON REFERENCE IS NOT
JSON Reference is not JSON Merge or JSON Patch and supporting such is beyond the scope of the specification. The focus of the specification was simply encoding objects with references to themselves.

<!-- This has nothing to do with merge or patching JSON documents except the following: Once a JSON document has been dereferenced it may no longer be directly mappable to a JSON document. As such, any merging or patch based only on JSON semantics cannot be applied after JSON dereferencing. These things should happen before dereferencing if required. -->

JSON Reference is not JSON Schema draft v7+'s JSON Reference implementation  or JSON Schema's implementation of `$ref`.

# JSON SCHEMA STYLE JSON REFERENCE CONFORMANCE
Like JSON Schema draft v06+ specifications, this implementation breaks with the original specification in using '$id' instead of `id` for identifiers. However, this implementation diverges with the JSON Schema style JSON Reference in some places.

  - A root level `$id` does not establish the base URI of the document. Instead a base URI is provided by the client, either explicitly or as the URI from which the resource was loaded. Clients may observe the root level `$id` and pass that in as the base URI if they wish.
  - The `$id` keyword *DOES NOT* establish a new base URI. Instead each distinct document has a base URI, and any relative URI encountered is qualified against this *singular*, document wide base URI.
  - This implementation places no restriction on where a `$ref` can occur or what a `$ref` can refer to.

# NOTE ON JSON REFERENCE

## ON ID POINTERS
According to [JSON Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03), the JSON reference fragment part must be [JSON Pointers](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04). However, there is another commonly implemented type of reference: a reference to an object that has an `$id` field. JSON Schema, requires such pointers. The semantics of pointers to `$id` labeled JSON is defined on the [json-schema.org Wiki](https://github.com/json-schema/json-schema/wiki/The-%22id%22-conundrum#how-to-fix-that). Example:

    {
      "foo": "bah",
      "a": {
        "$id": "#foo",
      },
      "b": {
        "byid": { "$ref": "#foo" }
        "byref": { "$ref": "#/foo" }
      },
    }

Gives:

    ...
    "b": {
      "byid": { "$id: "#foo" }
      "byref": "bah"
    }

`$id` refs are strictly local.

## ON PARSING CIRCULAR JSON REFERENCES
The main issues with JSON References containing JSON Pointers are certain types of circular references (see https://github.com/json-schema/json-schema/wiki/$ref-traps):

### ON POINTERS TO POINTERS
In general circular references are allowed and useful for defining graph like structures, including recursive structures. However, consider:

    {
      "foo": { "$ref": "#/bah" },
      "bah": { "$ref": "#/foo" }
    }

This is make no sense because it is *vacuous*: the references are expected to refer to valid JSON, they are not JSON content in an of themselves, so in this case no valid JSON can ever be resolved, and it is illegal. On the other hand, all of the following are legal. In the last case, neither of the pointer points to another pointer so is also fine:

    {
      "foo": { "$ref": "#/bah" },
      "bah": { "$ref": "#/" }
    }

    {
      "foo": { "$ref": "#/" }
    }

    {
      "definitions": {
        "foo": {"properties": {"bar": {"$ref": "#/definitions/bar"}}},
        "bar": {"properties": {"foo": {"$ref": "#/definitions/foo"}}}
      },
      "type": "object",
      "properties": {"foo": {"$ref": "#/definitions/foo"}}
    }

In summary, pointers to pointers are legal, but pointers to pointers resulting in a *pure* pointer loop are illegal. Conceptually, if one can collapse any pointer to pointer chain into an eventual non pointer value it's legal. If not, that's illegal.

### ON POINTERS THAT POINT *THROUGH* OTHER POINTERS
Consider the following document:

    {
      "a": {
        "x": { "$ref": "#/b/c/x" },
      },
      "b": { "$ref": "#/c" },
      "c": {
        "x": "Hey you found me!"
      }
    }

`#/b/c/x` points *through* the pointer at `#/b`. For this to work, we must ensure `#/b` is resolved before `#/b/c/x`.

*NOTE: Currently this parse uses an algorithm based on a priority queue to resolve references. Priority of a ref depends on it's depth. Whether refs through refs works depends on the order in which references are pushed onto the stack. There are currently edges cases where refs through refs are not resolved successfully*
