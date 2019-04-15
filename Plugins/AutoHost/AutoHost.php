<?php

namespace HedgeBot\Plugins\AutoHost;

use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Events\CoreEvent;
use Transliterator;

/**
 * @plugin AutoHost
 *
 * Manages a list of channels to host automatically.
 * For each channel, an interval time is defined, in seconds.
 * When this interval time is passed for this channel, first channel to host assigned for this channel is hosted.
 * You must wait another interval to host the second channel (a channel is hosted only if is online).
 * It will loop on first channel to host when all channels has been hosted.
 */
class AutoHost extends PluginBase
{
    protected $hosts = [];

    const TRANSLIT_RULES = ':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;';

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
        $hostUpdated = false;

        foreach ($this->hosts as &$host) {
            // If there is no hosted channel defined in the host channel, skip it
            if (empty($host['hostedChannels']) || $host['lastHostTime'] + $host['time'] > time()) {
                continue;
            }

            // Get the channel that will get hosted depending on the previous hosts stats
            $hostTargetName = $this->computeWeights($host['hostedChannels']);
            $hostTarget = $host['hostedChannels'][$hostTargetName];

            // Check that the time between 2 hosts has elapsed to host the channel
            if ($hostTarget) {
                $streamInfo = Twitch::getClient()->streams->info($hostTarget['channel']);

                if ($streamInfo != null) {
                    $streamTitleValid = $this->checkTitleValidity($streamInfo->channel->status, $host);

                    if ($streamTitleValid) {
                        if ($host['lastChannel'] != $hostTarget['channel']) {
                            IRC::message($host['channel'], '/host ' . $hostTarget['channel']);
                            $hostUpdated = true;

                            HedgeBot::message('Sent auto host request for "$0" -> "$1".', [$host['channel'], $hostTargetName], E_DEBUG);
                        } else {
                            HedgeBot::message('Keeping current hosting "$0" -> "$1"', [$host['channel'], $hostTargetName], E_DEBUG);
                        }

                        // Even if the channel wasn't actually hosted, set the time to reset the timer
                        $host['lastHostTime'] = time();
                    }
                }

                // Even if the host operation failed because we're already hosting that channel or it is offline,
                // count it as hosted because it will avoid a deadlock in case of balancing favorizing that channel.
                $host['lastChannel'] = $hostTarget['channel'];
                $host['hostedChannels'][$hostTarget['channel']]['totalHosted']++;
            }
        }

