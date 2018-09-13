<?php

namespace HedgeBot\Plugins\Announcements;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Events\CoreEvent;

/**
 * Class Announcements
 * @package HedgeBot\Plugins\Announcements
 */
class Announcements extends PluginBase
{
    private $messages = [];
    private $interval = [];

    /**
     * Get messages saved
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get channel entries with interval saved for this plugin
     *
     * @return array
     */
    public function getIntervals(): array
    {
        return $this->intervals;
    }

    /**
     * Get all messages associated to a specific channel
     *
     * @param string $channelName
     * @return array
     */
    public function getMessagesByChannel($channelName)
    {
        return array_filter($this->messages, function ($message) use ($channelName) {
            return in_array($channelName, $message['channels']);
        });
    }

    /**
     * Get a channel entry by channel name.
     * Useful for add/edit/delete interval
     *
     * @param $channelName
     * @return array|bool
     */
    public function getIntervalByChannel($channelName)
    {
        if(isset($this->intervals[$channelName])) {
            return $this->intervals[$channelName];
        }

        return false;
    }

    /**
     * @return bool|void
     */
    public function init()
    {
        $this->loadData();

        $pluginManager = Plugin::getManager();
        $pluginManager->addRoutine($this, 'RoutineSendAnnouncements');

        // Don't load the API endpoint if we're not on the main environment
        if (ENV == "main") {
            Tikal::addEndpoint('/plugin/announcements', new AnnouncementsEndpoint($this));
        }
    }

    /**
     *
     */
    public function RoutineSendAnnouncements()
    {
        foreach ($this->intervals as &$interval) {
            $channelName = $interval['channel'];
            $interval = $interval['interval'];
            $lastMessageIndex = $interval['lastMessageIndex'];
            $messages = $this->getMessagesByChannel($interval['channel']);

            if ($channel['lastSentTime'] + $interval < time()) {
                IRC::message($channelName, $messages[$lastMessageIndex]['message']);

                $lastMessageIndex++;
                if ($lastMessageIndex >= count($messages)) {
                    $lastMessageIndex = 0;
                }
                $channel['lastMessageIndex'] = $lastMessageIndex;
                $channel['lastSentTime'] = time();

                HedgeBot::message('Sent auto message "$0".', [$channelName], E_DEBUG);
            }
        }
    }

    /**
     * Get all messages associated to a specific channel
     *
     * @param string $channelName
     * @return array
     */
    protected function getChannelMessages($channelName)
    {
        return array_filter($this->messages, function ($message) use ($channelName) {
            return in_array($channelName, $message['channels']);
        });
    }

    /**
     * Add a message and link it to one or many channels
     * (which had an interval time for messages display)
     *
     * @param string $message text can contain Markdown
     * @param array $channelNames list of channels to apply this message
     */
    public function addMessage($message, $channelNames)
    {
        HedgeBot::message("Saving message ...", [], E_DEBUG);
        $this->messages[] = ['id' => uniqid(true), 'message' => $message, 'channels' => $channelNames];
        $this->data->messages = $this->messages;
    }

    /**
     * @param string $messageId
     * @param string $newMessage
     * @param array $channelNames
     */
    public function editMessage($messageId, $newMessage, $channelNames)
    {
        HedgeBot::message("Editing message ...", [], E_DEBUG);
    }

    /**
     * Add interval time (in seconds) on channel
     * to display, at each interval time, a message
     *
     * @param int $interval time in seconds
     * @param string $channelName
     */
    public function setInterval($channelName, int $interval)
    {
        HedgeBot::message("Saving interval to channel '" . $channelName . "' ...", [], E_DEBUG);
        
        if(!isset($this->intervals[$channelName])) {
            $this->intervals[$channelName] = [
                'channel' => $channelName,
                'interval' => 0,
                'lastSentTime' => 0,
                'lastMessageId' => 0
            ];
        }
        
        $this->intervals[$channelName]['interval'] = $interval;
        $this->data->channels = $this->intervals;
    }

    /**
     * @param CoreEvent $ev
     */
    public function CoreEventConfigUpdate(CoreEvent $ev)
    {
        $this->config = HedgeBot::getInstance()->config->get('plugin.Announcements');
    }

    public function CoreEventDataUpdate(CoreEvent $ev)
    {
        $this->loadData();
    }

    /**
     * Loads data from the storage.
     */
    protected function loadData()
    {
        if (!empty($this->data->messages)) {
            $this->messages = $this->data->messages->toArray();
        }
        if (!empty($this->data->channels)) {
            $this->intervals = $this->data->channels->toArray();
        }
        foreach ($this->intervals as &$interval) {
            $interval['lastMessageIndex'] = $interval['lastMessageIndex'] ?? 0;
            $interval['lastSentTime'] = $interval['lastSentTime'] ?? 0;
        }
    }
}
