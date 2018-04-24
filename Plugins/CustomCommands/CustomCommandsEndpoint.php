<?php

namespace HedgeBot\Plugins\CustomCommands;

class CustomCommandsEndpoint
{
    /** The plugin reference */
    protected $plugin;

    /**
     * Constructor. Initializes the endpoint with the plugin to use as data source.
     */
    public function __construct(CustomCommands $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the list of defined commands.
     *
     * @see CustomCommands::getCommands()
     */
    public function getCommands()
    {
        return $this->plugin->getCommands();
    }

    /**
     * Gets a command by its name.
     *
     * @see CustomCommands::getCommand()
     */
    public function getCommand($commandName)
    {
        return $this->plugin->getCommand($commandName);
    }

    /**
     * Removes a custom command.
     *
     * @see CustomCommands::removeCommand()
     */
    public function removeCommand($commandName)
    {
        return $this->plugin->removeCommand($commandName);
    }

    /**
     * Updates a command. Using this method, the command can change its name, but subsequent calls will have to use the new name.
     *
     * @see CustomCommands::updateCommand()
     */
    public function saveCommand($commandName, $commandData)
    {
        return $this->plugin->saveCommand($commandName, (array) $commandData);
    }
}