<?php

namespace HedgeBot\Plugins\Announcements;

/**
 * Class AnnouncementsEndpoint
 * @package HedgeBot\Plugins\Announcements
 */
class AnnouncementsEndpoint
{
    /** @var Announcements The plugin reference */
    protected $plugin;

    /**
     * AnnouncementsEndpoint constructor.
     * Initializes the endpoint with the plugin to use as data source.
     *
     * @param Announcements $plugin
     */
    public function __construct(Announcements $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Lists the messages that are registered on the bot.
     *
     * @return array The list of messages.
     */
    public function getMessages()
    {
        return $this->plugin->getMessages();
    }

    /**
     * Lists the channels that are registered on the bot.
     *
     * @return array The list of channels.
     */
    public function getIntervals()
    {
        return $this->plugin->getIntervals();
    }

    /**
     * Adds a message.
     * 
     * @see Announcements::addMessage()
     */
    public function addMessage($message, $channelNames)
    {
        return $this->plugin->addMessage($message, (array) $channelNames);
    }

    /**
     * Edits a message.
     * 
     * @see Announcements::editMessage()
     */
    public function editMessage($messageId, $newMessage, $channelNames)
    {
        return $this->plugin->editMessage($messageId, $newMessage, $channelNames);
    }

    /**
     * Deletes a message.
     * 
     * @see Announcements::deleteMessage()
     */
    public function deleteMessage($messageId)
    {
        return $this->plugin->deleteMessage($messageId);
    }
}
