<?php

namespace HedgeBot\Core\Data;

use HedgeBot\Core\HedgeBot;
use Predis\Client as RedisClient;

/**
 * Class RedisProvider.
 * Provides a storage access for bot data via a Redis server.
 * 
 * @package HedgeBot\Core\Data
 */
class RedisProvider extends Provider
{
    const STORAGE_NAME = "redis";
    const STORAGE_PARAMETERS = ["url", "host", "port", "database", "scheme"];

    /** @var RedisClient The redis client */
    protected $client;

    /**
     * @inheritdoc
     * 
     * On the redis provider, the data that is saved in a key is specifically scalar (as redis doesn't allow to store
     * and access multi-level nested data easily). Then, the recursive data is split into one redis key for each path
     * and stored that way. That means getting a recursive complex data structure will end up in getting all the keys
     * that begin with the asked key on Redis.
     */
    public function get($key = null)
    {
        // Checking if the key itself exists (then it's a scalar value)
        if($this->client->exists($key)) {
            return $this->client->get($key);
        }

        // Generate the search terms from the key or an universal key if nothing is given
        $search = "*";
        
        if(!empty($key)) {
            $search = $key. "\\.*";
        }

        // Check if there is a key that starts with the key we're looking for
        $patternMatches = $this->client->keys($search);
        
        // If there is, we will iterate over all the matches and build the data array piece by piece
        if(!empty($patternMatches)) {
            $data = [];

            foreach($patternMatches as $match) {
                $value = $this->client->get($match);
                
                // Remove the base path from the match, including the trailing dot
                if(!empty($key)) {
                    $match = substr($match, strlen($key) + 1);
                }

                $matchParts = explode('.', $match);
                $arrayPart = &$data;

                // Build the path in the array for that match
                foreach($matchParts as $part) {
                    if(!isset($arrayPart[$part])) {
                        $arrayPart[$part] = [];
                    }

                    // Going through the path
                    $arrayPart = &$arrayPart[$part];
                }

                // Save the value in the generated path
                $arrayPart = $value;
            }

            return $data;
        }
        
        return null;
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value)
    {
        // Saving a scalar value, we store it directly without thinking much about it
        if(is_scalar($value)) {
            return $this->client->set($key, $value);
        } elseif(is_array($value) || is_object($value)) { // If it's an array, we flatten it and then store each key
            // Flattening the array in a list of keys
            $flattened = $this->flattenArray($value, $key);

            foreach($flattened as $key => $value) {
                $this->client->set($key, $value);
            }

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function remove($key = null)
    {
        $search = "*";

        if(!empty($key)) {
            $search = $key. "\\.*";
        }

        // Match descendants to this key
        $patternMatches = $this->client->keys($search);

        if(!empty($patternMatches)) {
            foreach($patternMatches as $match) {
                $this->client->del($match);
            }
        }

        $this->client->del($key);
        
        return true;
    }

    /**
     * @inheritdoc
     * @see https://github.com/nrk/predis/wiki/Connection-Parameters
     */
    public function connect($parameters)
    {
        $connectParameters = null;
        
        // If there is an URL in the parameters, we use that as only parameter
        if(!empty($parameters->url)) {
            $client = new RedisClient($parameters->url);
        } else { // Else, there should be parameters given as an object, we then give them to the client straight
            // Set the default port if not specified
            if(empty($parameters->port)) {
                $parameters->port = 6379;
            }

            $client = new RedisClient((array) $parameters);
        }
        
        $client->connect();
        
        if(!$client->isConnected()) {
            return false;
        }

        $this->client = $client;
        return true;
    }

    /**
     * Flattens a recursive array, into a flat array with path as keys.
     * 
     * @param array $array The array to flatten.
     * @param string $base The base path to use in keys.
     * 
     * @return array The resulting flat array.
     */
    protected function flattenArray($array, $base = null)
    {
        $output = [];

        foreach($array as $key => $value) {
            if(is_array($value) || is_object($value)) {
                $subArray = $this->flattenArray($value, $base. ".". $key);

                foreach($subArray as $subkey => $subvalue) {
                    $output[$subkey] = $subvalue;
                }
            } elseif(is_scalar($value)) {
                $output[$base. ".". $key] = $value;
            }
        }

        return $output;
    }
}