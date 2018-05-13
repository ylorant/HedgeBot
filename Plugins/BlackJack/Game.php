<?php

namespace HedgeBot\Plugins\BlackJack;

use stdClass;

/**
 * Class Game
 * @package HedgeBot\Plugins\BlackJack
 */
class Game
{
    private $channel; // Channel on which the game is occuring
    private $players; // Players objects list
    private $plugin; // Reference to the main object ot read config and things.
    private $deck; // Deck of cards that haven't been drawn.
    private $discard; // Discard stack
    private $state; // State of the game
    private $house; // Object for the house
    private $idleTime; // Idle time for timeouts and things

    // Game states
    const STATE_IDLE = 0;
    const STATE_JOIN = 1;
    const STATE_PLAY = 2;

    // Draw/Game results
    const RESULT_LOST = 0;
    const RESULT_WON = 1; // Used only for game results
    const RESULT_STILL = 2; // Used when drawing a card, if the draw doesn't influence the game for that player
    const RESULT_BLACKJACK = 3;

    // Player status
    const PLAYER_INGAME = 0;
    const PLAYER_LOST = 1;
    const PLAYER_STAY = 2;
    const PLAYER_BLACKJACK = 3;

    /**
     * Game constructor.
     * Initializes a game.
     *
     * @param $channel The channel the game is on.
     * @param $plugin Reference to the main Blackjack plugin object.
     */
    public function __construct($channel, $plugin)
    {
        $this->channel = $channel;
        $this->plugin = $plugin;
        $this->state = self::STATE_IDLE;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = "Game state: " . $this->channel . " (" . $this->state . ")\n";
        $string .= "Hands:\n";

        foreach ($this->hands as $player => $hand) {
            $string .= "\t" . $player . ": " . join(', ', $hand) . "\n";
        }

        $string .= "\nHouse hand: " . join(', ', $this->houseHand) . "\n";

        $string .= "\nBets:\n";

        foreach ($this->bets as $player => $bet) {
            $string .= "\t" . $player . ": " . $bet . "\n";
        }

        return $string;
    }

    // Game operations

    /**
     * Initializes a BlackJack game.
     *
     * \return True if the game correctly initialized, false otherwise (there is already a game playing).
     */
    public function init()
    {
        if ($this->state == self::STATE_IDLE) {
            $this->state = self::STATE_JOIN;
            $this->players = [];
            $this->idleTime = time();

            $this->house = new stdClass();
            $this->house->hand = [];
            $this->house->status = self::PLAYER_INGAME;

            return true;
        }

        return false;
    }

    /**
     * Resets the game to its idle state.
     */
    public function reset()
    {
        $this->state = self::STATE_IDLE;
    }

    /**
     * Makes a player join the game.
     *
     * @param string $playerName The name of the player who is joining.
     * @param int $bet The amount of the bet.
     * @return bool True if the player has successfully joined, false otherwise (if the player has already joined).
     */
    public function joinGame($playerName, $bet)
    {
        if (isset($this->players[$playerName])) {
            return false;
        }

        $player = new stdClass();
        $player->hand = [];
        $player->name = $playerName;
        $player->status = self::PLAYER_INGAME;
        $player->bet = $bet;

        $this->players[$playerName] = $player;
        return true;
    }

    /**
     * Starts the game. At least one player has to be entered to play.
     *
     * @return bool If there is an error it will return False.
     *              If it has been correctly started, it will return an array containing the players.
     */
    public function startGame()
    {
        if ($this->state != self::STATE_JOIN) {
            return false;
        }

        $this->state = self::STATE_PLAY;
        $this->generateDeck();

        foreach ($this->players as $player => $playing) {
            $this->draw($player, 2);
        }

        $this->drawHouse(2);
    }

    /**
     * Draws cards for a player.
     *
     * @param string  $player The player to draw cards to.
     * @param int $count How many cards to draw. Defaults to only 1 card.
     * @return bool True in case of success, or false otherwise (the player isn't playing anymore).
     */
    public function draw($player, $count = 1)
    {
        // Is the player still in the game ?
        if (empty($this->players[$player]) || $this->players[$player]->status != self::PLAYER_INGAME) {
            return false;
        }

        // Draw cards as many times as necessary
        for ($i = 0; $i < $count; $i++) {
            $card = array_shift($this->deck);
            $this->players[$player]->hand[] = $card;

            // Refill the deck if it is empty
            if (empty($this->deck)) {
                $this->deck = $this->discard;
                $this->discard = [];
                shuffle($this->deck);
            }
        }

        // If the player has more than 21, he lost
        if ($this->computeHandValue($this->players[$player]->hand) > 21) {
            $this->players[$player]->status = self::PLAYER_LOST;
        }

        // Check blackjacks (value must be 21 and card count must be 2)
        if ($this->computeHandValue($this->players[$player]->hand) == 21 && count($this->players[$player]->hand) == 2) {
            $this->players[$player]->status = self::PLAYER_BLACKJACK;
        }

        return true;
    }

    /**
     * @param $player
     * @return bool
     */
    public function stay($player)
    {
        if (empty($this->players[$player]) || $this->players[$player]->status != self::PLAYER_INGAME) { // Is the player still in the game ?
            return false;
        }

        $this->players[$player]->status = self::PLAYER_STAY;
        return true;
    }

