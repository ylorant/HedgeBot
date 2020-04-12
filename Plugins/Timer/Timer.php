<?php
namespace HedgeBot\Plugins\Timer;

use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Plugins\Timer\Entity\Timer as EntityTimer;
use HedgeBot\Plugins\Timer\Event\TimerEvent;

class Timer extends PluginBase
{
    /** @var EntityTimer[] $timers */
    protected $timers = [];

    public function init()
    {
        Plugin::getManager()->addEventListener(TimerEvent::getType(), 'Timer');

        // Don't load the API endpoint if we're not on the main environment
        if (ENV == "main") {
            Tikal::addEndpoint('/plugin/timer', new TimerEndpoint($this));
        }

        $this->loadData();
    }
    
    /**
     * Core event: an event has been called.
     * @param CoreEvent $event 
     * @return void 
     */
    public function CoreEventEvent(CoreEvent $event)
    {
        $eventFQN = strtolower($event->event->getType(). "/". $event->event->name);

        // Iterate through the timers to trigger the one 
        foreach($this->timers as $timer) {
            if(!empty($timer->getTriggerEvent()) && $timer->getTriggerEvent() == $eventFQN) {
                $this->startStopTimer($timer);
            }
        }
    }

    /**
     * Data has been updated externally, reload the timers.
     */
    public function CoreEventDataUpdate()
    {
        $this->loadData();
    }

    /**
     * Command event: Timer start/stop.
     * @param CommandEvent $event 
     * @return void 
     */
    public function CommandTimerToggle(CommandEvent $event)
    {
        if(empty($event->arguments[0])) {
            return IRC::reply($event, "Insufficient parameters.");
        }

        $timer = $this->getTimerById($event->arguments[0]);

        if(empty($timer)) {
            IRC::reply($event, "Timer doesn't exist.");
        }

        $this->startStopTimer($timer);

        if($timer->isStarted()) {
            IRC::reply($event, "Timer started.");
        } else {
            IRC::reply($event, "Timer stopped. Time: ". $this->formatTimerTime($timer));
        }
    }

    /**
     * Command event: Timer pause.
     * @param CommandEvent $event 
     * @return void 
     */
    public function CommandTimerPause(CommandEvent $event)
    {
        if(empty($event->arguments[0])) {
            return IRC::reply($event, "Insufficient parameters.");
        }

        $timer = $this->getTimerById($event->arguments[0]);

        if(empty($timer)) {
            IRC::reply($event, "Timer doesn't exist.");
        }

        $this->pauseResumeTimer($timer);

        if(!$timer->isPaused()) {
            IRC::reply($event, "Timer resumed.");
        } else {
            IRC::reply($event, "Timer paused. Time: ". $this->formatTimerTime($timer));
        }
    }

    /**
     * Command event: Return current timer time.
     * @param CommandEvent $event 
     * @return void 
     */
    public function CommandTimerTime(CommandEvent $event)
    {
        if(empty($event->arguments[0])) {
            return IRC::reply($event, "Insufficient parameters.");
        }

        $timer = $this->getTimerById($event->arguments[0]);

        if(empty($timer)) {
            IRC::reply($event, "Timer doesn't exist.");
        }

        IRC::reply($event, "Timer current time: ". $this->formatTimerTime($timer));
    }

    /**
     * Creates a new timer.
     * 
     * @param string $id The new timer ID.
     * 
     * @return EntityTimer|bool The new timer, or false if it couldn't be created (most likely ID is already taken). 
     */
    public function createTimer(string $id, $title = null)
    {
        if(empty($id) || !empty($this->getTimerById($id))) {
            return false;
        }

        // Check if the timer id is valid
        if(!$this->checkTimerIDSyntax($id)) {
            return false;
        }

        $newTimer = new EntityTimer();
        $newTimer->setId($id);
        $newTimer->setTitle($title);

        $this->timers[] = $newTimer;
        $this->saveData();

        return $newTimer;
    }

    /**
     * Gets the defined timers.
     * @return array The defined timers.
     */
    public function getTimers()
    {
        return $this->timers;
    }

    /**
     * Gets a timer by its ID.
     * @param string $id The ID of the timer.
     * @return EntityTimer|null The timer if found, null if not.
     */
    public function getTimerById(string $id)
    {
        foreach($this->timers as $timer) {
            if($timer->getId() == $id) {
                return $timer;
            }
        }

        return null;
    }

