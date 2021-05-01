#!/usr/bin/env php
<?php
require_once dirname(__file__) . '/../vendor/autoload.php';
use JsonRef\JsonDocs;
use JsonRef\JsonLoader;
use JsonRef\Uri;

if($argc != 3) {
  echo "Usage: ${argv[0]} <schema-filename> <doc-filename>\n";
  exit(1);
}

$doc = json_decode(file_get_contents($argv[1]));
print(json_encode(JsonDocs::getPointer($doc, $argv[2])) . "\n");
