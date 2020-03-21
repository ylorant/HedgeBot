<?php

namespace HedgeBot\Core\Data;

/**
 * Class ObjectAccess
 * @package HedgeBot\Core\Data
 */
class ObjectAccess
{
    private $provider; // Provider instance to refer to.
    private $currentPath; // Path currently resolved

    /**
     * Constructor for ObjectAccess.
     *
     * @param Provider $provider The provider to use as a data source
     * @param string $path The current path inside the hierarchy. Optional.
     */
    public function __construct(Provider $provider, $path = "")
    {
        $this->provider = $provider;
        $this->currentPath = $path;
    }

    /**
     * Gets a var from the storage.
     * Gets a var from the storage, based on the current path. If the var is an array, then an ObjectAccess
     * representing the new path is returned.
     *
     * @param  string $name Var name.
     * @return mixed The data requested or another ObjectAccess object, depending of what is got.
     */
    public function __get($name)
    {
        $path = $this->currentPath . '.' . $name;
        $path = trim($path, '.');
        $val = $this->provider->get($path);

        if (is_null($val) || is_array($val)) {
            return new ObjectAccess($this->provider, $path);
        } else {
            return $val;
        }
    }

    /**
     * Sets a var into the data storage from the current path.
     *
     * @param string $name Var name
     * @param mixed $val Var value
     * @return The return value of the proxy set() method.
     */
    public function __set($name, $val)
    {
        $path = $this->currentPath . '.' . $name;
        $path = trim($path, '.');

        return $this->provider->set($path, $val);
    }

    /**
     * Checks if a var exists in the storage.
     *
     * @param string $name Var name.
     * @return bool
     */
    public function __isset($name)
    {
        $path = $this->currentPath . '.' . $name;
        $path = trim($path, '.');
        $val = $this->provider->get($path);

        if (is_null($val)) {
            return false;
        }

        return true;
    }

    /**
     * Proxy to the providers' get() function.
     *
     * @param string $name The var name to get.
     * @return ???
     */
    public function get($name)
    {
        $path = trim($this->currentPath . '.' . $name, '.');
        return $this->provider->get($path);
    }

    /**
     * Proxy to the providers' set() function.
     *
     * @param string $name  The name of the var to set.
     * @param $value The value to set it to.
     * @return ???
     */
    public function set($name, $value)
    {
        $path = trim($this->currentPath . '.' . $name, '.');
        return $this->provider->set($path, $value);
    }

    /**
     * Gets the underlying provider.
     *
     * @return Provider The provider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Proxy to the providers' functions.
     *
     * @param $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array(array($this->provider, $name), $arguments);
    }

    /**
     * Gets the array representation of the current path.
     * \return array The array representation for current data.
     */
    public function toArray()
    {
        return $this->provider->get($this->currentPath);
    }
}
