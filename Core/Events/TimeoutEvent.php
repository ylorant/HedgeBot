<?php

namespace HedgeBot\Core\Events;

/**
 * Class TimeoutEvent
 * @package HedgeBot\Core\Events
 */
class TimeoutEvent extends Event
{
    /**
     * @return string
     */
    public static function getType()
    {
        return 'timeout';
    }
}
