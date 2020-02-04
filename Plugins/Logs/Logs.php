<?php
namespace HedgeBot\Plugins\Logs;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\Data\Provider;
use HedgeBot\Core\Events\ServerEvent;

class Logs extends PluginBase
{
    /** @var Provider Storage provider for the logs */
    protected $storage;
    /** @var DateTime[] Last message on chat per channel, to trigger channel live check */
    protected $lastMessageDatetime = [];

    public function init()
    {
        $storageClass = Provider::resolveStorage($this->config['storage']['type']);
        $this->storage = new $storageClass((object) $this->config['storage']);
    }

    public function ServerPrivmsg(ServerEvent $ev)
    {
        
    }
}