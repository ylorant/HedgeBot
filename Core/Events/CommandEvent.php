<?php

namespace HedgeBot\Core\Events;

/**
 * Class CommandEvent
 * @package HedgeBot\Core\Events
 */
class CommandEvent extends ServerEvent
{
    /** @inheritDoc */
    const BROADCAST = false;

    protected $arguments;

    /**
     * Creates a command event.
     *
     * @constructor
     * @param       string $commandName The command name.
     * @param       array $args The arguments given to the command.
     * @param       array $command The server message data array, using IRCConnection::parseMsg() keys.
     */
    public function __construct($commandName, array $args, array $command)
    {
        parent::__construct($command);

        $this->name = $commandName; // Override the parent serverEvent with the command name
        $this->arguments = $args;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'command';
    }
}
