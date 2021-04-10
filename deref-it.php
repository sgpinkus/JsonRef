<?php
require_once './vendor/autoload.php';
use JsonDoc\JsonDocs;
use JsonDoc\JsonLoader;
use JsonDoc\Uri;

$jsonDocs = new JsonDocs(new JsonLoader());
$myUri = 'file://' . realpath($argv[1]);

$doc = $jsonDocs->loadUri($myUri);
var_dump($doc);
$doc2 = $jsonDocs->loadUri($myUri);
var_dump($doc === $doc2); // true

$doc = $jsonDocs->loadDoc(file_get_contents($argv[1]), 'file:///tmp/some/fake/unique/path');
var_dump($doc);

$doc = $jsonDocs->loadDoc(json_decode(file_get_contents($argv[1])), 'file:///tmp/some/fake/unique/path2');
var_dump($doc);
