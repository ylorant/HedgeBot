<?php
namespace HedgeBot\Core\API;

class Server
{
	private static $_instance;
	private $_server;

	public static function setServer($server)
	{
		$self = self::getInstance();
		$self->_server = $server;
	}

	private static function getInstance()
	{
		if(empty(self::$_instance))
			self::$_instance = new Server();

		return self::$_instance;
	}

	public static function __callStatic($command, $arguments)
	{
		$self = self::getInstance();
		$return = call_user_func_array(array($self->_server, $command), $arguments);

		return $return;
	}
}
