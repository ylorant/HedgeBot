<?php
namespace HedgeBot\Plugins\StreamControl;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Service\Twitch\TwitchHelper;
use HedgeBot\Plugins\StreamControl\Event\StreamControlEvent;

class StreamControl extends PluginBase
{
    /**
     * Plugin initialization.
     */
    public function init()
    {
        Plugin::getManager()->addEventListener(StreamControlEvent::getType(), 'StreamControl');

        // Don't load the API endpoint if we're not on the main environment
        if (ENV == "main") {
            Tikal::addEndpoint('/plugin/streamcontrol', new StreamControlEndpoint($this));
        }

    }

    /**
     * Command: sets the stream title.
     */
    public function CommandSetTitle(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 1) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        $title = join(' ', $args);
        $updatedInfo = $this->setChannelInfo($ev->channel, $title);
        
        if(!empty($updatedInfo)) {
            IRC::reply($ev, "Stream title changed to: ". $title);
        } else {
            IRC::reply($ev, "Failed updating stream title.");
        }
    }

    /**
     * Sets the stream game.
     */
    public function CommandSetGame(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 1) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        // Lookup for the game using the Twitch search API
        $gameSearch = join(' ', $args);
        $updatedInfo = $this->setChannelInfo($ev->channel, null, $gameSearch);

        if(!empty($updatedInfo)) {
            IRC::reply($ev, "Stream game changed to: ". $updatedInfo->game);
        } else {
            IRC::reply($ev, "Failed updating stream game.");
        }        
    }

    /**
     * Starts a raid on the given channel.
     */
    public function CommandRaid(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 1) {
            return IRC::reply($ev, "Insufficient parameters.");
        }
        
        $this->raidChannel($ev->channel, $args[0]);
    }

    /**
     * Gets the stream info for the given channel from the Twitch API.
     * 
     * @param string $channel The channel to get info from.
     * @return array|null The channel info, as an array, or null if an error occured.
     */
    public function getChannelInfo(string $channel)
    {
        $channelInfo = Twitch::getClient()->channels->info($channel);
        
        if($channelInfo != false) {   
            return (array) $channelInfo;
        }

        return null;
    }

    /**
     * Sets the stream info for the given channel via the Twitch API.
     * The caller must at least mention either a new title or a new category.
     * 
     * @param string $channel The channel to update the info of.
     * @param string $title The new strean title.
     * @param string $category The new stream category.
     * @return array|bool The new channel info, or False if the request didn't succeed.
     */
    public function setChannelInfo(string $channel, string $title = null, string $category = null)
    {
        $updateParams = [];
        $resolvedCategoryName = null;
        $resolvedCategory = null;
        
        if(empty($title) && empty($category)) {
            return false;
        }
        
        if(!empty($category)) {
            $resolvedCategory = TwitchHelper::resolveGameName($category, $resolvedCategoryName);

            if(empty($resolvedCategory)) {
                return false;
            }

            $updateParams['game_id'] = $resolvedCategory;
        }

        if(!empty($title)) {
            $updateParams['title'] = $title;
        }

        $updated = Twitch::getClient()->channels->update($channel, $updateParams);

        if($updated) {
            $updatedData = [
                'title' => $title,
                'game_id' => $resolvedCategory,
                'game_name' => $resolvedCategoryName
            ];

            Plugin::getManager()->callEvent(new StreamControlEvent('channelInfo', $updatedData));
            return $updatedData;
        }

        return false;
    }

    /**
     * Starts ads on a given channel, for the given duration
     * @param string $channel The channel to start ads on.
     * @param int $duration The ads duration, in seconds. Can be one of 30, 60, 90, 120, 150, 180.
     * @return bool True if the ads started, false if a problem occured.
     */
    public function startAds(string $channel, int $duration)
    {
        $started = Twitch::getClient()->channels->startCommercial($channel, $duration);

        if($started) {
            Plugin::getManager()->callEvent(new StreamControlEvent('ads'));
        }

        return $started;
    }

    /**
     * Starts a raid on the given channel. The raid will be launched 90s after this call.
     * 
     * @param string $from    The channel to raid from.
     * @param string $target  The channel to start a raid on.
     * 
     * @return void
     */
    public function raidChannel(string $from, string $target)
    {
        IRC::message($from, ".raid ". $target);
        Plugin::getManager()->callEvent(new StreamControlEvent('raid'));
    }

    /**
     * Starts hosting the given channel.
     * 
     * @param string $from    The channel to host from.
     * @param string $target  The channel to host.
     * 
     * @return void 
     */
    public function hostChannel(string $from, string $target)
    {
        IRC::message($from, '.host '. $target);
        Plugin::getManager()->callEvent(new StreamControlEvent('host'));
    }
}