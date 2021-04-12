<?php
require_once './vendor/autoload.php';
use JsonDoc\JsonDocs;
use JsonDoc\JsonLoader;
use JsonDoc\Uri;

if($argc != 3) {
  echo "Usage: ${argv[0]} <schema-filename> <doc-filename>\n";
  exit(1);
}

$doc = json_decode(file_get_contents($argv[1]));
print(json_encode(JsonDocs::getPointer($doc, $argv[2])) . "\n");
