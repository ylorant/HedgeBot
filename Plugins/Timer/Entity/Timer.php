<?php
namespace HedgeBot\Plugins\Timer\Entity;

use JsonSerializable;

/**
 * Timer entity class. Represents a timer.
 * 
 * Some timers can be countdowns, i.e. having a set amount of time to count down to 0.
 */
class Timer implements JsonSerializable
{
    /** @var string Timer ID */
    protected $id;
    /** @var float The timer start time for the last active segment, as microtime() */
    protected $startTime;
    /** @var float The timer offset in seconds (float for microseconds) */
    protected $offset;
    /** @var bool True if paused, false if not */
    protected $paused;
    /** @var bool True if the timer is started, false if not */
    protected $started;
    /** @var bool Wether the timer is a countdown or not. */
    protected $countdown;
    /** @var float The countdown start time (float for microseconds) */
    protected $countdownStart;
    /** @var string Trigger event for the timer to start */
    protected $triggerEvent;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->paused = false;
        $this->started = false;
        $this->countdown = false;
        $this->offset = 0;
        $this->startTime = 0;
        $this->countdownStart = 0;
    }

    /**
     * Generates a Timer instance from the given data array.
     * 
     * @param array $data The data to fill the timer with.
     * @return Timer the new Timer instance.
     */
    public static function fromArray(array $data)
    {
        $timer = new Timer();

        foreach($timer as $key => $value) {
            if(isset($data[$key])) {
                $timer->$key = $data[$key];
            }
        }

        return $timer;
    }

    /**
     * Gets the Timer as an array for storage.
     * 
     * @return array The timer as an array.
     */
    public function toArray()
    {
        $output = [];

        foreach($this as $key => $value) {
            $output[$key] = $value;
        }

        return $output;
    }

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return Timer self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of startTime
     */ 
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set the value of startTime
     *
     * @return Timer self
     */ 
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get the value of offset
     */ 
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set the value of offset
     *
     * @return Timer self
     */ 
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Get the value of paused
     */ 
    public function isPaused()
    {
        return $this->paused;
    }

    /**
     * Set the value of paused
     *
     * @return Timer self
     */ 
    public function setPaused($paused)
    {
        $this->paused = $paused;

        return $this;
    }

    /**
     * Get the value of started
     */ 
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Set the value of started
     *
     * @return Timer self
     */ 
    public function setStarted($started)
    {
        $this->started = $started;

        return $this;
    }

    /**
     * Get the value of countdownStart
     */ 
    public function getCountdownStart()
    {
        return $this->countdownStart;
    }

    /**
     * Set the value of countdownStart
     *
     * @return Timer self
     */ 
    public function setCountdownStart($countdownStart)
    {
        $this->countdownStart = $countdownStart;

        return $this;
    }

    /**
     * Get the value of triggerEvent
     */ 
    public function getTriggerEvent()
    {
        return $this->triggerEvent;
    }

    /**
     * Set the value of triggerEvent
     *
     * @return Timer self
     */ 
    public function setTriggerEvent($triggerEvent)
    {
        $this->triggerEvent = $triggerEvent;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}