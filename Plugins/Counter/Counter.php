<?php

namespace HedgeBot\Plugins\Counter;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;

/**
 * Class Counter
 * @package HedgeBot\Plugins\Counter
 */
class Counter extends PluginBase
{

    /**
     * @return bool
     */
    public function init()
    {
        if (!empty($this->data->counters)) {
            $this->counters = $this->data->counters->toArray();
        }
        return true;
    }

    /**
     * @param ServerEvent $ev
     * @return mixed
     */
    public function ServerPrivmsg(ServerEvent $ev)
    {
        $message = $ev->message;
        if ($message[0] == '!') {
            $message = explode(' ', $message);
            $command = substr($message[0], 1);

            if (isset($this->counters[$ev->channel][$command])) {
                $this->counters[$ev->channel][$command][1]++;
                $this->data->set('counters', $this->counters);
                return IRC::message($ev->channel, $this->formatCounterMessage($this->counters[$ev->channel][$command]));
            }
        }
    }

    /**
     * @param CommandEvent $ev
     */
    public function CommandAddCounter(CommandEvent $ev)
    {
        if (!$ev->moderator) {
            return;
        }

        $args = $ev->arguments;
        if (count($args) < 2) {
            return IRC::message($ev->channel, "Insufficient parameters.");
        }

        $newCounter = array_shift($args);
        $newCounter = $newCounter[0] == '!' ? substr($newCounter, 1) : $newCounter;
        $message = join(' ', $args);

        if (!empty($this->counters[$ev->channel][$newCounter])) {
            return IRC::message($ev->channel, "A counter with this name already exists. Try again.");
        }

        $this->counters[$ev->channel][$newCounter] = [$message, 0];
        $this->data->set('counters', $this->counters);
        return IRC::message($ev->channel, "New counter !" . $newCounter . " registered.");
    }

    /**
     * @param CommandEvent $ev
     */
    public function CommandRmCounter(CommandEvent $ev)
    {
        if (!$ev->moderator) {
            return;
        }

        $args = $ev->arguments;
        if (count($args) == 0) {
            return IRC::message($ev->channel, "Insufficient parameters.");
        }

        $deletedCounter = array_shift($args);
        $deletedCounter = $deletedCounter[0] == '!' ? substr($deletedCounter, 1) : $deletedCounter;

        if (empty($this->counters[$ev->channel][$deletedCounter])) {
            return IRC::message($ev->channel, "This counter does not exist. Try again.");
        }

        unset($this->counters[$ev->channel][$deletedCounter]);
        $this->data->set('counters', $this->counters);
        return IRC::message($ev->channel, "Counter deleted.");
    }

    /**
     * @param $counter
     * @return mixed
     */
    protected function formatCounterMessage($counter)
    {
        return str_replace('$nb', $counter[1], $counter[0]);
    }
}
