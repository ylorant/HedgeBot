<?php
namespace HedgeBot\Core\API;

class Data
{
	private static $_instance;
	private $_storage;

	public static function setStorage($storage)
	{
		$self = self::getInstance();
		$self->_storage = $storage;
	}

	public static function getStorage()
	{
		return self::getInstance()->_storage;
	}

    private static function getInstance()
    {
        if(empty(self::$_instance))
            self::$_instance = new Data();

        return self::$_instance;
    }

    public static function __callStatic($command, $arguments)
    {
        $self = self::getInstance();
        $return = call_user_func_array(array($self->_storage, $command), $arguments);

        return $return;
    }
}
