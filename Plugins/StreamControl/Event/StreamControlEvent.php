<?php

namespace HedgeBot\Plugins\StreamControl\Event;

use HedgeBot\Core\Events\Event;

/**
 * Class StreamControlEvent
 * @package HedgeBot\Plugins\StreamControl\Event
 */
class StreamControlEvent extends Event
{
    /** @var mixed|null Misc data linked to the event */
    protected $data;

    /**
     * Constructor.
     * 
     * @param string $name The event name.
     * @param mixed|null $data Data to pass on in case of need.
     */
    public function __construct($name, $data = null)
    {
        parent::__construct($name);
        $this->data = $data;
    }

    /**
     * @return string
     */
    public static function getType()
    {
        return 'streamcontrol';
    }
}
