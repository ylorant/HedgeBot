<?php

namespace HedgeBot\Plugins\AutoHost;

use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\API\Store;

/**
 * @plugin AutoHost
 *
 * Manage a list of channels to autohost.
 * For each channel, an interval time is defined, in seconds.
 * When this interval time is passed for this channel, first channel to host assigned for this channel is hosted.
 * You must wait another interval to host the second channel (a channel is hosted only if is online).
 * It will loop on first channel to host when all channels has been hosted.
 */
class AutoHost extends PluginBase
{
    private $hosts = [];

    /**
     * @return bool|void
     */
    public function init()
    {
        $this->loadData();

        $pluginManager = Plugin::getManager();
        $pluginManager->addRoutine($this, 'RoutineSendAutoHost');

        // Don't load the API endpoint if we're not on the main environment
        if (ENV == "main") {
            Tikal::addEndpoint('/plugin/autohost', new AutoHostEndpoint($this));
        }
    }

    /**
     * AutoHost main routine
     */
    public function RoutineSendAutoHost()
    {
        if (empty($this->hosts)) {
            return;
        }

        $hostUpdated = false;

        foreach ($this->hosts as &$host) {
            $channelToHost = $this->getChannelToHost($host);

            // Check that the time between 2 hosts has elapsed to host the channel
            if ($channelToHost && $host['lastHostTime'] + $host['time'] < time()) {
                $streamInfo = Twitch::getClient()->streams->info($channelToHost['channel']);
                if ($streamInfo != null && $host['lastChannel'] != $channelToHost['channel']) {
                    IRC::message($host['channel'], '/host ' . $channelToHost['channel']);

                    $host['lastHostTime'] = time();
                    $host['lastChannel'] = $channelToHost['channel'];
                    $hostUpdated = true;
                    HedgeBot::message('Sent auto host "$0".', [$host['channel']], E_DEBUG);
                }
                // Need to force it locally to pass through offline channels
                // (But don't want to save it)
                $host['lastChannel'] = $channelToHost['channel'];
            }
        }

        // Save host if at least one has been updated
        if ($hostUpdated) {
            $this->data->hosts = $this->hosts;
        }
    }

    /**
     * Return channel to host
     * Depends of precedent channel hosted and priority
     *
     * @param array $host
     * @param integer $priority
     * @return array|boolean
     */
    public function getChannelToHost($host, $priority = 0)
    {
            $lastChannel = $host['lastChannel'];
            $channels = $this->getChannelsToHost($host);

            if ($channels) {
                $newChannelIndex = array_search($lastChannel, $channels) + 1;

                if ($lastChannel == '' || $newChannelIndex >= count($channels)) {
                    $newChannelIndex = 0;
                }

                return $host['hostedChannels'][$newChannelIndex];
            } else {
                return false;
            }
    }

    /**
     * Return channels to try to host
     *
     * @param array $host
     * @return array|boolean
     */
    public function getChannelsToHost($host)
    {
        if (array_key_exists('hostedChannels', $host) && $host['hostedChannels'] != '') {
            return array_column($host['hostedChannels'], 'channel');
        } else {
            return false;
        }
    }

    /**
     * Set hosting basic informations for one channel
     *
     * @param string $channelName The host channel
     * @param int $time Time interval between each hosting. 600 by default (minimal value allowed by Twitch)
     *
     * @return bool True.
     */
    public function setHost($channelName, $time = 600)
    {
        HedgeBot::message("Saving hosting infos for channel '" . $channelName . "' ...", [], E_DEBUG);

        if (!isset($this->hosts[$channelName])) {
            $this->hosts[$channelName] = [
                'channel' => $channelName,
                'time' => $time,
                'lastHostTime' => 0,
                'lastChannel' => ''
            ];
        }

        $this->hosts[$channelName]['time'] = $time;
        $this->data->hosts = $this->hosts;

        return true;
    }

    /**
     * @param CoreEvent $ev
     */
    public function CoreEventConfigUpdate(CoreEvent $ev)
    {
        $this->config = HedgeBot::getInstance()->config->get('plugin.AutoHost');
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
        if (!empty($this->data->hosts)) {
            $this->hosts = $this->data->hosts->toArray();
        }
        foreach ($this->hosts as &$host) {
            $host['lastChannel'] = '';
            $host['lastHostTime'] = $host['lastHostTime'] ?? 0;
        }
    }
}