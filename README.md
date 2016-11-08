# Overview
This library provides a PHP wrapper over the plain old data structure returned by `json_decode()` in order to implement [Json Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03) and by extension [Json Pointer](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04) (Json Reference requires Json Pointer). This library simply replaces JSON References in a loaded document with native PHP references to other documents or to parts of the same document. It supports doing this on an existing decoded JSON document data structure, or may load and decode the document from a URI.

  * Almost full support for `$refs`. See below.
  * Support for `id` keyword, following [this amendment](https://github.com/json-schema/json-schema/wiki/The-%22id%22-conundrum#how-to-fix-that) to the ambiguous spec. Basically:
    - `id` at root identifies the document. A root `id` may be absolute or relative. If it's relative how it's resolved to an absolute URI is undefined.
    - `id` at non root identifies the given object in a document. Document `$refs` can ref it. Non root `id`s must be a non empty fragment URI, and unique within the document. Just like a HTML anchor.
    - `id` DOES NOT establish a new base URI for relative URI resolution (That is useless rubbush and I refuse to implement it).

# Usage

```php
<?php
require_once './vendor/autoload.php';
use JsonDoc\JsonDocs;
use JsonDoc\JsonLoader;
use JsonDoc\Uri;
$jsonDocs = new JsonDocs(new JsonLoader());
$myUri = new Uri('file://' . realpath('./tests/test-data/basic-refs.json'));

$doc = $jsonDocs->loadUri($myUri);
var_dump($doc);
$doc2 = $jsonDocs->loadUri($myUri);
var_dump($doc === $doc2); // true

// Or if the doc is already a string.
$strDoc = file_get_contents(realpath('./tests/test-data/basic-refs.json'));
$doc = $jsonDocs->loadDocStr($strDoc, new Uri('file:///tmp/some/fake/unique/path'));
var_dump($doc);

// Or if the doc is already decoded.
$objDoc = json_decode($strDoc);
$doc = $jsonDocs->loadDocObj($objDoc, new Uri('file:///tmp/some/fake/unique/path2'));
var_dump($doc);
```

# Classes & Design
`JsonDoc\JsonDocs` is the operative class of this library. Its acts as a cache of documents keyed by their unique URIs.

A JSON reference can reference parts of the document it exists in, or it can reference external JSON documents. External documents need to be loaded. For this reason, in order to keep the dereferencing procedure atomic, loading of resources needs to be done atomically in the "dereference" operation as we come across references to external resources.

Externally loaded documents need to stored somewhere. `JsonDocs` maintains an internal cache keyed by URI of loaded resources. Beware that the you can end up with references to this internal cache because JSON References are simply replaced by PHP references to the appropriate part of a document in the cache. But it doesn't really matter because it's all hidden under the `JsonDocs` interface.

`JsonDocs` requires a `JsonDoc\JsonLoader` to turn URIs into JSON document strings. Two loader are implemented in this library. `JsonLoader` loads from local file system only, and `JsonNullLoader` throws if you try to load from it (use this is you want to handle loading externally and you don't want the loader to every be invoked.

`JsonDoc\Uri` represents a URI because PHP doesn't have a good URI class (AFAIK)! It's fairly simple.

# On JSON Reference
According to [Json Reference](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03), JSON references must be [Json Pointers](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04). However there is another commonly implemented type of reference; A reference to an object that has an `id` field. JSON Schema, requires such pointers. The semantics of pointers to `id` labeled JSON is defined on the [json-schema.org Wiki](https://github.com/json-schema/json-schema/wiki/The-%22id%22-conundrum#how-to-fix-that). Example:

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

# On Parsing JSON References
Three main issues with JSON References containing JSON Pointers (see https://github.com/json-schema/json-schema/wiki/$ref-traps):

  1. Pointers to pointers.
  2. Pointers to pointers that cause a loop.
  3. Pointers that point *through* other pointers.

Currently this library addresses the the first two problems by **not allowing pointers to pointers at all**. XSD got by without such a complication, and in fact without any pointer like reference concept at all.

Note however, the references are resolved in a predictable order so *if* the referee is resolved before the referer, the reference works because the referee has already been literally replaced by PHP reference. As for the 3rd issue, if a pointer is encountered in a path while resolving a ref an exception is thrown. But resolution order is important here too so a pointer through a pointer may work, but is not guaranteed to work. That is, its a quirk that should not be relied on. For reference, references are resolved in order of depth they exist at in the document, with shallowest first, and alphabetical if depth equal. Also note that none of this implies it is not possible to implement recursive data structures using refs in this implementation - it is.
