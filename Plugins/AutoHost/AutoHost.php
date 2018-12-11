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
            $lastChannelIndex = 0;
            $channelsToHost = $host['hostedChannels'];

            // Check that the time between 2 hosts has elapsed to host the channel
            if ($host['lastHostTime'] + $host['time'] < time()) {
                $lastChannelIndex++;
                if ($lastChannelIndex >= count($channelsToHost)) {
                    $lastChannelIndex = 0;
                }

                foreach ($channelsToHost as &$channelToHost) {
                    $streamInfo = Twitch::getClient()->streams->info($channelToHost);
                    if ($streamInfo != null) {
                        IRC::message($host['channel'], '/host ' . $channelToHost);

                        $host['lastHostTime'] = time();
                        $host['lastChannelIndex'] = $lastChannelIndex;
                        $hostUpdated = true;
                        HedgeBot::message('Sent auto host "$0".', [$host['channel']], E_DEBUG);
                        break;
                    }
                }
            }
        }

        // Save intervals if at least one has been updated
        if ($hostUpdated) {
            $this->data->hosts = $this->hosts;
        }
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
            $host['lastChannelIndex'] = $host['lastChannelIndex'] ?? 0;
            $host['lastHostTime'] = $host['lastHostTime'] ?? 0;
        }
    }
}