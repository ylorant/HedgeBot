<?php
namespace HedgeBot\Core\Service\Twitch;

use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\HedgeBot;

class TwitchHelper
{
    /**
     * Tries to resolve a game's name from the Twitch API. This method exists because the Twitch API doesn't return the
     * search results ordered by closeness so we need to do an additional sorting in those.
     * 
     * @param string $gameSearch The game to search for.
     * @param mixed $categoryName Reference to the found category adjusted name, to be filled by the function.
     * @return string|null The resolved game name or null if no game is found. 
     */
    public static function resolveGameName(string $gameSearch, &$categoryName = null)
    {
        $gamesMatches = Twitch::getClient()->search->categories($gameSearch, 20, false);

        if(empty($gamesMatches)) {
            HedgeBot::message("No match.");
            return null;
        }

        // Try to find the closest game to the given title
        $gamesLevenshtein = [];
        $gameIds = [];

        foreach($gamesMatches as $game) {
            $gameIds[$game->name] = $game->id;
            $gamesLevenshtein[$game->name] = levenshtein($game->name, $gameSearch);
        }

        asort($gamesLevenshtein);
        $gamesOrdered = array_keys($gamesLevenshtein);
        $closest = reset($gamesOrdered);
        $categoryName = $closest;

        Hedgebot::message("Most pertinent game name found: $0 ($1)", [$closest, $gamesLevenshtein[$closest]]);

        HedgeBot::message("Results order:", [], E_DEBUG);
        foreach($gamesOrdered as $game => $score) {
            HedgeBot::message("$0 => $1", [$game, $score], E_DEBUG);
        }

        return $gameIds[$closest];
    }
}