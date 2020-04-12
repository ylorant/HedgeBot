<?php

namespace HedgeBot\Core\Tikal;

use HedgeBot\Core\Events\Event;

/**
 * Class HttpEvent
 * @package HedgeBot\Core\Tikal
 */
class HttpEvent extends Event
{
    /** @inheritDoc */
    const BROADCAST = false;

    /**
     * HttpEvent constructor.
     * @param $eventName
     * @param $data
     */
    public function __construct($eventName, $data)
    {
        parent::__construct($eventName);

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'http';
    }
}
