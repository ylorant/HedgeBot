<?php

namespace HedgeBot\Core\Events;

/**
 * Event class.
 *
 * This class contains the base data for an event, and is used by being passed to event callbacks.
 * It also allows to block further execution of events by a callback, breaking the flow of action if needed.
 */
abstract class Event
{
    protected $propagation;
    protected $name;

    abstract public static function getType();

    /**
     * Builds an event.
     *
     * @constructor
     * @param       string $name The name of the event.
     */
    public function __construct($name)
    {
        $this->propagation = true;
        $this->name = $this->normalize($name);
    }

    /**
     * Generic getter magic method, allows access to the event's properties.
     *
     * @constructor
     * @param       string $name The property to get.
     * @return      mixed        The property if found, or null if not found.
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return null;
    }

    /**
     * Stops the propagation of the event. If this function is called by a callback during execution of events,
     * then the event flow will be stopped (and the caller may also act accordingly with its propagation).
     */
    public function stopPropagation()
    {
        $this->propagation = false;
    }

    /**
     * Normalizes an event name. For now, it only returns it in full lower case.
     *
     * @param  string  $name The event name to normalize
     * @return string        The normalized event name.
     */
    final public static function normalize($name)
    {
        return strtolower($name);
    }
}
