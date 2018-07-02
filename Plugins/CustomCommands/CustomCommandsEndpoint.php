<?php

namespace HedgeBot\Plugins\CustomCommands;

/**
 * Class CustomCommandsEndpoint
 * @package HedgeBot\Plugins\CustomCommands
 */
class CustomCommandsEndpoint
{
    /** @var CustomCommands The plugin reference */
    protected $plugin;

    /**
     * CustomCommandsEndpoint constructor.
     * Initializes the endpoint with the plugin to use as data source.
     *
     * @param CustomCommands $plugin
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
     * @see CustomCommands::getCommand()
     *
     * @param $commandName
     * @return array|null
     */
    public function getCommand($commandName)
    {
        return $this->plugin->getCommand($commandName);
    }

    /**
     * Removes a custom command.
     * @see CustomCommands::deleteCommand()
     *
     * @param $commandName
     * @return bool
     */
    public function deleteCommand($commandName)
    {
        return $this->plugin->deleteCommand($commandName);
    }

    /**
     * Updates a command.
     * Using this method, the command can change its name, but subsequent calls will have to use the new name.
     * @see CustomCommands::updateCommand()
     *
     * @param $commandName
     * @param $commandData
     * @return bool
     */
    public function saveCommand($commandName, $commandData)
    {
        return $this->plugin->saveCommand($commandName, (array) $commandData);
    }
}
