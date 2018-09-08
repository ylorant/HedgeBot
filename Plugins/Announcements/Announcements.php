<?php

namespace HedgeBot\Plugins\Announcements;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
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
     * @return array
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->messages as $key => $message) {
            $message[$key]['id'] = $key;
        }
        return $messages;
    }

    /**
     * @return array
     */
    public function getChannels(): array
    {
        $channels = [];
        foreach ($this->channels as $key => $channel) {
            $channel[$key]['id'] = $key;
        }
        return $channels;
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
            $messages = $this->getChannelMessages($channel['name']);

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
     * @param string $message text can contain Markdown
     * @param array $channelNames list of channels to apply this message
     */
    public function addMessage($message, $channelNames)
    {
        HedgeBot::message("Saving message...", [], E_DEBUG);
        $this->messages[] = ['message' => $message, 'channels' => $channelNames];
        $this->data->messages = $this->messages;
    }

    /**
     * @param string $channelName
     * @param int $interval time in seconds
     */
    public function addChannel($channelName, $interval)
    {
        HedgeBot::message("Saving channel...", [], E_DEBUG);
        $this->channels[] = [
            'name' => $channelName,
            'interval' => $interval,
            'lastSentTime' => '0',
            'lastMessageId' => '0'
        ];
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
