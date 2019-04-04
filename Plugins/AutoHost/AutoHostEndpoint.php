<?php
namespace HedgeBot\Plugins\AutoHost;

/**
 * Class AutoHostEndpoint
 * @package HedgeBot\Plugins\AutoHost
 */
class AutoHostEndpoint
{
    /** @var AutoHost The plugin reference */
    protected $plugin;

    /**
     * AutoHostEndpoint constructor.
     * Initializes the endpoint with the plugin to use as data source.
     *
     * @param AutoHost $plugin
     */
    public function __construct(AutoHost $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Set hosting basic informations for one channel
     *
     * @see AutoHost::setHost()
     * @param $channelName
     * @param int $time Time interval between each hosting. 600 by default (minimal value allowed by Twitch)
     * @return bool
     */
    public function setHost($channelName, $time)
    {
        return $this->plugin->setHost($channelName, $time);
    }

    /**
     * Add one channel to host
     *
     * @see AutoHost::addHostedChannel()
     * @param string $hostName
     * @param string $channelName
     * @param float $priority
     * @return bool
     */
    public function addHostedChannel($hostName, $channelName, $priority)
    {
        return $this->plugin->addHostedChannel($hostName, $channelName, $priority);
    }

    /**
     * Delete one channel to host
     *
     * @see AutoHost::removeHostedChannel()
     * @param $hostName
     * @param $channelName
     * @return bool
     */
    public function removeHostedChannel($hostName, $channelName)
    {
        return $this->plugin->removeHostedChannel($hostName, $channelName);
    }
}
