<?php
namespace HedgeBot\Plugins\CustomCommands;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\Store;
use HedgeBot\Core\Store\Formatter\TextFormatter;

class CustomCommands extends Plugin
{
	private $commands = array();

	public function init()
	{
		if(!empty($this->data->commands))
			$this->commands = $this->data->commands->toArray();
	}

	public function ServerPrivmsg(ServerEvent $ev)
	{
		$message = $ev->message;
		if($message[0] == '!')
		{
			$message = explode(' ', $message);
			$command = substr($message[0], 1);

			if(isset($this->commands[$ev->channel][$command]))
			{
				$formatter = Store::getFormatter(TextFormatter::getName());
				$formattedMessage = $formatter->format($this->commands[$ev->channel][$command], $ev->channel);
				return IRC::message($ev->channel, $formattedMessage);
			}
		}
	}

	public function CommandAddCommand(CommandEvent $ev)
	{
		if(!$ev->moderator)
			return;

		$args = $ev->arguments;
		if(count($args) < 2)
			return IRC::message($ev->channel, "Insufficient parameters.");

		$newCommand = array_shift($args);
		$newCommand = $newCommand[0] == '!' ? substr($newCommand, 1) : $newCommand;
		$message = join(' ', $args);

		if(!empty($this->commands[$ev->channel][$newCommand]))
			return IRC::message($ev->channel, "A command with this name already exists. Try again.");

		$this->commands[$ev->channel][$newCommand] = $message;
		$this->data->set('commands', $this->commands);
		return IRC::message($ev->channel, "New message for command !". $newCommand. " registered.");
	}

	public function CommandRmCommand(CommandEvent $ev)
	{
		if(!$ev->moderator)
			return;

		$args = $ev->arguments;
		if(count($args) == 0)
			return IRC::message($ev->channel, "Insufficient parameters.");

		$deletedCommand = array_shift($args);
		$deletedCommand = $deletedCommand[0] == '!' ? substr($deletedCommand, 1) : $deletedCommand;

		if(empty($this->commands[$ev->channel][$deletedCommand]))
			return IRC::message($ev->channel, "This command does not exist. Try again.");

		unset($this->commands[$ev->channel][$deletedCommand]);
		$this->data->set('commands', $this->commands);
		return IRC::message($ev->channel, "Command deleted.");
	}
}
