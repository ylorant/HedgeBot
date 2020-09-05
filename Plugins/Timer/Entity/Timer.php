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
    /** @var string Timer title */
    protected $title;
    /** @var float The timer start time for the last active segment, as microtime() */
    protected $startTime;
    /** @var float The timer last stop time. Allows to undo a timer stop. */
    protected $stopTime;
    /** @var float The timer offset in seconds (float for microseconds) */
    protected $offset;
    /** @var bool True if paused, false if not */
    protected $paused;
    /** @var bool True if the timer is started, false if not */
    protected $started;
    /** @var bool Wether the timer is a countdown or not. */
    protected $countdown;
    /** @var float The countdown start time (float for microseconds) */
    protected $countdownAmount;
    /** @var string Trigger event for the timer to start */
    protected $triggerEvent;

    const TYPE = "timer-simple";

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

    /// TIMER OPERATIONS ///

    /**
     * Gets the elapsed time.
     * 
     * @return float The timer's elapsed time.
     */
    public function getElapsedTime()
    {
        $elapsed = $this->getOffset();

        if($this->isStarted() && !$this->isPaused()) {
            $elapsed += microtime(true) - $this->getStartTime();
        }
        
        return $elapsed;
    }

    /**
     * Generates a Timer instance from the given data array.
     * 
     * @param array $data The data to fill the timer with.
     * @return Timer the new Timer instance.
     */
    public static function fromArray(array $data)
    {
        $timer = new static();

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

        $output['type'] = static::TYPE;

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
     * Get the value of title
     */ 
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the value of title
     *
     * @return Timer self
     */ 
    public function setTitle($title)
    {
        $this->title = $title;

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
     * Get the value of stopTime
     */ 
    public function getStopTime()
    {
        return $this->stopTime;
    }

    /**
     * Set the value of stopTime
     *
     * @return Timer self
     */ 
    public function setStopTime($stopTime)
    {
        $this->stopTime = $stopTime;

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
     * Get the value of countdown
     */ 
    public function isCountdown()
    {
        return $this->countdown;
    }

    /**
     * Set the value of countdown
     *
     * @return Timer self
     */ 
    public function setCountdown($countdown)
    {
        $this->countdown = $countdown;

        return $this;
    }

    /**
     * Get the value of countdownAmount
     */ 
    public function getCountdownAmount()
    {
        return $this->countdownAmount;
    }

    /**
     * Set the value of countdownAmount
     *
     * @return Timer self
     */ 
    public function setCountdownAmount($countdownAmount)
    {
        $this->countdownAmount = $countdownAmount;

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
        return array_merge(['type' => static::TYPE], $this->toArray());
    }
}