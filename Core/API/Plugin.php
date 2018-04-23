<?php

namespace HedgeBot\Core\API;

class Plugin
{
    private static $_instance;
    private $_manager;

    public static function setManager($manager)
    {
        $self = self::getInstance();
        $self->_manager = $manager;
    }

    public static function getManager()
    {
        $self = self::getInstance();
        return $self->_manager;
    }

    private static function getInstance()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new Plugin();
        }

        return self::$_instance;
    }

    public static function get($name)
    {
        $self = self::getInstance();

        return $self->_manager->getPlugin($name);
    }
}
