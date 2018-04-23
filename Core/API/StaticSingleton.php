<?php

namespace HedgeBot\Core\API;

trait StaticSingleton
{
    private static $_instance;
    protected $_object;

    private static function getInstance()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public static function setObject($object)
    {
        $self = self::getInstance();
        $self->_object = $object;
    }

    public static function getObject()
    {
        $self = self::getInstance();
        return $self->_object;
    }

    public static function __callStatic($command, $arguments)
    {
        $self = self::getInstance();
        return call_user_func_array(array($self->_object, $command), $arguments);
    }
}
