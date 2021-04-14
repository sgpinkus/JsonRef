#!/usr/bin/env php
<?php
require_once './vendor/autoload.php';
use JsonDoc\JsonDocs;
use JsonDoc\JsonLoader;
use JsonDoc\Uri;

$jsonDocs = new JsonDocs(new JsonLoader());
$myUri = 'file://' . realpath($argv[1]);

$doc = $jsonDocs->loadUri($myUri);
var_dump($doc);
