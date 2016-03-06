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

			if(isset($this->commands[$command]))
				return IRC::message($cmd['channel'], $this->commands[$command]);
		}
	}

	public function CommandAddCommand($param, $args)
	{
		if(!$param['moderator'])
			return;

		if(count($args) < 2)
			return IRC::message($param['channel'], "Insufficient parameters.");

		$newcommand = array_shift($args);
		$newcommand = $newcommand[0] == '!' ? substr($newcommand, 1) : $newcommand;
		$message = join(' ', $args);

		if(!empty($this->commands[$newcommand]))
			return IRC::message($param['channel'], "A command with this name already exists. Try again.");

		$this->commands[$newcommand] = $message;
		$this->data->set('commands', $this->commands);
		return IRC::message($param['channel'], "New message for command !". $newcommand. " registered.");
	}

	public function CommandRmCommand($param, $args)
	{
		if(!$param['moderator'])
			return;

		if(count($args) == 0)
			return IRC::message($param['channel'], "Insufficient parameters.");

		$newcommand = array_shift($args);
		$newcommand = $newcommand[0] == '!' ? substr($newcommand, 1) : $newcommand;

		if(empty($this->commands[$newcommand]))
			return IRC::message($param['channel'], "This command does not exist. Try again.");

		unset($this->commands[$newcommand]);
		$this->data->set('commands', $this->commands);
		return IRC::message($param['channel'], "Command deleted.");
	}
}
