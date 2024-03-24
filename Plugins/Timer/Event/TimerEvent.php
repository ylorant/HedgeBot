<?php

namespace HedgeBot\Plugins\Timer\Event;

use DateInterval;
use DateTime;
use HedgeBot\Core\Events\Event;
use HedgeBot\Plugins\Timer\Entity\Timer;

/**
 * Class TimerEvent
 * @package HedgeBot\Plugins\Timer\Event
 */
class TimerEvent extends Event
{
    /** @var Timer */
    protected $timer;
    /** @var string */
    protected $player;
    /** @var string */
    protected $localTime;
    /** @var int */
    protected $msec;

    /**
     * TimerEvent constructor.
     * @param $name
     * @param $timer
     */
    public function __construct($name, Timer $timer, string $player = null)
    {
        parent::__construct($name);
        $now = new DateTime();

        $this->timer = $timer;
        $this->player = $player;
        $this->localTime = $now->format('c');
        $this->msec = $now->format('v');
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'timer';
    }
}
