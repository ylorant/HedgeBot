<?php

namespace HedgeBot\Core\API;

/**
 * Class ServerList
 * @package HedgeBot\Core\API
 */
class ServerList
{
    private static $instance;
    private $main;

    /**
     * @param $main
     */
    public static function setMain($main)
    {
        $self = self::getInstance();
        $self->main = $main;
    }

    /**
     * @return ServerList
     */
    private static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new ServerList();
        }

        return self::$instance;
    }

    /**
     *
     * TODO: Refactor this method to return the actual instances when no parameter is given, not the names, and make a getNames function
     *
     * @param null $name
     * @return array|null
     */
    public static function get($name = null)
    {
        $self = self::getInstance();
        if (!empty($name)) {
            return isset($self->main->servers[$name]) ? $self->main->servers[$name]->getIRC() : null;
        } else {
            return array_keys($self->main->servers);
        }
    }

    /**
     * @param $name
     * @return null
     */
    public static function getServer($name)
    {
        $self = self::getInstance();
        if (!empty($name)) {
            return isset($self->main->servers[$name]) ? $self->main->servers[$name] : null;
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public static function exists($name)
    {
        $self = self::getInstance();
        return isset($self->main->servers[$name]);
    }
}