    /**
     * Starts and stops (splits) the specified timer.
     * 
     * @param EntityTimer $timer The timer to start/stop
     * @return EntityTimer The timer.
     */
    public function startStopTimer(EntityTimer $timer, $sendEvents = true)
    {
        $event = null;

        // Timer is not started or paused -> we start it
        if((!$timer->isStarted()) || $timer->isPaused()) {
            if(!$timer->isStarted()) {
                $this->resetTimer($timer, false);
            }

            $timer->setStartTime(microtime(true));
            $timer->setStarted(true);

            $event = "start";
        } else {
            // Stopping timer by setting its offset and its stop time.
            $timer->setOffset($this->getTimerElapsedTime($timer));
            $timer->setStartTime(null);
            $timer->setStarted(false);
            
            $event = "stop";
        }

        $this->saveData();

        if($sendEvents) {
            Plugin::getManager()->callEvent(new TimerEvent($event, $timer));
        }

        return $timer;
    }

    /**
     * Pauses and/or resumes the specified timer.
     * 
     * @param EntityTimer $timer The timer to pause.
     * @return EntityTimer The timer. 
     */
    public function pauseResumeTimer(EntityTimer $timer, $sendEvents = true)
    {
        if(!$timer->isStarted()) {
            return $timer;
        }

        $event = null;

        // Can only pause the timer if it's started and not already paused
        if(!$timer->isPaused()) {
            $timer->setOffset($this->getTimerElapsedTime($timer));
            $timer->setStartTime(null);
            $timer->setPaused(true);

            $event = "pause";
        } else {
            $timer->setStarted(true);
            $timer->setStartTime(microtime(true));
            $timer->setPaused(false);

            $event = "resume";
        }

        $this->saveData();

        if($sendEvents && $event) {
            Plugin::getManager()->callEvent(new TimerEvent($event, $timer));
        }

        return $timer;
    }

    /**
     * Resets the specified timer.
     * 
     * @param EntityTimer $timer The timer to reset.
     * @return EntityTimer The timer.
     */
    public function resetTimer(EntityTimer $timer, $sendEvents = true)
    {
        if($timer->isPaused()) {
            $timer->setPaused(false);
        }

        // Stop timer if it is started
        if($timer->isStarted()) {
            $this->startStopTimer($timer, false);
        }

        $timer->setStartTime(null);
        $timer->setOffset(0);
        
        $this->saveData();

        if($sendEvents) {
            Plugin::getManager()->callEvent(new TimerEvent('reset', $timer));
        }

        return $timer;
    }

    /**
     * Checks if the given timer ID has a valid syntax.
     * 
     * @param string $id The timer ID to check the syntax of.
     * @return bool True if the timer has a valid syntax, false if not.
     */
    public function checkTimerIDSyntax(string $id)
    {
        return preg_match('#[a-z0-9_-]+#', $id);
    }

    /**
     * Gets the elapsed time on the given timer.
     * 
     * @param EntityTimer $timer The timer to get the elapsed time for.
     * @return float The timer's elapsed time.
     */
    public function getTimerElapsedTime(EntityTimer $timer)
    {
        $elapsed = $timer->getOffset();

        if($timer->isStarted() && !$timer->isPaused()) {
            $elapsed += microtime(true) - $timer->getStartTime();
        }
        
        return $elapsed;
    }

    /**
     * Formats a timer's current time.
     * 
     * @param EntityTimer $timer The timer to get the formatted time of.
     * @param bool $milliseconds Wether to show milliseconds or not.
     * @return string The timer's time.
     */
    public function formatTimerTime(EntityTimer $timer, bool $milliseconds = false)
    {
        $elapsed = $this->getTimerElapsedTime($timer);
        $output = "";

        if($timer->isCountdown() && $timer->getCountdownAmount() > 0) {
            $elapsed = $timer->getCountdownAmount() - $elapsed;

            if($elapsed < 0) {
                $elapsed = 0;
            }
        }
        
        $totalSeconds = floor($elapsed);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor($totalSeconds / 60 - ($hours * 60));
        $seconds = floor($totalSeconds - ($minutes * 60) - ($hours * 3600));

        $components = [$hours, $minutes, $seconds];
        $components = array_map(function($el) {
            return str_pad($el, 2, "0", STR_PAD_LEFT);
        }, $components);
        $output = join($components, ':');

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

        if(is_array($timers)) {
            foreach($timers as $timer) {
                $this->timers[] = EntityTimer::fromArray($timer);
            }
        }
    }

    /**
     * Saves the plugin data into storage.
     */
    public function saveData()
    {
        $timers = [];

        /** @var EntityTimer $timer */
        foreach($this->timers as $timer) {
            $timers[] = $timer->toArray();
        }

        $this->data->timers = $timers;
    }
}