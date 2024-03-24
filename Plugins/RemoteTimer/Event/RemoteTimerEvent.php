<?php

namespace HedgeBot\Plugins\RemoteTimer\Event;

use DateTime;
use HedgeBot\Core\Events\Event;
use HedgeBot\Plugins\RemoteTimer\Entity\RemoteTimer as RemoteTimerEntity;

class RemoteTimerEvent extends Event
{
    /** @var RemoteTimer */
    protected $remoteTimer;
    /** @var string */
    protected $localTime;
    /** @var int */
    protected $msec;


    public function __construct($name, RemoteTimerEntity $timer)
    {
        parent::__construct($name);
        $now = new DateTime();

        $this->remoteTimer = $timer;
        $this->localTime = $now->format('c');
        $this->msec = $now->format('v');
    }

    public static function getType()
    {
        return 'remoteTimer';
    }
}