    /**
     * Draws cards for the house's hand.
     *
     * @param int $count The number of cards to draw. Defaults to one card.
     * @return bool
     */
    public function drawHouse($count = 1)
    {
        // Draw cards as many times as necessary
        for ($i = 0; $i < $count; $i++) {
            $card = array_shift($this->deck);
            $this->house->hand[] = $card;

            // Refill the deck if it is empty
            if (empty($this->deck)) {
                $this->deck = $this->discard;
                $this->discard = [];
                shuffle($this->deck);
            }
        }

        $handValue = $this->computeHandValue($this->house->hand);

        // If the player has more than 21, he lost
        if ($handValue > 21) {
            $this->house->status = self::PLAYER_LOST;
        }

        // Check blackjacks (value must be 21 and card count must be 2)
        if ($handValue == 21 && count($this->house->hand) == 2) {
            $this->house->status = self::PLAYER_BLACKJACK;
        }

        return true;
    }

    /**
     * Finishes a game when every human player has played. Holds the house hand draw strategy.
     *
     * \return False if the game still hasn't finished, true otherwise.
     */
    public function finishGame()
    {
        if ($this->getPlayers(true) == 0) {
            return false;
        }

        if ($this->house->status == self::PLAYER_BLACKJACK) {
            return true;
        }

        while ($this->computeHandValue($this->house->hand) < 17) {
            $this->drawHouse();
        }

        if ($this->house->status != self::PLAYER_LOST) {
            $this->house->status = self::PLAYER_STAY;
        }

        $this->state = self::STATE_IDLE;

        return true;
    }

    // Internal logic functions

    /**
     * Generates a deck of cards composed of multiple regular 52-cards decks.
     */
    private function generateDeck()
    {
        $this->deck = array();

        $deckCount = $this->plugin->getConfigParameter($this->channel, 'deckCount');
        for ($i = 0; $i < $deckCount; $i++) {
            foreach (BlackJack::CARDS_COLORS as $color) {
                foreach (BlackJack::CARDS_VALUES as $value) {
                    $this->deck[] = $value . $color;
                }
            }
        }

        shuffle($this->deck); // Shuffling the cards.
    }

    /**
     * Just the code computing the hand value.
     *
     * @param $cardList
     * @return int|mixed
     */
    private function computeHandValue($cardList)
    {
        $handTotal = 0;
        $orderedCards = $this->reorderCards($cardList);

        foreach ($orderedCards as $card) {
            $value = str_replace(BlackJack::CARDS_COLORS, '', $card); // strip color
            switch ($value) {
                case 'J':
                case 'Q':
                case 'K':
                    $handTotal += 10;
                    break;
                case 'A':
                    if ($handTotal > 10) {
                        $handTotal += 1;
                    } else {
                        $handTotal += 11;
                    }
                    break;
                default:
                    $handTotal += $value;
            }
        }

        return $handTotal;
    }

    /**
     * Reorder cards from lowest value to highest value.
     *
     * @param $cardList
     * @return mixed
     */
    private function reorderCards($cardList)
    {
        $sortedCards = $cardList;

        $sortFunction = function ($a, $b) {
            $aValue = substr($a, 0, -1);
            $bValue = substr($b, 0, -1);

            $aKey = array_search($aValue, BlackJack::CARDS_VALUES);
            $bKey = array_search($bValue, BlackJack::CARDS_VALUES);

            return $aKey - $bKey;
        };

        usort($sortedCards, $sortFunction);
        return $sortedCards;
    }

    // Accessors

    /**
     * Returns the current state of the game.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Returns the amount of time the game has been idle for.
     */
    public function getIdleTime()
    {
        return $this->idleTime;
    }

    /**
     * Returns the list of players entered into the game.
     *
     * @param bool $active filters out inactive players. Defaults to false.
     * @return array The list of players
     */
    public function getPlayers($active = false)
    {
        if ($active) {
            return array_filter(array_keys($this->players), array($this, 'isPlaying'));
        } else {
            return array_keys($this->players);
        }
    }

    /**
     * Returns if a player is playing or not.
     *
     * @param string $player The player's name.
     * @return bool True if the player can still play (i.e. draw cards), false otherwise.
     */
    public function isPlaying($player)
    {
        return !empty($this->players[$player]) ? $this->players[$player]->status == self::PLAYER_INGAME : false;
    }

    /**
     * Gets info from a player.
     *
     * @param string $player The player's name.
     * @param bool $getHandValue
     * @return stdClass|bool The player data as an stdClass object if found, otherwise it returns false.
     */
    public function getPlayer($player, $getHandValue = false)
    {
        if (!isset($this->players[$player])) {
            return false;
        }

        $playerObject = clone $this->players[$player];
        if ($getHandValue) {
            $playerObject->handValue = $this->computeHandValue($playerObject->hand);
        }

        return $playerObject;
    }

    /**
     * Gets the house's object.
     *
     * @param bool $getHandValue
     * @return stdClass The house's object as a stdClass object.
     */
    public function getHouse($getHandValue = false)
    {
        $houseObject = clone $this->house;
        if ($getHandValue) {
            $houseObject->handValue = $this->computeHandValue($houseObject->hand);
        }

        return $houseObject;
    }
}
