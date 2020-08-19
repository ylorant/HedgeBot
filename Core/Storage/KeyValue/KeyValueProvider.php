<?php

namespace HedgeBot\Core\Storage\KeyValue;

use ReflectionClass;

/**
 * Class Provider
 * @package HedgeBot\Core\Storage\KeyValue
 */
abstract class KeyValueProvider
{
    public $readonly = false; ///< Boolean, prevents writing to the data storage.
    protected static $classCache = []; ///< Storage class cache

    const STORAGE_NAME = "";
    const STORAGE_PARAMETERS = ["readonly"];

    /**
     * Loads a key from the storage.
     * The implemented method will allow to load data from the storage, in a transparent manner.
     * The user only has to provide the key to retrieve,
     * and the storage should be able to retrieve the data and all its children.
     *
     * @param $key The key to retrieve.
     *
     * @return mixed A mixed value containing the data.
     */
    abstract public function get($key = null);

    /**
     * Saves a specific key into the storage.
     * The implemented version of this prototype must allow to store
     * a single key or a complex key in a completely transparent manner for the user.
     *
     * @param $key The key name to save the data as. String is preferred.
     * @param $data The data to save. Could be a scalar value like a complex value.
     *
     * @return bool True if the save succeeded, False otherwise.
     */
    abstract public function set($key, $data);

    /**
     * Removes a specific key from the storage.
     * The implemented version of this prototype must allow to remove a single key, or to remove a tree
     * of nested complex data just by removing it's base branch.
     * 
     * @param $key The key of the data to remove. String is preferred.
     * 
     * @return bool True if the data has been deleted successfully, false otherwise.
     */
    abstract public function remove($key = null);

    /**
     * Connects to the data source.
     * The method implementing this prototype will be used to connect to the data source.
     *
     * @param $parameters The data source parameters to connect with. Content can vary between data providers.
     * @return bool True if the connection to the source succeeded, False otherwise.
     */
    abstract public function connect($parameters);

    /**
     * Resolves a storage from its name and returns its class name.
     *
     * @param $name The storage name.
     * @return bool|string The storage class if found, false if not.
     * 
     * @throws \ReflectionException
     */
    public static function resolveStorage($name)
    {
        $storageClasses = self::getStorageClasses();

        if(!empty($storageClasses[$name])) {
            return $storageClasses[$name];
        }

        return false;
    }

    /**
     * Gets the list of available storages.
     * 
     */
    public static function getStorageList()
    {
        $storageClasses = self::getStorageClasses();
        return array_keys($storageClasses);
    }

    /**
     * Gets the available storage providers' classes names.
     * 
     * @return array The available storages' classes names.
     */
    protected static function getStorageClasses()
    {
        // Refresh the storage classes cache if needed
        if(empty(self::$classCache)) {
            // Getting the current namespace to be able to load classes correctly
            $reflectionClass = new ReflectionClass(self::class);
            $currentNamespace = $reflectionClass->getNamespaceName();

            // Classes to be ignored
            $ignoreClasses = array(
                'ObjectAccess',
                'KeyValueProvider'
            );

            // Scanning the directory of this file, which contains the other classes.
            $currentDir = scandir(__DIR__);
            foreach ($currentDir as $file) {
                if (!is_file(__DIR__ . '/' . $file)) {
                    continue;
                }

                $className = str_replace('.php', '', $file);
                if (in_array($className, $ignoreClasses)) {
                    continue;
                }

                $className = $currentNamespace . "\\" . $className;
                $storageName = $className::getName();
                self::$classCache[$storageName] = $className;
            }
        }

        return self::$classCache;
    }

    /**
     * @return string
     */
    public static function getName()
    {
        return "kv-" . static::STORAGE_NAME;
    }

    /**
     * Gets the defined storage parameters for the current storage provider.
     * 
     * @return array The defined storage parameters.
     */
    public static function getParameters()
    {
        return array_unique(array_merge(self::STORAGE_PARAMETERS, static::STORAGE_PARAMETERS));
    }
}