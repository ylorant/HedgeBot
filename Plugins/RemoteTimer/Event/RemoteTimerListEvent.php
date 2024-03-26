<?php

namespace HedgeBot\Plugins\RemoteTimer\Event;

use DateTime;
use HedgeBot\Core\Events\Event;
use HedgeBot\Plugins\RemoteTimer\Entity\RemoteTimer as RemoteTimerEntity;

class RemoteTimerListEvent extends Event
{
    /** @var array */
    protected $list;
    /** @var string */
    protected $localTime;
    /** @var int */
    protected $msec;


    public function __construct($name, array $timerList)
    {
        parent::__construct($name);
        $now = new DateTime();

        $this->list = $timerList;
        $this->localTime = $now->format('c');
        $this->msec = $now->format('v');
    }

    public static function getType()
    {
        return 'remoteTimerList';
    }
}