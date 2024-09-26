<?php

namespace HedgeBot\Core\API;

use HedgeBot\Core\Plugins\PluginManager;

/**
 * Class Plugin
 * @package HedgeBot\Core\API
 */
class Plugin
{
    private static $instance;
    private $manager;

    /**
     * @param $manager
     */
    public static function setManager($manager)
    {
        $self = self::getInstance();
        $self->manager = $manager;
    }

    /**
     * @return PluginManager
     */
    public static function getManager()
    {
        $self = self::getInstance();
        return $self->manager;
    }

    /**
     * @return Plugin
     */
    private static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new Plugin();
        }

        return self::$instance;
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function get($name)
    {
        $self = self::getInstance();

        return $self->manager->getPlugin($name);
    }
}
