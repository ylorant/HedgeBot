<?php

namespace HedgeBot\Core\API;

class ServerList
{
    private static $_instance;
    private $_main;

    public static function setMain($main)
    {
        $self = self::getInstance();
        $self->_main = $main;
    }

    private static function getInstance()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new ServerList();
        }

        return self::$_instance;
    }

    /**
     * TODO: Refactor this method to return the actual instances when no parameter is given, not the names, and make a getNames function
     */
    public static function get($name = null)
    {
        $self = self::getInstance();
        if (!empty($name)) {
            return isset($self->_main->servers[$name]) ? $self->_main->servers[$name]->getIRC() : null;
        } else {
            return array_keys($self->_main->servers);
        }
    }

    public static function getServer($name)
    {
        $self = self::getInstance();
        if (!empty($name)) {
            return isset($self->_main->servers[$name]) ? $self->_main->servers[$name] : null;
        }
    }

    public static function exists($name)
    {
        $self = self::getInstance();
        return isset($self->_main->servers[$name]);
    }
}
