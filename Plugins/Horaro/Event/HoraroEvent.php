<?php

namespace HedgeBot\Plugins\Horaro\Event;

use HedgeBot\Core\Events\Event;
use HedgeBot\Plugins\Horaro\Entity\Schedule;

/**
 * Class HoraroEvent
 * @package HedgeBot\Plugins\Horaro\Event
 */
class HoraroEvent extends Event
{
    /** @var Schedule */
    protected $schedule;

    /**
     * HoraroEvent constructor.
     * @param $name
     * @param $schedule
     */
    public function __construct($name, $schedule)
    {
        parent::__construct($name);
        $this->schedule = $schedule;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'horaro';
    }
}