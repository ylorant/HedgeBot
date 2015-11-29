<?php
namespace HedgeBot\Core\Server;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Server;

class ServerInstance
{
	private $IRC;
	private $pluginManager;
	private $config;

	public function __construct()
	{
		$this->IRC = new IRCConnection(); // Init IRC connection handler
		$this->pluginManager = Plugin::getManager();
	}

	public function load($config)
	{
		$this->config = $config;
		$this->_name = HedgeBot::getServerName($this);

		$this->IRC->connect($config['address'], $config['port']);

		if(!empty($config['password']))
			$this->IRC->setPassword($config['password']);


		// Send Twitch specific commands
		$this->IRC->capabilityRequest("twitch.tv/commands");
		$this->IRC->capabilityRequest("twitch.tv/tags");
		$this->IRC->capabilityRequest("twitch.tv/membership");


		$this->IRC->setNick($config['name'], $config['name']);

		if(isset($config['floodLimit']) && HedgeBot::parseRBool($config['floodLimit']))
			$this->IRC->setFloodLimit($config['floodLimit']);
	}

	public function getConfig()
	{
		return $this->config;
	}

	public function getName()
	{
		return $this->_name;
	}

	public function getNick()
	{
		return $this->config['name'];
	}

	public function getIRC()
	{
		return $this->IRC;
	}

	public function step($data = null)
	{
		if($data == null)
			$data = $this->IRC->read();

		foreach($data as $command)
		{
			if(HedgeBot::$verbose >= 2)
				echo '<-['.Server::getName().'] '.$command."\n";
			$command = $this->IRC->parseMsg($command);
			$this->pluginManager->callEvent('server', strtolower($command['command']), $command);

			if(in_array($command['command'], array('PRIVMSG', 'NOTICE')))
			{
				$message = explode(' ', $command['message']);
				if(strlen($message[0]))
				{
					$message[0] = str_replace(':', '', $message[0]);
					if($message[0] == $this->config['name'])
			        {
						array_shift($message);
						$string = trim(join(' ', $message));
			            $this->pluginManager->execRegexEvents($command, $string);
			        }
					switch($message[0][0])
					{
						case '!': //Command
							$cmdName = substr(array_shift($message), 1);
							$cmdName = strtolower($cmdName);
							HedgeBot::message('Command catched: !$0', array($cmdName), E_DEBUG);
							$this->pluginManager->callEvent('command', $cmdName, $command, $message);
							break;
						case "\x01": //CTCP
							$cmdName = substr(array_shift($message), 1);
							HedgeBot::message('CTCP Command catched: CTCP$0', array($cmdName), E_DEBUG);
							$this->pluginManager->callEvent('command', 'CTCP'.$cmdName, $command, $message);
							break;
					}
				}
			}
		}
		$this->IRC->processBuffer();
	}
}
