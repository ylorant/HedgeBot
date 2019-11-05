<?php
namespace HedgeBot\Plugins\StreamControl;

use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Twitch;

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
        IRC::whisper($ev->nick, "Stream title changed to: ". $title);
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

        if(empty($gamesMatches)) {
            return IRC::whisper($ev->nick, "No matching game found.");
        }

        // Try to find the closest game to the given title
        $closest = null;
        $closestLevenshtein = null;

        foreach($gamesMatches as $game) {
            $gameLevenshtein = levenshtein($game->name, $gameSearch);
            
            if($closestLevenshtein == null || $gameLevenshtein < $closestLevenshtein) {
                $closestLevenshtein = $gameLevenshtein;
                $closest = $game->name;
            }
        }

        Twitch::getClient()->channels->update($ev->channel, ['game' => $closest]);
        IRC::whisper($ev->nick, "Stream game changed to: ". $closest);
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