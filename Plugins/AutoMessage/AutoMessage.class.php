<?php
namespace HedgeBot\Plugins\AutoMessage;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\API\Plugin as PluginAPI;
use HedgeBot\Core\API\IRC;

class AutoMessage extends Plugin
{
    private $messages = array();
    private $nextSendQueue = array();

    public function init()
    {
        $this->reloadConfig();

        $pluginManager = PluginAPI::getManager();
        $pluginManager->addRoutine($this, 'RoutineSendMessages');
        $pluginManager->addRoutine($this, 'RoutinePopulateQueue', 30);
    }

    public function RoutineSendMessages()
    {
        $time = time();
        if(!empty($this->nextSendQueue[$time]))
        {
            foreach($this->nextSendQueue[$time] as $message)
            {
                list($channel, $name) = explode('.', $message);
                IRC::message($channel, $this->messages[$channel][$name]->message);
                $this->messages[$channel][$name]->lastSend = $time;
                    HedgeBot::message("Sent $0.", [$name]);
            }

            unset($this->nextSendQueue[$time]);
        }
    }

    public function RoutinePopulateQueue()
    {
        HedgeBot::message("Populating autosend queue...");

        $threshold = time() + 30;

        foreach($this->messages as $channel => $messages)
        {
            foreach($messages as $messageName => $message)
            {
                $sendTime = $message->lastSend + $message->freq;
                if($sendTime <= $threshold)
                {
                    if(empty($this->nextSendQueue[$sendTime]) || !in_array($channel. '.'. $messageName, $this->nextSendQueue[$sendTime]))
                    {
                        if(empty($this->nextSendQueue[$sendTime]))
                            $this->nextSendQueue[$sendTime] = array();

                        $this->nextSendQueue[$sendTime][] = $channel. '.'. $messageName;
                    }
                }
            }
        }
    }

    public function SystemEventConfigUpdate()
    {
		$this->config = HedgeBot::getInstance()->config->get('plugin.Currency');
		$this->reloadConfig();
    }

    public function reloadConfig()
    {
        foreach($this->config as $channel => $messages)
        {
            if(is_array($messages))
            {
                $this->messages[$channel] = array();
                foreach($messages as $name => $message)
                {
                    if(is_array($message) && !empty($message['frequency']) && !empty($message['message']))
                    {
                        // If the message doesn't exist yet in the loaded messages, then init the data structure and i
                        if(empty($this->messages[$name]))
                            $this->messages[$channel][$name] = (object) ['lastSend' => time()];

                        $this->messages[$channel][$name]->message = $message['message'];
                        $this->messages[$channel][$name]->freq = $this->parseTimeInterval($message['frequency']);
                    }
                }
            }
        }
    }

    private function parseTimeInterval($time)
    {
        $seconds = 0;

        $multiplier = 0;
        $time = str_split($time);
        foreach($time as $chr)
        {
            if(is_numeric($chr))
                $multiplier = $multiplier * 10 + $chr;
            else
            {
                switch($chr)
                {
                    case 'h': $seconds += $multiplier * 3600; break;
                    case 'm': $seconds += $multiplier * 60; break;
                    case 's': $seconds += $multiplier; break;
                    default:
                        return null;
                }

                $multiplier = 0;
            }
        }

        $seconds += $multiplier;
        return $seconds;
    }
}
