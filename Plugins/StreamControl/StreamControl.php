<?php
namespace HedgeBot\Plugins\StreamControl;

use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;

class StreamControl extends Plugin
{
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
        Twitch::getClient()->channels->update($ev->channel, ['status' => $title]);
        IRC::reply($ev, "Stream title changed to: ". $title);
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
        $gamesMatches = Twitch::getClient()->search->games($gameSearch);

        HedgeBot::message("Game change requested: $0", [$gameSearch]);

        if(empty($gamesMatches)) {
            HedgeBot::message("No match.");
            return IRC::reply($ev, "No matching game found.");
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

        Twitch::getClient()->channels->update($ev->channel, ['game' => $closest]);
        IRC::reply($ev, "Stream game changed to: ". $closest);
    }

    public function CommandRaid(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 1) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        IRC::message($ev->channel, ".raid ". $args[0]);
    }
}