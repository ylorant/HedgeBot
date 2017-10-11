<?php
namespace HedgeBot\Core\Events;

class TimeoutEvent extends Event
{
    public static function getType()
    {
        return 'timeout';
    }
}
