<?php
namespace HedgeBot\Core\Server;

use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\ServerList;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Config;
use HedgeBot\Core\HedgeBot;

/**
 * Core Events handler class.
 * This class handles basic server events, and as such, it handles the basic workflow
 * for the bot connection lifecycle (authentication against the server and things).
 */
class CoreEvents
{
	private $names;
	private $lastMessages = [];

	public function __construct()
	{
		$this->names = [];

		$events = Plugin::getManager();

		// Server commands the bot is supposed to answer to
		// TODO: Use autoloading question mark ?
		$events->addEvent('server', 'coreevents', '001', array($this, 'ServerConnected'));
		$events->addEvent('server', 'coreevents', 'kick', array($this, 'ServerKick'));
		$events->addEvent('server', 'coreevents', 'ping', array($this, 'ServerPing'));
		$events->addEvent('server', 'coreevents', '353', array($this, 'ServerNamesReply'));
		$events->addEvent('server', 'coreevents', '366', array($this, 'ServerEndOfNames'));
		$events->addEvent('server', 'coreevents', 'mode', array($this, 'ServerMode'));
		$events->addEvent('server', 'coreevents', 'join', array($this, 'ServerJoin'));
		$events->addEvent('server', 'coreevents', 'part', array($this, 'ServerPart'));

		// Core events
		$events->addEvent('core', 'coreevents', 'ServerMessage', array($this, 'CoreEventServerMessage'));

		// Routines
		$events->addRoutine($this, 'RoutinePingServer', 60);
		$events->addRoutine($this, 'RoutineCheckStorages', 2);
		$events->addRoutine($this, 'RoutineReconnect', 5);
	}

	public function RoutineCheckStorages()
	{
		$events = Plugin::getManager();

		$updated = Config::checkUpdate();
		if($updated)
			$events->callEvent('core', 'ConfigUpdate');

		$updated = Data::checkUpdate();
		if($updated)
			$events->callEvent('core', 'DataUpdate');
	}

	public function RoutinePingServer()
	{
		$time = time();

		foreach(ServerList::get() as $server)
		{
			$srv = ServerList::getServer($server);
			IRC::setObject($srv->getIRC());
			Server::setObject($srv);

			// Before pinging, check if last message from server isn't older than 90 secs (timeout)
			if(!empty($this->lastMessages[$server]) && $this->lastMessages[$server] < ($time - 90))
			{
				HedgeBot::message('Connection to server $0 lost, reconnecting.', array(Server::getName()), E_WARNING);
				Server::reconnect();

				continue;
			}

			IRC::ping();
		}
	}

	public function RoutineReconnect()
	{
		foreach(ServerList::get() as $server)
		{
			$srv = ServerList::getServer($server);

			if(!$srv->isConnected())
				$srv->connect();
		}
	}

	public function CoreEventServerMessage($command)
	{
		$serverName = Server::getName();
		$this->lastMessages[$serverName] = time();
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

		if(!isset($this->names[$channel]))
			$this->names[$channel] = array();
		$this->names[$channel] = array_merge($this->names[$channel], explode(' ', $command['message']));
	}

	public function ServerEndOfNames($command)
	{
		$channel = substr($command['additionnal'][0], 1);
		IRC::setChannelUsers($channel, $this->names[$channel]);
		unset($this->names[$channel]);
	}

	public function ServerUserstate($command)
	{
		if(!$command['moderator'])
			HedgeBot::message("HedgeBot isn't currently a moderator. Moderator rights may be needed to perform some operations.", NULL, E_WARNING);
	}
}
