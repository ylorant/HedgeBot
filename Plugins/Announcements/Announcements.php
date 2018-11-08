<?php

namespace HedgeBot\Plugins\Announcements;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Events\ServerEvent;

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

        $intervalUpdated = false;
        
        foreach ($this->intervals as &$interval) {
            $lastMessageIndex = $interval['lastMessageIndex'];
            $messages = $this->getMessagesByChannel($interval['channel']);
            $messageKeys = array_keys($messages);

            // Skip the channel if there is no messages available for it
            if(count($messages) == 0) {
                continue;
            }

            // Check that the time/message interval between 2 sends has elapsed to send the message
            if (
                $interval['lastSentTime'] + $interval['time'] < time() &&
                $interval['currentMessageCount'] >= $interval['messages']
            ) {
                IRC::message($interval['channel'], $messages[$messageKeys[$lastMessageIndex]]['message']);

                $lastMessageIndex++;
                if ($lastMessageIndex >= count($messages)) {
                    $lastMessageIndex = 0;
                }
                $interval['lastMessageIndex'] = $lastMessageIndex;
                $interval['lastSentTime'] = time();
                $interval['currentMessageCount'] = 0;
                $intervalUpdated = true;

                HedgeBot::message('Sent auto message "$0".', [$interval['channel']], E_DEBUG);
            }
        }

        // Save intervals if at least one has been updated
        if ($intervalUpdated) {
            $this->data->intervals = $this->intervals;
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
     * 
     * @return string The created message's ID.
     */
    public function addMessage($message, $channelNames)
    {
        $newId = uniqid(true);
        $this->messages[$newId] = ['id' => $newId, 'message' => $message, 'channels' => $channelNames];
        $this->data->messages = $this->messages;

        return $newId;
    }

    /**
     * Edits a message.
     * 
     * @param string $messageId The ID of the message to edit.
     * @param string $newMessage The new message text.
     * @param array $channelNames The new channel list on which the message is enabled.
     * 
     * @return bool True if the message was edited successfully, false if not (mainly the message doesn't exist).
     */
    public function editMessage($messageId, $newMessage, array $channelNames)
    {
        if(!isset($this->messages[$messageId])) {
            return false;
        }
        
        $this->messages[$messageId] = ['id' => $messageId, 'message' => $newMessage, 'channels' => $channelNames];
        $this->data->messages = $this->messages;

        return true;
    }

    /**
     * @param string $messageId
     */
    public function deleteMessage($messageId)
    {
        if(!isset($this->messages[$messageId])) {
            return false;
        }

        unset($this->messages[$messageId]);
        $this->data->messages = $this->messages;

        return true;
    }

    /**
     * Set the interval of time and/or messages between each message on a channel.
     *
     * @param string $channelName The channel to set the interval of.
     * @param int $time Time interval between each send, in seconds.
     * @param int $messages Message count between each send.
     * 
     * @return bool True.
     */
    public function setInterval($channelName, $time, $messages)
    {
        HedgeBot::message("Saving interval to channel '" . $channelName . "' ...", [], E_DEBUG);
        
        if (!isset($this->intervals[$channelName])) {
            $this->intervals[$channelName] = [
                'channel' => $channelName,
                'time' => 0,
                'messages' => 0,
                'currentMessageCount' => 0,
                'lastSentTime' => 0,
                'lastMessageIndex' => 0
            ];
        }
        
        $this->intervals[$channelName]['time'] = $time;
        $this->intervals[$channelName]['messages'] = $messages;
        $this->data->intervals = $this->intervals;

        return true;
    }

    /**
     * Removes a set interval configuration on a channel.
     * 
     * @param string $channelName The name of the channel o remove the interval of.
     * 
     * @return bool True if the interval has been removed, false if not.
     */
    public function removeInterval($channelName)
    {
        if(!isset($this->intervals[$channelName])) {
            return false;
        }

        unset($this->intervals[$channelName]);
        $this->data->intervals = $this->intervals;
        return true;
    }

    /**
     * Server event: chat message received, we increase the messsage counter of the specified channel.
     * 
     * @param ServerEvent $ev
     */
    public function ServerEventPrivmsg(ServerEvent $ev)
    {
        if(isset($this->intervals[$ev->channel])) {
            $this->intervals[$ev->channel]['currentMessageCount']++;
        }
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
