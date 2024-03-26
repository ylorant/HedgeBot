<?php

namespace HedgeBot\Plugins\RemoteTimer;

use DateTime;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Plugins\RemoteTimer\Event\RemoteTimerEvent;
use HedgeBot\Plugins\RemoteTimer\Entity\RemoteTimer as RemoteTimerEntity;
use HedgeBot\Plugins\RemoteTimer\Event\RemoteTimerListEvent;

class RemoteTimer extends PluginBase
{
    /** @var RemoteTimerEntity[] $timers */
    protected $timers = [];

    public function init()
    {
        Plugin::getManager()->addEventListener(RemoteTimerEvent::getType(), 'RemoteTimer');
        Plugin::getManager()->addEventListener(RemoteTimerListEvent::getType(), 'RemoteTimerList');

        // Don't load the API endpoint if we're not on the main environment
        if (ENV == "main") {
            Tikal::addEndpoint('/plugin/remote-timer', new RemoteTimerEndpoint($this));
            Tikal::addPublicEndpoint('/public/plugin/remote-timer', new RemoteTimerPublicEndpoint($this));
        }

        $this->loadData();
    }

    /**
     * Data has been updated externally, reload the timers.
     */
    public function CoreEventDataUpdate()
    {
        $this->loadData();
    }

    /**
     * Creates a time with the given name.
     * 
     * @param string $name The name to give to the timer.
     * 
     * @return RemoteTimerEntity The new timer.
     */
    public function createTimer(string $name)
    {
        $timer = new RemoteTimerEntity();
        $timer->setName($name);
        $timer->setLastRefresh(new DateTime());

        $this->timers[] = $timer;
        $this->saveData();

        Plugin::getManager()->callEvent(new RemoteTimerEvent('new', $timer));

        return $timer;
    }

    /**
     * Gets the list of registered timers.
     * 
     * @return RemoteTimerEntity[] The list of registered timers.
     */
    public function getTimers()
    {
        return $this->timers;
    }

    /**
     * Gets an existing timer by its key.
     * 
     * @param string $key The timer key.
     * 
     * @return RemoteTimerEntity|null The remote timer if found, null if not.
     */
    public function getTimerByKey(string $key)
    {
        /** @var RemoteTimerEntity $timer */
        foreach ($this->timers as $timer)
        {
            if ($timer->getKey() == $key) {
                return $timer;
            }
        }

        return null;
    }

    /**
     * Updates the given timer (by its key) with new data, performing necessary conversions if needed.
     * 
     * @param string $key The timer key.
     * @param array $newData The new timer data to update, can be a partial update.
     * 
     * @return RemoteTimerEntity|false The updated timer or false if the timer isn't found.
     */
    public function updateTimer(string $key, array $newData)
    {
        $timer = $this->getTimerByKey($key);

        if (empty($timer)) {
            return false;
        }

        foreach ($newData as $key => $value) {
            $setter = "set" . ucfirst($key);

            if (method_exists($timer, $setter)) {
                $timer->$setter($value);
            }
        }
        
        $this->saveData();
        Plugin::getManager()->callEvent(new RemoteTimerEvent("update", $timer));

        return $timer;
    }

    /**
     * Deletes the timer identified by the given key, if it exists.
     * 
     * @param string $key The key of the timer to delete.
     * 
     * @return bool True if the timer has been deleted, false if not (timer doesn't exist).
     */
    public function deleteTimer(string $key)
    {
        foreach ($this->timers as $index => $timer) {
            if ($timer->getKey() == $key) {
                unset($this->timers[$index]);
                $this->saveData();
                Plugin::getManager()->callEvent(new RemoteTimerEvent("delete", $timer));
                return true;
            }
        }

        return false;
    }

    /**
     * Formats a timer's current time.
     * 
     * @param RemoteTimerEntity $timer The timer to get the formatted time of.
     * @param bool $milliseconds Wether to show milliseconds or not.
     * @return string The timer's time.
     */
    public function formatTimerTime(RemoteTimerEntity $timer, bool $milliseconds = false)
    {
        $elapsed = $timer->getElapsedTime();
        $output = "";
        
        $totalSeconds = floor($elapsed);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor($totalSeconds / 60 - ($hours * 60));
        $seconds = floor($totalSeconds - ($minutes * 60) - ($hours * 3600));

        $components = [$hours, $minutes, $seconds];
        $components = array_map(function($el) {
            return str_pad($el, 2, "0", STR_PAD_LEFT);
        }, $components);
        $output = join(':', $components);

        if($milliseconds) {
            $ms = round($elapsed - $totalSeconds, 3);
            $output .= ".". $ms;
        }
        
        return $output;
    }

    /**
     * Loads the plugin data from storage.
     */
    public function loadData()
    {
        $timers = $this->data->timers->toArray();
        $this->timers = [];

        if (is_array($timers)) {
            foreach ($timers as $timer) {
                $this->timers[] = RemoteTimerEntity::fromArray($timer);
            }

            Plugin::getManager()->callEvent(new RemoteTimerListEvent('reload', $this->timers));
        }
    }

    /**
     * Saves the plugin data into storage.
     */
    public function saveData()
    {
        $timers = [];
        $now = new DateTime();

        /** @var RemoteTimerEntity $timer */
        foreach ($this->timers as $timer) {
            // Update the timer last refresh time
            $timer->setLastRefresh($now);

            $timers[] = $timer->toArray();
        }

        $this->data->timers = $timers;
    }
}