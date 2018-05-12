<?php

namespace HedgeBot\Core\Data;

use HedgeBot\Core\HedgeBot;

/**
 * JSON File provider. Provides a way to store data in an unique JSON file.
 */
class JsonFileProvider extends Provider
{
    private $backups; // Wether to do backups or not
    private $dataFile; // Data file location
    private $lastModification; // Data file last modification timestamp

    private $data; // Data storage

    const STORAGE_NAME = "json";

    /**
     * Loads the data from the JSON file into the memory.
     */
    public function loadData()
    {
        $fileContent = file_get_contents($this->dataFile);
        $this->data = json_decode($fileContent, true);
        $this->lastModification = filemtime($this->dataFile);

        return true;
    }

    /**
     * Writes the in-memory data to the JSON file.
     */
    public function writeData()
    {
        // Filter function to remove empty arrays/objects from the generated JSON
        $filterFunction = function ($element) {
            return !(is_array($element) && empty($element));
        };

        $cleanData = $this->arrayFilterRecursive($this->data, $filterFunction);

        $json = json_encode($cleanData, JSON_PRETTY_PRINT);
        file_put_contents($this->dataFile, $json);

        // Updating the last modification time to avoid instant reload, and forcing stat cache reload to avoid getting bad times
        clearstatcache(true, $this->dataFile);
        $this->lastModification = filemtime($this->dataFile);

        HedgeBot::message("Saved data to file.", [], E_DEBUG);
    }

    /**
     * Loads data from the file store in the specified file.
     *
     * @param $parameters
     * @return bool|True
     */
    public function connect($parameters)
    {
        $this->backups = false;

        // Reset data
        $this->data = [];

        // If we're given an object configuration (from the boostrapping storage), load the location from it
        $location = null;
        if (is_object($parameters)) {
            $location = $parameters->path;

            if (isset($parameters->backups) && HedgeBot::parseBool($parameters->backups)) {
                $this->backups = true;
            }
        } else {
            $location = $parameters;
        }

        HedgeBot::message('Connecting to JSON file storage at file "$0"', array($location), E_DEBUG);

        if (is_dir($location)) {
            return HedgeBot::message("The specified data path is a directory. A file is needed.", null, E_WARNING);
        }

        $this->dataFile = $location;

        // Check if file exists before loading data
        if (!file_exists($location)) {
            HedgeBot::message("There is no file at the specified data path. It will be created.", null, E_NOTICE);
            file_put_contents($this->dataFile, json_encode([], JSON_FORCE_OBJECT));
        } else {
            $this->loadData();
        }

        return true;
    }


    /**
     * Gets a variable, scalar or complex, from the storage.
     *
     * @param $key The key corresponding to the data to get.
     *
     * @return The requested data or NULL on failure.
     */
    public function get($key)
    {
        $keyComponents = explode('.', $key);

        $currentPath = &$this->data;
        foreach ($keyComponents as $component) {
            if (!isset($currentPath[$component])) {
                return null;
            } elseif (is_array($currentPath)) {
                $currentPath = &$currentPath[$component];
            } else {
                return false;
            }
        }

        return $currentPath;
    }

    /** Sets a variable in the data storage.
     * Sets a var in the data storage, and saves instantly all the data.
     * TODO: Save only the relevant part ?
     *
     * @param $key The key under which to save the data.
     * @param $data The value to save. Could be a complex structure like an array.
     *
     * @return TRUE if the data has been saved, FALSE otherwise.
     */
    public function set($key, $data)
    {
        $keyComponents = explode('.', $key);

        $varName = array_pop($keyComponents);

        $currentPath = &$this->data;
        foreach ($keyComponents as $component) {
            if (!isset($currentPath[$component])) {
                $currentPath[$component] = array();
            }

            if (is_array($currentPath[$component])) {
                $currentPath = &$currentPath[$component];
            } else {
                return false;
            }
        }

        $pathWasEmpty = empty($currentPath[$varName]);
        $currentPath[$varName] = $data;
    
        // Write the data only if the data write would result in an actual write
        if(!($pathWasEmpty && is_array($data) && empty($data)))
            $this->writeData();
        
        return true;
    }

    /**
     * Sets wether to do backups of the data when updating or not.
     *
     * @param bool $backups TRUE to do backups, FALSE to not.
     */
    public function setBackups($backups)
    {
        $this->backups = $backups;
    }

    /**
     * Backs up the data file to a backup file.
     */
    private function backupData()
    {
        $backupFile = $this->dataFile . '.backup';
        $fileContent = file_get_contents($this->dataFile);
        file_put_contents($backupFile, $fileContent);
    }

    /**
     * Checks if there is an update to the data file, and reload it if necessary.
     */
    public function checkUpdate()
    {
        $lastModification = filemtime($this->dataFile);
        if ($lastModification > $this->lastModification) {
            $this->loadData();
            return true;
        }

        return false;
    }

    /**
     * Recursively filter an array
     *
     * @param array $array
     * @param callable $callback
     *
     * @return array
     */
    public function arrayFilterRecursive(array $array, $callback = null)
    {
        $array = is_callable($callback) ? array_filter($array, $callback) : array_filter($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = call_user_func([$this, 'arrayFilterRecursive'], $value, $callback);
            }
        }

        return $array;
    }
}
