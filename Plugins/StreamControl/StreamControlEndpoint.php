<?php
namespace HedgeBot\Plugins\StreamControl;

class StreamControlEndpoint
{
    /** @var StreamControl The plugin reference */
    protected $plugin;

    /**
     * StreamControlEndpoint constructor.
     * Initializes the endpoint with the plugin to use as source.
     *
     * @param StreamControl $plugin
     */
    public function __construct(StreamControl $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the stream info for the given channel.
     * 
     * @param string $channel The channel to get the stream info of.
     * @return array The stream info.
     */
    public function getChannelInfo(string $channel)
    {
        return $this->plugin->getChannelInfo($channel);
    }

    /**
     * Sets the stream info for the given channel.
     *
     * @param string $channel The channel to update the info of.
     * @param string $title The new stream title.
     * @param string $category The new stream category/game.
     * @return array The new channel info.
     */
    public function setChannelInfo(string $channel, string $title, string $category)
    {
        return $this->plugin->setChannelInfo($channel, $title, $category);
    }

    /**
     * Starts ads on a given channel, for the given duration
     * @param string $channel The channel to start ads on.
     * @param int $duration The ads duration, in seconds. Can be one of 30, 60, 90, 120, 150, 180.
     * @return bool True if the ads started, false if a problem occured.
     */
    public function startAds(string $channel, int $duration)
    {
        return $this->plugin->startAds($channel, $duration);
    }
}