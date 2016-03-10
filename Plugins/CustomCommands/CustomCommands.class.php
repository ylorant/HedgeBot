<?php
namespace HedgeBot\Plugins\CustomCommands;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\API\IRC;

class CustomCommands extends Plugin
{
	private $commands = array();

	public function init()
	{
		if(!empty($this->data->commands))
			$this->commands = $this->data->commands->toArray();
	}

	public function ServerPrivmsg($cmd)
	{
		$message = $cmd['message'];
		if($message[0] == '!')
		{
			$message = explode(' ', $message);
			$command = substr($message[0], 1);

			if(isset($this->commands[$cmd['channel']][$command]))
				return IRC::message($cmd['channel'], $this->commands[$cmd['channel']][$command]);
		}
	}

	public function CommandAddCommand($param, $args)
	{
		if(!$param['moderator'])
			return;

		if(count($args) < 2)
			return IRC::message($param['channel'], "Insufficient parameters.");

		$newCommand = array_shift($args);
		$newCommand = $newCommand[0] == '!' ? substr($newCommand, 1) : $newCommand;
		$message = join(' ', $args);

		if(!empty($this->commands[$param['channel']][$newCommand]))
			return IRC::message($param['channel'], "A command with this name already exists. Try again.");

		$this->commands[$param['channel']][$newCommand] = $message;
		$this->data->set('commands', $this->commands);
		return IRC::message($param['channel'], "New message for command !". $newCommand. " registered.");
	}

	public function CommandRmCommand($param, $args)
	{
		if(!$param['moderator'])
			return;

		if(count($args) == 0)
			return IRC::message($param['channel'], "Insufficient parameters.");

		$deletedCommand = array_shift($args);
		$deletedCommand = $deletedCommand[0] == '!' ? substr($deletedCommand, 1) : $deletedCommand;

		if(empty($this->commands[$param['channel']][$deletedCommand]))
			return IRC::message($param['channel'], "This command does not exist. Try again.");

		unset($this->commands[$param['channel']][$deletedCommand]);
		$this->data->set('commands', $this->commands);
		return IRC::message($param['channel'], "Command deleted.");
	}
}
