<?php
namespace HedgeBot\Core\Tikal;

use HedgeBot\Core\Events\Event;

class HttpEvent extends Event
{
    public function __construct($eventName, $data)
    {
        parent::__construct($eventName);

        foreach($data as $key => $value)
            $this->$key = $value;
    }

    public static function getType()
    {
        return 'http';
    }
}
