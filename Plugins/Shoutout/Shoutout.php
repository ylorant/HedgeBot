<?php

namespace HedgeBot\Plugins\Shoutout;

use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Store as StoreAPI;
use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\Store\Formatter\TraverseFormatter;
use HedgeBot\Core\Store\Store;
use HedgeBot\Plugins\Horaro\Entity\Schedule;
use HedgeBot\Plugins\Horaro\Horaro;
use HedgeBot\Plugins\Timer\Event\TimerEvent;
use TwitchClient\API\Helix\Helix;

/**
 * Class Shoutout
 * @package HedgeBot\Plugins\Shoutout
 */
class Shoutout extends PluginBase
{
    public function TimerStop(TimerEvent $event)
    {
        if ($event->timer->getId() == $this->config['timer']) {
            Plugin::getManager()->setTimeout($this->config['delay'], "shoutout", "sendShoutout");
        }
    }

    public function TimerStart(TimerEvent $event)
    {
        if ($event->timer->getId() == $this->config['timer']) {
            Plugin::getManager()->clearTimeout("shoutout", "sendShoutout");
        }
    }

    public function TimerReset(TimerEvent $event)
    {
        if ($event->timer->getId() == $this->config['timer']) {
            Plugin::getManager()->clearTimeout("shoutout", "sendShoutout");
        }
    }

    public function TimeoutShoutout()
    {
        /** @var Horaro $horaro */
        $horaro = Plugin::get('Horaro');
        /** @var TraverseFormatter $formatter */
        $formatter = StoreAPI::getFormatter(TraverseFormatter::getName());
        /** @var Store $store */
        $store = StoreAPI::getObject();
        /** @var Helix $helix */
        $helix = Twitch::getClient();

        $schedules = $horaro->getCurrentlyRunningSchedules();

        /** @var Schedule $schedule */
        foreach ($schedules as $schedule) {
           $shoutoutTarget = $formatter->traverse(
                Horaro::CURRENT_DATA_SOURCE_PATH . '.' . $this->config['usernameColumn'],
                $store->getData($schedule->getChannel())
            );

            HedgeBot::message("Sending shoutout to channel: $0", [$shoutoutTarget]);
            $sent = $helix->chat->shoutout($schedule->getChannel(), $shoutoutTarget);

            if (!$sent) {
                HedgeBot::message("Failed sending shoutout to channel $0", [$shoutoutTarget], E_WARNING);
            }
        }
    }
}
