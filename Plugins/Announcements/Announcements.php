<?php

namespace HedgeBot\Plugins\Announcements;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Events\CoreEvent;

/**
 * @plugin Announcements
 *
 * Manage a messages list. Each message can be linked to one or many channels.
 * For each channel, an interval time is defined, in seconds.
 * When this interval time is passed for this channel, first message assigned for this channel is displayed.
 * You must wait another interval to see the second channel.
 * It will loop on first message when all messages has been displayed
 */
class Announcements extends PluginBase
{
    private $messages = [];
    private $intervals = [];

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
        foreach ($this->messages as $key => $message) {
            return array_filter($this->messages, function ($message) use ($channelName) {
                return in_array($channelName, $message['channels']);
            });
        }
    }

    /**
     * Get message associated with its id
     *
     * @param string $messageId
     * @return array
     */
    public function getMessageById($messageId)
    {
        if(isset($this->messages[$messageId])) {
            return $this->messages[$messageId];
        }

        return null;
    }

    /**
     * Get a interval entry by channel name.
     * Useful for add/edit/delete interval
     *
     * @param $channelName
     * @return array|bool
     */
    public function getIntervalByChannel($channelName)
    {
        if (isset($this->intervals[$channelName])) {
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
        if (empty($this->intervals)) {
            return;
        }
        
        foreach ($this->intervals as &$interval) {
            $lastMessageIndex = $interval['lastMessageIndex'];
            $messages = $this->getMessagesByChannel($interval['channel']);
            $messageKeys = array_keys($messages);

            // Skip the channel if there is no messages available for it
            if(count($messages) == 0) {
                continue;
            }

            // Check that the time interval between 2 sends has elapsed to send the file
            if ($interval['lastSentTime'] + $interval['time'] < time()) {
                IRC::message($interval['channel'], $messages[$messageKeys[$lastMessageIndex]]['message']);

                $lastMessageIndex++;
                if ($lastMessageIndex >= count($messages)) {
                    $lastMessageIndex = 0;
                }
                $interval['lastMessageIndex'] = $lastMessageIndex;
                $interval['lastSentTime'] = time();

                HedgeBot::message('Sent auto message "$0".', [$interval['channel']], E_DEBUG);
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
        $newId = uniqid(true);
        $this->messages[$newId] = ['id' => $newId, 'message' => $message, 'channels' => $channelNames];
        $this->data->messages = $this->messages;
    }

    /**
     * @param string $messageId
     * @param string $newMessage
     */
    public function editMessage($messageId, $newMessage)
    {
        HedgeBot::message("Editing message ...", [], E_DEBUG);

        $this->messages[$messageId] = ['message' => $newMessage];
        $this->data->messages = $this->messages;
    }

    /**
     * @param string $messageId
     */
    public function deleteMessage($messageId)
    {
        HedgeBot::message("Deleting message ...", [], E_DEBUG);

        unset($this->messages[$messageId]);
        $this->data->messages = $this->messages;
    }

    /**
     * Add interval time (in seconds) on channel
     * to display, at each interval time, a message
     *
     * @param int $time time in seconds
     * @param string $channelName
     */
    public function setInterval($channelName, int $time)
    {
        HedgeBot::message("Saving interval to channel '" . $channelName . "' ...", [], E_DEBUG);
        
        if (!isset($this->intervals[$channelName])) {
            $this->intervals[$channelName] = [
                'channel' => $channelName,
                'time' => 0,
                'lastSentTime' => 0,
                'lastMessageIndex' => 0
            ];
        }
        
        $this->intervals[$channelName]['time'] = $time;
        $this->data->intervals = $this->intervals;
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
        if (!empty($this->data->intervals)) {
            $this->intervals = $this->data->intervals->toArray();
        }
        foreach ($this->intervals as &$interval) {
            $interval['lastMessageIndex'] = $interval['lastMessageIndex'] ?? 0;
            $interval['lastSentTime'] = $interval['lastSentTime'] ?? 0;
        }
    }
}
