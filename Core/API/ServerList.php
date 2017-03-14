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
		if(empty(self::$_instance))
			self::$_instance = new Server();

		return self::$_instance;
	}

	public static function get($name = NULL)
	{
		$self = self::getInstance();
		if(!empty($name))
			return isset($self->_main->servers[$name]) ? $self->_main->servers[$name]->getIRC() : NULL;
		else
			return array_keys($self->_main->servers);
	}

	public static function getServer($name)
	{
		$self = self::getInstance();
		if(!empty($name))
			return isset($self->_main->servers[$name]) ? $self->_main->servers[$name] : NULL;
	}

	public static function exists($name)
	{
		$self = self::getInstance();
		return isset($self->_main->servers[$name]);
	}
}
