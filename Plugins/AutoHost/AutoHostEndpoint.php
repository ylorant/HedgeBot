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
     * get hosting basic informations for one channel
     *
     * @see AutoHost::setHost()
     * @param string $channelName
     * @return array|bool
     */
    public function getHost($channelName)
    {
        return $this->plugin->getHost($channelName);
    }

    /**
     * get hosting basic informations for all channels
     *
     * @return array|bool
     */
    public function getHosts()
    {
        return $this->plugin->getHosts();
    }

    /**
     * Edit a host channel configuration, including whitelist and blacklist words
     *
     * @param string $hostName
     * @param boolean $enabled
     * @param integer $timeInterval
     * @param array $whiteList
     * @param array $blackList
     * @return bool
     */
    public function editHostConfiguration($hostName, $enabled, $timeInterval, $whiteList, $blackList)
    {
        return $this->plugin->editHostConfiguration($hostName, $enabled, $timeInterval, $whiteList, $blackList);
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
     * Add one channel to host
     *
     * @see AutoHost::addHostedChannel()
     * @param string $hostName
     * @param string $channelName
     * @param float $priority
     * @param bool $enabled
     * @return bool
     */
    public function editHostedChannel($hostName, $channelName, $priority, $enabled)
    {
        return $this->plugin->editHostedChannel($hostName, $channelName, $priority, $enabled);
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

    /**
     * Add a word into a defined filter list for one host channel
     *
     * @see AutoHost::addFilterList()
     * @param string $hostName
     * @param int $typeFilter
     * @param string $word
     * @return bool
     */
    public function addFilterList($hostName, $typeFilter, $word)
    {
        return $this->plugin->addFilterList($hostName, $typeFilter, $word);
    }

    /**
     * Remove a word into a defined filter list for one host channel
     *
     * @see AutoHost::removeFilterList()
     * @param string $hostName
     * @param int $typeFilter
     * @param string $word
     * @return bool
     */
    public function removeFilterList($hostName, $typeFilter, $word)
    {
        return $this->plugin->removeFilterList($hostName, $typeFilter, $word);
    }
}
