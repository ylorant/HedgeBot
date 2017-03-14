<?php
namespace HedgeBot\Core\Server;

use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\ServerList;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Config;
use HedgeBot\Core\HedgeBot;

class CoreEvents
{
	private $_main;
	private $_events;
	private $_names;

	public function __construct()
	{
		$this->_names = array();

		$events = Plugin::getManager();

		$events->addEvent('server', 'coreevents', '001', array($this, 'ServerConnected'));
		$events->addEvent('server', 'coreevents', 'kick', array($this, 'ServerKick'));
		$events->addEvent('server', 'coreevents', 'ping', array($this, 'ServerPing'));
		$events->addEvent('server', 'coreevents', '353', array($this, 'ServerNamesReply'));
		$events->addEvent('server', 'coreevents', '366', array($this, 'ServerEndOfNames'));
		$events->addEvent('server', 'coreevents', 'mode', array($this, 'ServerMode'));
		$events->addEvent('server', 'coreevents', 'join', array($this, 'ServerJoin'));
		$events->addEvent('server', 'coreevents', 'part', array($this, 'ServerPart'));

		$events->addRoutine($this, 'RoutinePingServer', 60);
		$events->addRoutine($this, 'RoutineCheckStorages', 2);

		$events->addEventListener('systemEvent', 'SystemEvent');
	}

	public function RoutineCheckStorages()
	{
		$events = Plugin::getManager();

		$updated = Config::checkUpdate();
		if($updated)
			$events->callEvent('systemEvent', 'ConfigUpdate');

		$updated = Data::checkUpdate();
		if($updated)
			$events->callEvent('systemEvent', 'DataUpdate');
	}

	public function RoutinePingServer()
	{
		foreach(ServerList::get() as $server)
		{
			$srv = ServerList::getServer($server);
			IRC::setObject($srv->getIRC());
			Server::setObject($srv);

			IRC::ping();
		}
	}

	public function ServerConnected($command)
	{
		$config = Server::getConfig();

		// Autoperform commands
		if(!empty($config['autoperform']))
		{
			foreach($config['autoperform'] as $action)
				IRC::send($action);
		}

		IRC::joinChannels($config['channels']);
		HedgeBot::getInstance()->initialized = TRUE;
	}

	public function ServerKick($command)
	{
		if($command['additionnal'][0] == Server::getNick())
			IRC::joinChannel($command['channel']);
	}

	public function ServerPing($command)
	{
		IRC::send('PONG :'.$command['additionnal']);
	}

	public function ServerJoin($command)
	{
		if(strtolower($command['nick']) != strtolower(Server::getNick()))
		{
			if($command['message'] && !$command['channel'])
				$command['channel'] = $command['message'];
			IRC::userJoin($command['channel'], $command['nick']);
		}
	}

	public function ServerPart($command)
	{
		IRC::userPart($command['channel'], $command['nick']);
	}

	public function ServerMode($command)
	{
		if(preg_match('/(\+|-).*(v|o)/', $command['additionnal'][0], $matches) && isset($command['additionnal'][1]))
		{
			if($matches[1] == '+')
				IRC::userModeAdd($command['channel'], $command['additionnal'][1], $matches[2]);
			else
				IRC::userModeRemove($command['channel'], $command['additionnal'][1], $matches[2]);
		}
	}

	public function ServerNamesReply($command)
	{
		$channel = substr($command['additionnal'][1], 1);

		if(!isset($this->_names[$channel]))
			$this->_names[$channel] = array();
		$this->_names[$channel] = array_merge($this->_names[$channel], explode(' ', $command['message']));
	}

	public function ServerEndOfNames($command)
	{
		$channel = substr($command['additionnal'][0], 1);
		IRC::setChannelUsers($channel, $this->_names[$channel]);
		unset($this->_names[$channel]);
	}

	public function ServerUserstate($command)
	{
		if(!$command['moderator'])
			HedgeBot::message("HedgeBot isn't currently a moderator. Moderator rights may be needed to perform some operations.", NULL, E_WARNING);
	}
}
