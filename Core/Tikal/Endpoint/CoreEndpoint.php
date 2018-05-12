<?php

namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\ServerList;

/**
 * Class CoreEndpoint
 * @package HedgeBot\Core\Tikal\Endpoint
 */
class CoreEndpoint
{
    /**
     * Returns true, great to test that the API is replying correctly.
     * @param  string $data A string the method will return if given.
     * @return boolean       The string it was given, or else, true.
     */
    public function ping($data = null)
    {
        if (is_null($data)) {
            return true;
        } else {
            return (string)$data;
        }
    }

    /**
     * Gets the current status of the bot.
     * Lists all the channels the bot is connected to, the loaded plugins,
     * the uptime...
     *
     * @return array The different available data.
     */
    public function status()
    {
        $data = [
            'servers' => [],
            'channels' => [],
        ];

        $serverList = ServerList::get();
        foreach ($serverList as $server) {
            $serverObj = ServerList::getServer($server);
            $connected = true;

            if (!$serverObj->isConnected()) {
                $connected = false;
            }

            $data['channels'][$server] = ServerList::get($server)->getChannels();
            $data['servers'][$server] = [
                'name' => $server,
                'connected' => $connected
            ];
        }

        return $data;
    }
}
