<?php
namespace HedgeBot\Plugins\StreamControl;

use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;

class StreamControl extends Plugin
{
    /**
     * Plugin initialization.
     */
    public function init()
    {
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
        
        $this->raidChannel($args[0]);
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
        
        if(empty($title) && empty($category)) {
            return false;
        }
        
        if(!empty($category)) {
            $resolvedCategory = $this->resolveGameName($category);

            if(empty($resolvedCategory)) {
                return false;
            }

            $updateParams['game'] = $resolvedCategory;
        }

        if(!empty($title)) {
            $updateParams['status'] = $title;
        }

        $update = Twitch::getClient()->channels->update($channel, $updateParams);
        return $update;
    }

    /**
     * Starts ads on a given channel, for the given duration
     * @param string $channel The channel to start ads on.
     * @param int $duration The ads duration, in seconds. Can be one of 30, 60, 90, 120, 150, 180.
     * @return bool True if the ads started, false if a problem occured.
     */
    public function startAds(string $channel, int $duration)
    {
        return Twitch::getClient()->channels->startCommercial($channel, $duration);
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
    }

    /**
     * Tries to resolve the game name from the Twitch API.
     * @param string $gameSearch The game to search for.
     * @return string|null The resolved game name or null if no game is found. 
     */
    protected function resolveGameName(string $gameSearch)
    {
        $gamesMatches = Twitch::getClient()->search->games($gameSearch);

        if(empty($gamesMatches)) {
            HedgeBot::message("No match.");
            return null;
        }

        // Try to find the closest game to the given title
        $gamesLevenshtein = [];

        foreach($gamesMatches as $game) {
            $gamesLevenshtein[$game->name] = levenshtein($game->name, $gameSearch);
        }

        asort($gamesLevenshtein);
        $gamesOrdered = array_keys($gamesLevenshtein);
        $closest = reset($gamesOrdered);

        Hedgebot::message("Most pertinent game name found: $0 ($1)", [$closest, $gamesLevenshtein[$closest]]);

        HedgeBot::message("Results order:", [], E_DEBUG);
        foreach($gamesOrdered as $game => $score) {
            HedgeBot::message("$0 => $1", [$game, $score], E_DEBUG);
        }

        return $closest;
    }
}