        // Save hosts if at least one has been updated
        if ($hostUpdated) {
            $this->saveData();
        }
    }

    /**
     * Returns the channel to host for a given hosting channel name.
     * Choice is made with weight system (Depends of priority to host and number of times this channel was hosted)
     *
     * NB : Blacklist of words on title's stream and Whitelist of words on title's stream are used
     * after verifying if channel chosen here is online
     *
     * @param string $channelName The hosting channel info.
     * @return array|boolean
     */
    public function getChannelToHost($channelName)
    {
        if (!isset($this->hosts[$channelName])) {
            return false;
        }

        $hostingChannelInfo = $this->hosts[$channelName];

        if (array_key_exists('hostedChannels', $hostingChannelInfo) && !empty($hostingChannelInfo['hostedChannels'])) {
            $hostTarget = $this->computeWeights($hostingChannelInfo['hostedChannels']);
            return $hostingChannelInfo['hostedChannels'][$hostTarget];
        } else {
            return false;
        }
    }

    /**
     * Check if title of streams contains at least one word from whitelist and blacklist
     * Returns TRUE if we can host it
     * i.e. (One word or more in whitelist OR whitelist empty) AND (no word in blacklist OR blacklist empty)
     *
     * @param string $title The stream title to check the validity of.
     * @param array $host The hoster info, namely the whitelist and the blacklist.
     * @return bool True if it is a valid title, false if it isn't.
     */
    protected function checkTitleValidity($title, array $host)
    {
        $whiteWordFound = true;
        
        $transliterator = Transliterator::createFromRules(self::TRANSLIT_RULES, Transliterator::FORWARD);
        $title = strtolower($transliterator->transliterate($title));

        if (!empty($host['titleWhiteList'])) {
            $whiteWordFound = false;
            foreach ($host['titleWhiteList'] as $word) {
                if (strpos($title, $word) !== false) {
                    $whiteWordFound = true;
                }
            }
        }

        if (!empty($host['titleBlackList'])) {
            foreach ($host['titleBlackList'] as $word) {
                if (strpos($title, $word) !== false) {
                    return false;
                }
            }
        }

        return $whiteWordFound;
    }

    /**
     * Return a channel name depending on its priority and the number of times this channel has already been hosted.
     *
     * @param array $hostedChannels The list of channels to sort through.
     * @return mixed
     */
    protected function computeWeights($hostedChannels)
    {
        $hostStatsPerChannel = array_column($hostedChannels, 'totalHosted');
        $totalHosts = array_sum($hostStatsPerChannel);

        // Compute each host ratio (as a float)
        $hostsTargetRatio = [];
        foreach ($hostedChannels as $channelName => $channelData) {
            $hostActualRatio = 0;

            if ($totalHosts > 0) {
                $hostActualRatio = $channelData['totalHosted'] / $totalHosts;
            }

            $hostsTargetRatio[$channelName] = ($channelData['priority'] - $hostActualRatio) + 1;
        }

        // Ordering the channels by their ratio, in the reverse order (the channel most under its host ratio target first)
        arsort($hostsTargetRatio);
        $orderedChannels = array_keys($hostsTargetRatio);

        // Picking the most-likely to host channel
        return reset($orderedChannels);
    }

    /**
     * Set hosting basic data for one channel
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
                'lastChannel' => '',
                'titleWhiteList' => [],
                'titleBlackList' => [],
            ];
        }

        $this->hosts[$channelName]['time'] = $time;
        $this->saveData();

        return true;
    }

    /**
     * Get informations about a specific host channel
     *
     * @param string $hostName
     *
     * @return array|bool
     */
    public function getHost($hostName)
    {
        if (!isset($this->hosts[$hostName])) {
            return false;
        }

        return $this->hosts[$hostName];
    }

    /**
     * Add one channel to host on one hosting channel, including priority
     *
     * @param string $hostName hosting channel name
     * @param string $channelName channel to host
     * @param float $priority priority % for hosting choice
     *
     * @return boolean
     */
    public function addHostedChannel($hostName, $channelName, $priority)
    {
        if (!isset($this->hosts[$hostName])) {
            return false;
        }

        $this->hosts[$hostName]['hostedChannels'][$channelName] = [
            'channel' => $channelName,
            'priority' => (float)$priority,
            'totalHosted' => 0
        ];
        $this->saveData();

        return true;
    }

    /**
     * Remove one channel to host on one hosting channel
     *
     * @param string $hostName
     * @param string $channelName
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

        $this->saveData();
        return true;
    }

    /**
     * Add a word into a defined filter list for one host channel
     *
     * @param string $hostName
     * @param int $typeFilter
     * @param string $word
     *
     * @return boolean
     */
    public function addFilterList($hostName, $typeFilter, $word)
    {
        if (!isset($this->hosts[$hostName])) {
            return false;
        }

        if ($typeFilter == 1) {
            $filterListName = 'titleBlackList';
        } elseif ($typeFilter == 2) {
            $filterListName = 'titleWhiteList';
        } else {
            return false;
        }

        $transliterator = Transliterator::createFromRules(self::TRANSLIT_RULES, Transliterator::FORWARD);
        $word = strtolower($transliterator->transliterate($word));

        if (!in_array($word, $this->hosts[$hostName][$filterListName], true)) {
            array_push($this->hosts[$hostName][$filterListName], $word);
        }

        $this->saveData();
        return true;
    }

    /**
     * Remove a word into a defined filter list for one host channel
     *
     * @param string $hostName
     * @param int $typeFilter
     * @param string $word
     *
     * @return boolean
     */
    public function removeFilterList($hostName, $typeFilter, $word)
    {
        if (!isset($this->hosts[$hostName])) {
            return false;
        }

        if ($typeFilter == 1) {
            $filterListName = 'titleBlackList';
        } elseif ($typeFilter == 2) {
            $filterListName = 'titleWhiteList';
        } else {
            return false;
        }

        if (array_key_exists($typeFilter, $this->hosts[$hostName])) {
            $transliterator = Transliterator::createFromRules(self::TRANSLIT_RULES, Transliterator::FORWARD);
            $word = strtolower($transliterator->transliterate($word));

            foreach (array_keys($this->hosts[$hostName][$filterListName], $word, true) as $key) {
                unset($this->hosts[$hostName][$filterListName][$key]);
            }

            $this->saveData();
            return true;
        }
        return false;
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

    /**
     * Saves data into the storage.
     */
    protected function saveData()
    {
        HedgeBot::message("Saving hosts list...", [], E_DEBUG);
        $this->data->hosts = $this->hosts;
    }
}