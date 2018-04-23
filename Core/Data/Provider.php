<?php

namespace HedgeBot\Core\Data;

use ReflectionClass;

abstract class Provider
{
    public $readonly = false; ///< Boolean, prevents writing to the data storage.

    const STORAGE_NAME = "";

    /** Loads a key from the storage.
     * The implemented method will allow to load data from the storage, in a transparent manner. The user only has to provide
     * the key to retrieve, and the storage should be able to retrieve the data and all its children.
     *
     * \param $key The key to retrieve.
     *
     * \return A mixed value containing the data.
     */
    abstract public function get($key);

    /** Saves a specific key into the storage.
     * The implemented version of this prototype must allow to store a single key or a complex key in a completely transparent
     * manner for the user.
     *
     * \param $key The key name to save the data as. String is preferred.
     * \param $data The data to save. Could be a scalar value like a complex value.
     *
     * \return True if the save succeeded, False otherwise.
     */
    abstract public function set($key, $data);

    /** Sets the location for the data to be located.
     * The method implementing this prototype will be used to connect to the data source.
     *
     * \param $location The data source location to connect to. Can vary between data providers.
     *
     * \return True if the connection to the source succeeded, False otherwise.
     */
    abstract public function connect($location);

    /** Binds a method to a specific event from the provider.
     * The methods implementing this prototype should provide a multiple event system for
     * the plugins to use.
     *
     * \param $event The event to bind.
     * \param $callback The method to call when the event is fired.
     */
    // abstract public function bind($event, $callback);

    /** Checks if a storage exists.
     * Checks if a storage type exists, by checking its name against all existing storage names.
     *
     * \param $name The name to check.
     * \return The storage's class name if the storage exists, else otherwise.
     */
    public static function resolveStorage($name)
    {
        $ignoreClasses = array(
            'ObjectAccess',
            'Provider'
        );

        // Getting the current namespace to be able to load classes correctly
        $reflectionClass = new ReflectionClass(new ProviderResolverProxy());
        $currentNamespace = $reflectionClass->getNamespaceName();

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
            if ($className::getName() == $name) {
                return $className;
            }
        }

        return false;
    }

    public static function getName()
    {
        return static::STORAGE_NAME;
    }
}

/** Provider resolver stub class.
 * This class is just a stub to allow Provider to resolve using ReflectionClass by
 * giving it a way to guess the current namespace dynamically.
 */
class ProviderResolverProxy
{

}
