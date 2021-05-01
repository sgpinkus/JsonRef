# PHP JSONREF
This library implements [JSON Reference v0.4.0](https://github.com/sgpinkus/jsonref.org) and by extension [JSON Pointer](https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-04) (JSON Reference requires JSON Pointer) for PHP. JSON Reference v0.4.0 succeeds [JSON Reference v0.3.0](https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03) and is *not* entirely backwards compatible.

This library replaces JSON references in a JSON document with native PHP references to parts of the same decoded JSON document, or parts of some other decoded JSON document referred to by URI. It supports doing this on an existing decoded JSON document data structures, or loading and decoding the JSON document from a URL.

*NOTE: Pre-encode, normalization of objects is not yet supported.*

# INSTALLATION

```
composer install
```

# TESTS

```
composer test
```

# SYNOPSIS
The following show various ways of loading and dereferencing a JSON document:

```
<?php
require_once './vendor/autoload.php';
use JsonRef\JsonDocs;
use JsonRef\JsonLoader;

// Loader is optional. The default loader will throw an exception if any non local refs are encountered.
// strictIds option forces `$id` values to be valid anchor names. But many documents use arbitrary strings.
$strictIds = false;
$jsonDocs = new JsonDocs(new JsonLoader(), $strictIds);
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
