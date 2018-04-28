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

		$newCommandData = [];
		$newCommand = array_shift($args);
		$newCommandData['name'] = $newCommand[0] == '!' ? substr($newCommand, 1) : $newCommand;
		$newCommandData['text'] = join(' ', $args);
		$newCommandData['channels'] = [$ev->channel];
		
		$commandAdded = $this->saveCommand($newCommand, $newCommandData);

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
	 * Saves a command into the list of commands. This method is to be used to create a command as well as update it.
	 * 
	 * @param string $commandName The command name to save. If this is an update and the name of the command is to be changed,
	 * 							  then this parameter has to be the *old* name of the command, to allow proper replacement.
	 * @param array  $commandData The command data. The allowed keys are :
	 * 							  - name: The (new) command name.
	 * 							  - text: The command reply text.
	 * 							  - channels: The channel list where the command is active, as an array.
	 */
	public function saveCommand($commandName, array $commandData)
	{
		$oldCommandData = $this->getCommand($commandName);
		
		// If the command exists, we update the data and save it in the place of the old one
		if(!empty($oldCommandData))
		{
			// Fill the remaining of the new data with the old one if new data isn't present
			$commandData['name'] = $commandData['name'] ?? $oldCommandData['name'];
			$commandData['text'] = $commandData['text'] ?? $oldCommandData['text'];
			$commandData['channels'] = $commandData['channels'] ?? $oldCommandData['channels'];

			// Get the old 
			foreach($this->commands as $i => &$command)
			{
				if($command['name'] == $commandName)
				{
					$command = $commandData;
					break;
				}
			}	
		}
		else // If there isn't any command present (it's a new command), we need to create it
		{
			// Check if basic data is needed, and if not, fail
			if(empty($commandData['name']) || empty($commandData['text']))
				return false;
			
			// Set a default channels value if needed, and cast it as an array
			$commandData['channels'] = (array) ($commandData['channels'] ?? []);

			// Add the command
			$this->commands[] = $commandData;
		}

		$this->saveData();
		return true;
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
