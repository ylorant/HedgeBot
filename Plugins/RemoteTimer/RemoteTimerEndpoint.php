<?php

namespace HedgeBot\Plugins\RemoteTimer;

use DateTime;
use HedgeBot\Plugins\RemoteTimer\Entity\RemoteTimer as RemoteTimerEntity;

class RemoteTimerEndpoint
{
    /** @var RemoteTimer $plugin The plugin reference */
    protected $plugin;

    /**
     * RemoteTimerEndpoint constructor.
     * Initializes the endpoint with the plugin to use as source.
     *
     * @param RemoteTimer $plugin
     */
    public function __construct(RemoteTimer $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the defined timers.
     * @return array The defined timers.
     * 
     * @see RemoteTimer::getTimers()
     */
    public function getTimers()
    {
        return $this->plugin->getTimers();
    }

    /**
     * Gets a timer by its key.
     * @param string $key The timer key.
     * @return RemoteTimerEntity|null The timer if found, null if not.
     */
    public function getTimerByKey(string $key)
    {
        return $this->plugin->getTimerByKey($key);
    }

    /**
     * Gets the local time with milliseconds.
     * 
     * @return array The local time as an array with the general time in the "time" key using ISO8601,
     *               and the milliseconds in the "msec" key.
     */
    public function getLocalTime()
    {
        $now = new DateTime();

        return [
            "time" => $now->format('c'),
            "msec" => $now->format('v')
        ];
    }

    /**
     * Updates the given remote timer with the given data.
     * 
     * @param string $key The remote timer key.
     * @param array $data Data to update the timer with.
     * 
     * @return RemoteTimerEntity|false The new timer data or false if an error occured.
     */
    public function updateTimer(string $key, array $data)
    {
        return $this->plugin->updateTimer($key, $data);
    }
}