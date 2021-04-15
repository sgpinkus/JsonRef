<?php
namespace JsonDoc;

use JsonDoc\Uri;
use JsonDoc\JsonLoader;
use JsonDoc\Exception\ResourceNotFoundException;

/**
 * Simple JsonLoader wrapper over an array of preloaded schemas.
 * This provides a way to load "packed" schemas, packed according to this ~pseudo standard approach.
 */
class JsonArrayLoader extends JsonLoader
{
  private $schemaMap;

  /**
   * Construct by parsing $schemas array. entries can be either JSON document strings or 2-tuples: [$uri, $schema]
   * where $schema is a string or \StdObject instance.
   * @input $schemas array of schemas.
   */
  public function __construct(array $schemas = []) {
    foreach($schemas as $i => $schema) {
      $uri = null;
      if(is_array($schema)) {
        [$uri, $schema] = $schema;
      }
      if($uri) {
        $uri = new Uri($uri);
      }
      if(!$uri && is_string($schema)) {
        $schema = json_decode($schema);
        if($schema === null) {
          throw new JsonDecodeException(json_last_error());
        }
      }
      if(is_object($schema)) {
        $uri = new Uri($schema->{'$id'});
      }
      if(!$uri || !$uri->isAbsoluteUri()) {
        throw new \LogicException("Could not resolve URI to identify schema at index $i.");
      }
      if(!is_object($schema) && !is_string($schema)) {
        throw new \LogicException("Invalid schema for URI $uri.");
      }
      var_dump($uri, $this->schemaMap);
      if(isset($this->schemaMap[$uri.""])) {
        throw new \LogicException("Schema identified by '$uri' already exists.");
      }
      $this->schemaMap[$uri.""] = $schema;
    }
  }

  /**
   * Load raw data from $uri. The data returned should be a JSON doc decodable with json_decode().
   * @throws ResourceNotFoundException.
   */
  public function load($uri) {
    if(!isset($this->schemaMap[$uri.""])) {
      throw new ResourceNotFoundException("Could not load resource $uri.");
    }
    return $this->schemaMap[$uri.""];
  }
}
