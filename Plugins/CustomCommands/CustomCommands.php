<?php
namespace HedgeBot\Plugins\CustomCommands;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\Store;
use HedgeBot\Core\Store\Formatter\TextFormatter;
use HedgeBot\Core\API\Tikal;

class CustomCommands extends Plugin
{
	protected $commands = [];

	public function init()
	{
		$this->loadData();

		// Don't load the API endpoint if we're not on the main environment
		if(ENV == "main")
			Tikal::addEndpoint('/plugin/custom-commands', new CustomCommandsEndpoint($this));
	}

	public function ServerPrivmsg(ServerEvent $ev)
	{
		$message = $ev->message;
		if($message[0] == '!')
		{
			$message = explode(' ', $message);
			$commandName = substr($message[0], 1);
			$command = $this->getCommand($commandName);

			if(!empty($command) && in_array($ev->channel, $command['channels']))
			{
				$formatter = Store::getFormatter(TextFormatter::getName());
				$formattedMessage = $formatter->format($command['text'], $ev->channel);
				return IRC::message($ev->channel, $formattedMessage);
			}
		}
	}

	public function CommandAddCommand(CommandEvent $ev)
	{
		$args = $ev->arguments;
		if(count($args) < 2)
			return IRC::message($ev->channel, "Insufficient parameters.");

		$newCommand = array_shift($args);
		$newCommand = $newCommand[0] == '!' ? substr($newCommand, 1) : $newCommand;
		$message = join(' ', $args);

		$commandAdded = $this->addCommand($newCommand, $message, [$ev->channel]);

		if(!$commandAdded)
			return IRC::message($ev->channel, "A command with this name already exists. Try again.");
		
		return IRC::message($ev->channel, "New message for command !". $newCommand. " registered.");
	}

	public function CommandRmCommand(CommandEvent $ev)
	{
		$args = $ev->arguments;
		if(count($args) == 0)
			return IRC::message($ev->channel, "Insufficient parameters.");

		$commandToDelete = array_shift($args);
		$commandToDelete = $commandToDelete[0] == '!' ? substr($commandToDelete, 1) : $commandToDelete;

		$commandDeleted = $this->removeCommand($commandToDelete);

		if(!$commandDeleted)
			return IRC::message($ev->channel, "This command does not exist. Try again.");

		return IRC::message($ev->channel, "Command deleted.");
	}

	/**
	 * Gets the commandes defined on the bot.
	 * 
	 * @return array The command list.
	 */
	public function getCommands()
	{
		return $this->commands;
	}

	/**
	 * Gets a command by its name.
	 * 
	 * @param string $commandName The command name.
	 * @return array|null The command data, or null if the command has not been found.
	 */
	public function getCommand($commandName)
	{
		$commandName = strtolower($commandName);

		foreach($this->commands as $command)
		{
			if($command['name'] == $commandName)
				return $command;
		}

		return null;
	}

	/**
	 * Adds a command to the command list.
	 * 
	 * @param string $command The command name, without the exclamation mark.
	 * @param string $text    The text to display when calling the command.
	 * @param array $channels The channels to add the command on.
	 * 
	 * @return bool True if the command has been added, false if not (that usually means that the command already exists).
	 */
	public function addCommand($command, $text, array $channels)
	{
		if(!empty($this->commands[$command]))
			return false;
		
		$this->commands[$command] = [
			'name' => $command,
			'channels' => $channels,
			'text' => $text
		];
		
		$this->saveData();

		return true;
	}

	/**
	 * Removes a command from the list of commands.
	 * 
	 * @param string $commandName The command name, without the exclamation mark.
	 * 
	 * @return bool True if the command has been removed, false if not (that means the command doesn't exist).
	 */
	public function removeCommand($commandName)
	{	
		foreach($this->commands as $i => $command)
		{
			if($command['name'] == $commandName)
			{
				unset($this->commands[$i]);
				$this->saveData();
				return true;
			}
		}

		return false;
	}

	/**
	 * Loads the commands data from the storage.
	 */
	protected function loadData()
	{
		$this->commands = [];

		if(!empty($this->data->commands))
			$this->commands = $this->data->commands->toArray();
	}

	/**
	 * Saves the commands data to the storage.
	 */
	protected function saveData()
	{
		$this->data->set('commands', $this->commands);
	}
}
