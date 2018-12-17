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
                    IRC::message($host['channel'], 'host ' . $channelToHost['channel']);

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
     * Choice is made by including :
     * - Weight (Depends of priority to host and number of times this channel was hosted)
     * - Blacklist of words on title's stream (@TODO)
     * - Whitelist of words on title's stream (@TODO)
     *
     * @param array $host
     * @return array|boolean
     */
    public function getChannelToHost($host)
    {
        if (array_key_exists('hostedChannels', $host) && !empty($host['hostedChannels'])) {
            $channelName = $this->computeWeights($host['hostedChannels']);
            $channel = array_filter($host['hostedChannels'], function ($channel) use ($channelName) {
                return ($channel['channel'] == $channelName);
            });
            return array_shift($channel);
        } else {
            return false;
        }
    }

    /**
     * Return a channel name depends of priority to host and number of times this channel was hosted
     *
     * @param array $hostedChannels
     * @return mixed
     */
    protected function computeWeights($hostedChannels)
    {
        $hostStatsPerChannel = array_column($hostedChannels, 'totalHosted');
        $totalHosts = count($hostStatsPerChannel);

        // Compute each host ratio (as a float)
        $hostsTargetRatio = [];
        foreach($hostedChannels as $channel) {
            $hostActualRatio = $channel['totalHosted'] / $totalHosts;
            $hostsTargetRatio[$channel['channel']] = ($channel['priority'] - $hostActualRatio) + 1;
        }

        arsort($hostsTargetRatio);
        $orderedChannels = array_keys($hostsTargetRatio);

        return reset($orderedChannels);
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
     * Add one channel to host on one hosting channel, including priority
     *
     * @param string $hostName hosting channel name
     * @param array $channelName channel to host
     * @param float $priority priority % for hosting choice
     *
     * @return boolean
     */
    public function addHostedChannel($hostName, $channelName, $priority)
    {
        if (!isset($this->hosts[$hostName])) {
            return false;
        }

        $this->hosts[$hostName]['hostedChannels'][] = [
            'channel' => $channelName,
            'priority' => (float)$priority,
            'totalHosted' => 0
            ];
        $this->data->hosts = $this->hosts;

        return true;
    }

    /**
     * Remove one channel to host on one hosting channel
     *
     * @param $hostName
     * @param $channelName
     * @return boolean
     */
    public function removeHostedChannel($hostName, $channelName)
    {
        if (!isset($this->hosts[$hostName])) {
            return false;
        }

        $arrayIndex = null;
        foreach ($this->hosts[$hostName]['hostedChannels'] as $key => $channel) {
            if ($channel['channel'] == $channelName) {
                $arrayIndex = $key;
            }
        }
        if (is_null($arrayIndex)) {
            return false;
        }

        unset($this->hosts[$hostName]['hostedChannels'][$arrayIndex]);
        $this->hosts[$hostName]['hostedChannels'] = array_values($this->hosts[$hostName]['hostedChannels']);

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

    /**
     * @param CoreEvent $ev
     */
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