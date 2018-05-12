<?php

namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\ServerList;

/**
 * Class ServerEndpoint
 * @package HedgeBot\Core\Tikal\Endpoint
 */
class ServerEndpoint
{
    /**
     * Gets a list of all the available channels to the bot.
     *
     * @return array The list of all the available channels to the bot.
     */
    public function getAvailableChannels()
    {
        $channels = [];

        foreach (ServerList::get() as $serverName) {
            $server = ServerList::get($serverName);
            $channels = array_merge($channels, $server->getChannels());
        }

        return array_unique($channels);
    }
}
