<?php

namespace HedgeBot\Plugins\RemoteTimer;

use DateTime;
use HedgeBot\Plugins\RemoteTimer\Entity\RemoteTimer as RemoteTimerEntity;

class RemoteTimerPublicEndpoint
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
     * Gets a timer by its key.
     * @param string $key The timer key.
     * @return RemoteTimerEntity|null The timer if found, null if not.
     */
    public function getTimerByKey(string $key)
    {
        return $this->plugin->getTimerByKey($key);
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
        // Filter data that we don't want the user to update thru the public API
        $data = array_filter($data, function ($element, $key) {
            return !in_array($key, ['key', 'name', 'lastRefresh']);
        }, ARRAY_FILTER_USE_BOTH);

        // Converting dates that are present from ISO representation to float timestamps
        array_walk($data, function (&$element, $key) {
            if (in_array($key, ["startTime"])) {
                $element = floatval((new DateTime($element))->format("U.u"));
            }
        });

        return $this->plugin->updateTimer($key, $data);
    }
}