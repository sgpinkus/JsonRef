#!/usr/bin/env php
<?php
require_once dirname(__file__) . '/../vendor/autoload.php';
use JsonRef\JsonDocs;
use JsonRef\JsonLoader;
use JsonRef\Uri;

$jsonDocs = new JsonDocs(new JsonLoader());
$myUri = 'file://' . realpath($argv[1]);

$doc = $jsonDocs->loadUri($myUri);
var_dump($doc);
