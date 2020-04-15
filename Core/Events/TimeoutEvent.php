<?php

namespace HedgeBot\Core\Events;

/**
 * Class TimeoutEvent
 * @package HedgeBot\Core\Events
 */
class TimeoutEvent extends Event
{
    /** @var string Event launcher id */
    protected $id;

    public function __construct($name, $id)
    {
        parent::__construct($name);
        
        $this->id = $id;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'timeout';
    }
}
