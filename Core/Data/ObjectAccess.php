<?php

/**
 * \file Core/Data/ObjectAccess.class.php
 * \author Yohann Lorant <yohann.lorant@gmail.com>
 * \version 0.1
 * \brief ObjectAccess class file.
 *
 * \section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * \section DESCRIPTION
 *
 * This file contains the ObjectAccess class, enabling a plugin to walk a data provider
 * object as a regular object.
 */

namespace HedgeBot\Core\Data;

class ObjectAccess
{
    private $provider; // Provider instance to refer to.
    private $currentPath; // Path currently resolved

    /** Constructor for ObjectAccess.
     * Constructor for ObjectAccess.
     * \param Provider $provider The provider to use as a data source
     * \param string   $path     The current path inside the hierarchy. Optional.
     */
    public function __construct(Provider $provider, $path = "")
    {
        $this->provider = $provider;
        $this->currentPath = $path;
    }

    /** Gets a var from the storage.
     * Gets a var from the storage, based on the current path. If the var is an array, then an ObjectAccess
     * representing the new path is returned.
     * \param  string $name Var name.
     * \return mixed        The data requested or another ObjectAccess object, depending of what is got.
     */
    public function __get($name)
    {
        $path = $this->currentPath. '.'. $name;
        $path = trim($path, '.');
        $val = $this->provider->get($path);

        if(is_null($val) || is_array($val))
            return new ObjectAccess($this->provider, $path);
        else
            return $val;
    }

    /** Sets a var into the data storage.
     * Sets a var into the data storage from the current path.
     * \param string $name Var name
     * \param mixed  $val  Var value
     * \return The return value of the proxy set() method.
     */
    public function __set($name, $val)
    {
        $path = $this->currentPath. '.'. $name;
        $path = trim($path, '.');

        return $this->provider->set($path, $val);
    }

    /** Checks if a var exists in the storage.
     * Checks if a var exists in the storage.
     *
     * \param string $name Var name.
     *
     * \return True or False.
     */
    public function __isset($name)
    {
        $path = $this->currentPath. '.'. $name;
        $path = trim($path, '.');
        $val = $this->provider->get($path);

        if(is_null($val))
            return false;

        return true;
    }

    /** Proxy to the providers' get() function.
     * Proxy to the providers' get() function.
     *
     * \param $name The var name to get.
     */
    public function get($name)
    {
        $path = trim($this->currentPath.'.'.$name, '.');
        return $this->provider->get($path);
    }

    /**
     * Proxy to the providers' set() function.
     * \param $name  The name of the var to set.
     * \param $value The value to set it to.
     */
    public function set($name, $value)
    {
        $path = trim($this->currentPath.'.'.$name, '.');
        return $this->provider->set($path, $value);
    }

    /**
     * Gets the underlying provider.
     * @return Provider The provider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Proxy to the providers' functions.
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
