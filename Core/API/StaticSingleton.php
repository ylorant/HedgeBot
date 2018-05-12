<?php

namespace HedgeBot\Core\API;

/**
 * Trait StaticSingleton
 * @package HedgeBot\Core\API
 */
trait StaticSingleton
{
    private static $instance;
    protected $_object;

    /**
     * @return StaticSingleton
     */
    private static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $object
     */
    public static function setObject($object)
    {
        $self = self::getInstance();
        $self->_object = $object;
    }

    /**
     * @return mixed
     */
    public static function getObject()
    {
        $self = self::getInstance();
        return $self->_object;
    }

    /**
     * @param $command
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($command, $arguments)
    {
        $self = self::getInstance();
        return call_user_func_array(array($self->_object, $command), $arguments);
    }
}
