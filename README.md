# JSONDOC
This library implements [JSON Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03) and by extension [JSON Pointer](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04) (JSON Reference requires JSON Pointer) for PHP. This library replaces JSON References in a JSON document with native PHP references to parts of the same decoded JSON document, or parts of some other decoded JSON document referred to by URI. It supports doing this on an existing deserialized JSON document data structures, or loading and deserializing the JSON document from a URL.

*NOTE: Serialization of object is not yet supported but is a goal of this project.*

# SYNOPSIS
Following show various ways of loading and dereferencing a JSON document.

```
<?php
require_once './vendor/autoload.php';
use JSONDoc\JSONDocs;
use JSONDoc\JSONLoader;

// Loader is optional. The default loader will throw an exception if any non local refs are encountered.
// strictIds option forces `$id` values to be valid anchor names. But many documents use arbitrary strings.
$strictIds = false;
$jsonDocs = new JSONDocs(new JSONLoader(), $strictIds);
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

# JSON REFERENCE SPECIFICATION
Every valid JSON Reference document is a valid JSON document. By "JSON document" we mean a UTF8 encoded string of MIME type `application/json` not it's native language dependent *decoded document*. JSON Reference decodes JSON documents, in a special way, making use two special JSON object properties, meaningful to JSON Reference, but not meaningful to JSON: `$ref` and `$id`. They work like this:

**$id**

  - The `$id` property is optional on any object.
  - The `$id` property is analogous to the `name` or `id` HTML attribute. The `$id` keyword value MUST begin with a letter ([A-Za-z]) and may be followed by any number of letters, digits ([0-9]), hyphens ("-"), underscores ("_"), colons (":"), and periods (".")".
  - `$id` values are case sensitive.
  - The `$id` property values MUST be unique within a *JSON document*.
  - Implementations MUST raise an error if duplicate `$id` values are present.

**$ref**

  - Objects with a `$ref` property, such as `{ "$ref": <URI> }` are *entirely replaced* by the value pointed to by the `<URI>`. This value is called the *replacement-value*.
  - All other properties of an object containing a `$ref` key *are ignored*.
  - `<URI>` MUST be a valid URI. Implementations must attempt to resolve the `<URI>` to a value as described below. This value then replaces the object containing the `$ref` property entirely.
  - The fragment component of `<URI>` MUST be empty, a valid `$id` keyword value, or a valid [JSON pointer][json-pointer].
  - If `<URI>` is consists only of a fragment identifier part it MUST be resolved against the current document. The *replacement-value* is the object with the given  `$id` keyword or value pointed to via the JSON Pointer in the case that the fragment is a valid JSON Pointer (pointers a syntactically distinct from valid `$id` names so there is no ambiguity).
  - If `<URI>` is not just a fragment, implementations SHOULD resolve relative URIs against a base URI, then use the value identified by this URI as the *replacement-value*. How the base URI is determined is beyond the scope of this specification.
  - Implementations MUST NOT attempt to load remote resources or any external resource by default. In general implementations should make it clear how and where external values may be retrieved from and require the client to explicitly enable or configure such.
  - If a loaded resource refers to all or part of a JSON document, that JSON document MUST be processed as a standalone document as described above, and NOT considered part of, or substituted into, the referring source JSON document.

# WHAT JSON REFERENCE IS NOT
JSON Reference is not JSON Merge or JSON Patch and supporting such is beyond the scope of the specification. The focus of the specification was simply encoding objects with references to themselves.

JSON Reference is not JSON Schema draft v06+'s JSON Reference like de-referencing implementation, although it has been suggested as a practical replacement for it.

# JSON SCHEMA STYLE JSON REFERENCE CONFORMANCE
Like JSON Schema draft v06+ specifications, this implementation breaks with the original specification in using `$id` instead of `id` for identifiers. However, this implementation diverges with the JSON Schema style JSON Reference in some places.

  - `$id` does not establish the base URI of the document. Instead a base URI is provided by the client, either explicitly, or as the URI from which the resource was loaded.
  - The `$id` keyword does not change the base URI in anyway. By default, each distinct document has a base URI (as above), and any relative URI encountered is qualified against this *singular*, document wide base URI.
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
