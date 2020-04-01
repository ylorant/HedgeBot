<?php

namespace HedgeBot\Plugins\Timer\Event;

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

    /**
     * TimerEvent constructor.
     * @param $name
     * @param $timer
     */
    public function __construct($name, Timer $timer)
    {
        parent::__construct($name);
        $this->timer = $timer;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'timer';
    }
}
