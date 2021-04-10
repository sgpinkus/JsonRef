# JSONDOC
This library implements [JSON Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03) and by extension [JSON Pointer](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04) (JSON Reference requires JSON Pointer) for PHP. This library replaces JSON References in a loaded document with native PHP references to parts of the same document, or parts of some other document referred to by URL. It supports doing this on an existing deserialized JSON document data structure, or loading and deserializing the document from a URL.

  * Full support for `$refs`. See below.
  * Support for `id` keyword, following [this amendment](https://github.com/json-schema/json-schema/wiki/The-%22id%22-conundrum#how-to-fix-that) to the ambiguous spec. Basically:
    - `id` at root identifies the document. A root `id` may be an absolute or relative URI. If it's relative, how it is resolved to an absolute URI is undefined.
    - `id` *NOT* at the root of a document gives a *LOCAL* identifier for the given object. `id`s must be a non empty fragment URI, and unique within the document. It's just like HTML anchors.
    - `$refs` can refer to parts of the containing document by `id`.
    - `id` *DOES NOT* establish a new base URI for relative URI resolution (this is an interesting but ultimately useless and poorly thought out, unimplementable (?), part of the so called spec).

*Serialization is not yet supported but is a goal of this project.*

# USAGE
Following show various ways of loading and dereferencing a JSON document. The result of dereferencing is a `JSONDocs` object.

```
<?php
require_once './vendor/autoload.php';
use JSONDoc\JSONDocs;
use JSONDoc\JSONLoader;

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

# ON JSON REFERENCE

## ON ID POINTERS
According to [JSON Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03), the JSON reference fragment part must be [JSON Pointers](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04). However, there is another commonly implemented type of reference: a reference to an object that has an `id` field. JSON Schema, requires such pointers. The semantics of pointers to `id` labeled JSON is defined on the [json-schema.org Wiki](https://github.com/json-schema/json-schema/wiki/The-%22id%22-conundrum#how-to-fix-that). Example:

    {
      "foo": "bah",
      "a": {
        "id": "#foo",
      },
      "b": {
        "byid": { "$ref": "#foo" }
        "byref": { "$ref": "#/foo" }
      },
    }

Gives:

    ...
    "b": {
      "byid": { "id: "#foo" }
      "byref": "bah"
    }

`id` refs are strictly local.

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

*NOTE: Currently this library addresses pure pointer loops not allowing pointers to pointers at all (XSD got by without such a complication, and in fact without any pointer like reference concept at all). This strict rule prohibits any possibility of any pure pointer loop by nipping any chain that might loop in the bud. However it is unsatisfactory because it requires the referrer to have knowledge of the structure of the referred to documents to ensure any referred to object is not a pointer.*

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

`#/b/c/x` points *through* the pointer at `#/b`.

*NOTE: Currently this parse uses an algorithm based on a priority queue to resolve references. Priority of a ref depends on it's depth. Whether refs through refs works depends on the order in which references are pushed onto the stack. There may be a better algorithm for this. TBC ...*

## WHAT JSON REFERENCE IS NOT
JSON Ref is not JSON Merge or JSON Patch and supporting such is beyond the scope of this specification. The focus of this specification is on encoding objects with references to themselves. This has nothing to do with merge or patching JSON documents except the following: Once a JSON document has been dereferenced it is no longer directly mappable to a JSON document. As such, any merging or patch based only on JSON semantics cannot be applied after JSON dereferencing. These things should happen before dereferencing if required.
