<?php

namespace HedgeBot\Plugins\SamplePlugin;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\CommandEvent;

/**
 * Class SamplePlugin
 * @package HedgeBot\Plugins\SamplePlugin
 */
class SamplePlugin extends PluginBase
{
    /**
     * @param CommandEvent $ev
     */
    public function CommandHello(CommandEvent $ev)
    {
        IRC::reply($ev, "Hello, world!");
    }

    /**
     * @param CommandEvent $ev
     */
    public function CommandGoodbye(CommandEvent $ev)
    {
        IRC::message($ev->channel, "Goodbye !");
    }
}