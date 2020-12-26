<?php
namespace HedgeBot\Plugins\Timer;

use DateInterval;
use DateTime;
use HedgeBot\Plugins\Timer\Entity\RaceTimer;

class TimerEndpoint
{
    /** @var Timer $plugin The plugin reference */
    protected $plugin;

    /**
     * TimerEndpoint constructor.
     * Initializes the endpoint with the plugin to use as source.
     *
     * @param Timer $plugin
     */
    public function __construct(Timer $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the defined timers.
     * @return array The defined timers.
     * 
     * @see Timer::getTimers()
     */
    public function getTimers()
    {
        return $this->plugin->getTimers();
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
     * Gets a timer by its ID.
     * @param string $id The timer ID
     * @return Timer|null The timer if found, null if not.
     */
    public function getTimerById(string $id)
    {
        return $this->plugin->getTimerById($id);
    }

    public function startStopTimer(string $id)
    {
        $timer = $this->plugin->getTimerById($id);

        if(empty($timer)) {
            return false;
        }

        return $this->plugin->startStopTimer($timer);
    }

    public function stopPlayerTimer(string $id, string $player)
    {
        $timer = $this->plugin->getTimerById($id);

        if(empty($timer) || !($timer instanceof RaceTimer) || !$timer->hasPlayer($player)) {
            return false;
        }

        return $this->plugin->stopPlayerTimer($timer, $player);
    }

    public function pauseResumeTimer(string $id)
    {
        $timer = $this->plugin->getTimerById($id);

        if(empty($timer)) {
            return false;
        }

        return $this->plugin->pauseResumeTimer($timer);
    }

    public function resetTimer(string $id)
    {
        $timer = $this->plugin->getTimerById($id);

        if(empty($timer)) {
            return false;
        }

        return $this->plugin->resetTimer($timer);
    }
}