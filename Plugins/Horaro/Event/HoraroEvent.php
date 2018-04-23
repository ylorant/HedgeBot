<?php

namespace HedgeBot\Plugins\Horaro\Event;

use HedgeBot\Core\Events\Event;
use HedgeBot\Plugins\Horaro\Entity\Schedule;

class HoraroEvent extends Event
{
    /** @var Schedule */
    protected $schedule;

    public function __construct($name, $schedule)
    {
        parent::__construct($name);
        $this->schedule = $schedule;
    }

    public static function getType()
    {
        return 'horaro';
    }
}