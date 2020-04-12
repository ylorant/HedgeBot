<?php

namespace HedgeBot\Core\Events;

/**
 * Class CoreEvent
 * @package HedgeBot\Core\Events
 */
class CoreEvent extends Event
{
    /** @inheritDoc */
    const BROADCAST = false;

    /**
     * Builds a core event.
     * @constructor
     * @param       string $eventName The event name.
     * @param       array $data The event data. It will be expanded into properties, so they'll be available at
     *                                $event->key = value.
     */
    public function __construct($eventName, $data = [])
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
        return 'core';
    }
}
