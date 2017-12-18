<?php
namespace HedgeBot\Plugins\SamplePlugin;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;

class SamplePlugin extends PluginBase
{
    public function CommandHello(CommandEvent $ev)
    {
        IRC::message($ev->channel, "Hello, world!");
    }

    public function CommandGoodbye(CommandEvent $ev)
    {
        IRC::message($ev->channel, "Goodbye !");
    }
}