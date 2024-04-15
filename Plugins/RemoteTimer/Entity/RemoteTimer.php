<?php

namespace HedgeBot\Plugins\RemoteTimer\Entity;

use DateTime;
use JsonSerializable;

/**
 * RemoteTimer entity. Represents a remote timer updated by LiveSplit on an user's PC.
 */
class RemoteTimer implements JsonSerializable
{
    /** @var string The timer key */
    protected $key;
    /** @var string The timer name */
    protected $name;
    /** @var bool Whether the timer is currently running or not */
    protected $started = false;
    /** @var bool Whether the timer is currently paused or not */
    protected $paused = false;
    /** @var float Timer start time offset (to add to start time for paused then restarted timers) */
    protected $offset;
    /** @var float Start time (timestamp to the ms) */
    protected $startTime;
    /** @var string Current split name */
    protected $currentSplitName;
    /** @var float Current split start time (timestamp to the ms) */
    protected $currentSplitStartTime;
    /** @var float Current split reference start time (timestamp to the ms) */
    protected $currentSplitReferenceStartTime;
    /** @var float Current split length Reference length (interval in sec.ms) */
    protected $currentSplitReferenceLength;
    /** @var float Sum of Bests (interval in sec.ms) */
    protected $sumOfBests;
    /** @var float Best possible time (interval in sec.ms) */
    protected $bestPossibleTime;
    /** @var int The number of started runs */
    protected $startedRuns;
    /** @var int The number of completed runs */
    protected $completedRuns;
    /** @var DateTime */
    protected $lastRefresh;

    public function __construct()
    {
        $this->renewKey();
    }

    /**
     * Gets the elapsed time.
     * 
     * @return float The timer's elapsed time.
     */
    public function getElapsedTime()
    {
        $elapsed = 0;

        if($this->isStarted()) {
            $elapsed += microtime(true) - $this->getStartTime();
        }
        
        return $elapsed;
    }

    /**
     * Generates a RemoteTimer instance from the given data array.
     * 
     * @param array $data The data to fill the remote timer with.
     * @return RemoteTimer the new RemoteTimer instance.
     */
    public static function fromArray(array $data)
    {
        $timer = new static();

        foreach ($timer as $key => $value) {
            if (isset($data[$key])) {
                $timer->$key = $data[$key];
            }
        }

        if ($data['lastRefresh']) {
            $timer->setLastRefresh(new DateTime($data['lastRefresh']));
        }

        return $timer;
    }

    /**
     * Converts the timer to an array, for storage or serialization purposes.
     */
    public function toArray()
    {
        $output = [];

        foreach ($this as $key => $value) {
            if ($value instanceof DateTime) {
                $output[$key] = $value->format('c');
            } else {
                $output[$key] = $value;
            }
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Generates a random key for the remote timer.
     * Uses a key length of 64 bytes that is base64 encoded.
     * 
     * @return string The generated random key.
     */
    protected function generateKey()
    {
        return str_replace(['/', '+', '='], '', base64_encode(random_bytes(48)));
    }

    /**
     * Renew the timer's key.
     */
    public function renewKey()
    {
        $this->key = $this->generateKey();
    }

    //// GETTERS AND SETTERS ////

    /**
     * Get the value of key
     */ 
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set the value of key
     *
     * @return RemoteTimer self
     */ 
    public function setKey(string $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get the value of name
     */ 
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */ 
    public function setName($name)
    {
        $this->name = $name;

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
     * @return RemoteTimer self
     */ 
    public function setStarted(bool $started)
    {
        $this->started = $started;

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
     * @return RemoteTimer self
     */ 
    public function setPaused(bool $paused)
    {
        $this->paused = $paused;

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
     * @return RemoteTimer self
     */ 
    public function setOffset($offset)
    {
        $this->offset = $offset;

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
     * @return RemoteTimer self
     */ 
    public function setStartTime(float $startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get the value of currentSplitName
     */ 
    public function getCurrentSplitName()
    {
        return $this->currentSplitName;
    }

    /**
     * Set the value of currentSplitName
     *
     * @return  self
     */ 
    public function setCurrentSplitName($currentSplitName)
    {
        $this->currentSplitName = $currentSplitName;

        return $this;
    }

    /**
     * Get the value of currentSplitStartTime
     */ 
    public function getCurrentSplitStartTime()
    {
        return $this->currentSplitStartTime;
    }

    /**
     * Set the value of currentSplitStartTime
     *
     * @return  self
     */ 
    public function setCurrentSplitStartTime($currentSplitStartTime)
    {
        $this->currentSplitStartTime = $currentSplitStartTime;

        return $this;
    }

    /**
     * Get the value of currentSplitReferenceStartTime
     */ 
    public function getCurrentSplitReferenceStartTime()
    {
        return $this->currentSplitReferenceStartTime;
    }

    /**
     * Set the value of currentSplitReferenceStartTime
     *
     * @return RemoteTimer self
     */ 
    public function setCurrentSplitReferenceStartTime($currentSplitReferenceStartTime)
    {
        $this->currentSplitReferenceStartTime = $currentSplitReferenceStartTime;

        return $this;
    }

    /**
     * Get the value of currentSplitReferenceLength
     */ 
    public function getCurrentSplitReferenceLength()
    {
        return $this->currentSplitReferenceLength;
    }

    /**
     * Set the value of currentSplitReferenceLength
     *
     * @return  self
     */ 
    public function setCurrentSplitReferenceLength($currentSplitReferenceLength)
    {
        $this->currentSplitReferenceLength = $currentSplitReferenceLength;

        return $this;
    }

    /**
     * Get the value of sumOfBests
     */ 
    public function getSumOfBests()
    {
        return $this->sumOfBests;
    }

    /**
     * Set the value of sumOfBests
     *
     * @return  self
     */ 
    public function setSumOfBests($sumOfBests)
    {
        $this->sumOfBests = $sumOfBests;

        return $this;
    }

    /**
     * Get the value of bestPossibleTime
     */ 
    public function getBestPossibleTime()
    {
        return $this->bestPossibleTime;
    }

    /**
     * Set the value of bestPossibleTime
     *
     * @return  self
     */ 
    public function setBestPossibleTime($bestPossibleTime)
    {
        $this->bestPossibleTime = $bestPossibleTime;

        return $this;
    }

    /**
     * Get the value of startedRuns
     */ 
    public function getStartedRuns()
    {
        return $this->startedRuns;
    }

    /**
     * Set the value of startedRuns
     *
     * @return  self
     */ 
    public function setStartedRuns($startedRuns)
    {
        $this->startedRuns = $startedRuns;

        return $this;
    }

    /**
     * Get the value of completedRuns
     */ 
    public function getCompletedRuns()
    {
        return $this->completedRuns;
    }

    /**
     * Set the value of completedRuns
     *
     * @return  self
     */ 
    public function setCompletedRuns($completedRuns)
    {
        $this->completedRuns = $completedRuns;

        return $this;
    }

    /**
     * Get the value of lastRefresh
     */ 
    public function getLastRefresh()
    {
        return $this->lastRefresh;
    }

    /**
     * Set the value of lastRefresh
     *
     * @return RemoteTimer self
     */ 
    public function setLastRefresh($lastRefresh)
    {
        $this->lastRefresh = $lastRefresh;

        return $this;
    }
}