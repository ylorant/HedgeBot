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
    private $channels = [];

    /**
     * Get messages saved
     *
     * @return array
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->messages as $key => $message) {
            $message['id'] = $key;
        }
        return $messages;
    }

    /**
     * Get channel entries with interval saved for this plugin
     *
     * @return array
     */
    public function getChannels(): array
    {
        $channels = [];
        foreach ($this->channels as $key => $channel) {
            $channel['id'] = $key;
        }
        return $channels;
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
    public function getChannelByName($channelName)
    {
        foreach ($this->channels as $key => $channel) {
            if ($channel['name'] == $channelName) {
                $channel['id'] = $key;
                return $channel;
            }
        }
        return false;
    }

    /**
     * @return bool|void
     */
    public function init()
    {
        if (!empty($this->data->messages)) {
            $this->messages = $this->data->messages->toArray();
        }
        if (!empty($this->data->channels)) {
            $this->channels = $this->data->channels->toArray();
        }
        foreach ($this->channels as &$channel) {
            $channel['lastMessageId'] = $channel['lastMessageId'] ?? 0;
            $channel['lastSentTime'] = $channel['lastSentTime'] ?? 0;
        }

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
        foreach ($this->channels as &$channel) {
            $channelName = $channel['name'];
            $interval = $channel['interval'];
            $lastMessageId = $channel['lastMessageId'];
            $messages = $this->getMessagesByChannel($channel['name']);

            if ($channel['lastSentTime'] + $interval < time()) {
                IRC::message($channelName, $messages[$lastMessageId]['message']);

                $lastMessageId++;
                if ($lastMessageId >= count($messages)) {
                    $lastMessageId = 0;
                }
                $channel['lastMessageId'] = $lastMessageId;
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
        $this->messages[] = ['message' => $message, 'channels' => $channelNames];
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
    public function addInterval(int $interval, $channelName)
    {
        HedgeBot::message("Saving interval to channel '" . $channelName . "' ...", [], E_DEBUG);
        $this->channels[] = [
            'name' => $channelName,
            'interval' => $interval,
            'lastSentTime' => '0',
            'lastMessageId' => '0'
        ];
        $this->data->channels = $this->channels;
    }

    /**
     * @param int $interval
     * @param int $channelId
     * @param string $channelName useful only for console output
     */
    public function editInterval(int $interval, $channelId, $channelName)
    {
        HedgeBot::message("Editing interval to channel '" . $channelName . "' ...", [], E_DEBUG);
        $this->channels[$channelId]['interval'] = $interval;
        $this->data->channels = $this->channels;
    }

    /**
     * @param CoreEvent $ev
     */
    public function CoreEventConfigUpdate(CoreEvent $ev)
    {
        $this->config = HedgeBot::getInstance()->config->get('plugin.Announcements');
    }
}
