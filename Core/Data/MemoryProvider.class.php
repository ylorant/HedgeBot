<?php
namespace HedgeBot\Core\Data;

use HedgeBot\Core\HedgeBot;

class MemoryProvider extends Provider
{
    private $data;

    const STORAGE_NAME = "memory";

    /** Loads a key from the storage.
     * The implemented method will allow to load data from the storage, in a transparent manner. The user only has to provide
     * the key to retrieve, and the storage should be able to retrieve the data and all its children.
     *
     * \param $key The key to retrieve.
     *
     * \return A mixed value containing the data.
     */
    public function get($key)
    {
        $keyComponents = explode('.', $key);

        $currentPath = &$this->data;
        foreach($keyComponents as $component)
        {
            if(!isset($currentPath[$component]))
                return NULL;
            elseif(is_array($currentPath))
                $currentPath = &$currentPath[$component];
            else
                return FALSE;
        }

        return $currentPath;
    }

    /** Saves a specific key into the storage.
     * The implemented version of this prototype must allow to store a single key or a complex key in a completely transparent
     * manner for the user.
     *
     * \param $key The key name to save the data as. String is preferred.
     * \param $data The data to save. Could be a scalar value like a complex value.
     *
     * \return True if the save succeeded, False otherwise.
     */
    public function set($key, $data)
    {
        $keyComponents = explode('.', $key);

        $varName = array_pop($keyComponents);

        $currentPath = &$this->data;
        foreach($keyComponents as $component)
        {
            if(!isset($currentPath[$component]))
                return FALSE;
            elseif(is_array($currentPath[$component]))
                $currentPath = &$currentPath[$component];
            else
                return FALSE;
        }

        $currentPath[$varName] = $data;
        return TRUE;
    }

    /** Sets the location for the data to be located.
     * The method implementing this prototype will be used to connect to the data source.
     *
     * \param $location The data source location to connect to. Can vary between data providers.
     *
     * \return True if the connection to the source succeeded, False otherwise.
     */
    public function connect($location)
    {
        HedgeBot::message('Resetting memory storage.', null, E_DEBUG);
        HedgeBot::message('You are using memory storage, data will not be saved across reboots.', null, E_WARNING);

        $this->data = array();
        return true;
    }

    /** Checks data update from outside sources.
     * Checks data update from outside sources. Does nothing since data isn't editable from outside.
     */
    public function checkUpdate()
    {

    }
}
