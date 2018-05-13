<?php

namespace HedgeBot\Plugins\BlackJack;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\Traits\PropertyConfigMapping;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\Events\CommandEvent;

/**
 * Class BlackJack
 * @package HedgeBot\Plugins\BlackJack
 */
class BlackJack extends PluginBase
{
    private $games = []; // Games, by channel
    private $currency; // Reference to the Currency plugin

    // Plugin configuration variables by channel
    private $deckCount = [];
    private $messages = [];
    private $houseName = [];
    private $blackjackMultiplicator = [];
    private $winMultiplicator = [];
    private $joinTimeout = [];

    // Plugin global configuration variables
    private $globalDeckCount;
    private $globalMessages;
    private $globalHouseName;
    private $globalBlackjackMultiplicator;
    private $globalWinMultiplicator;
    private $globalJoinTimeout;

    // Cards colors Unicode characters
    const CARDS_COLORS = [
        "\xE2\x99\xA0", // Spades
        "\xE2\x99\xA3", // Clubs
        "\xE2\x99\xA5", // Hearts
        "\xE2\x99\xA6"  // Diamonds
    ];

    // Cards values. A function will get each card numerary value from its name.
    const CARDS_VALUES = ["2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K", "A"];

    // Default parameters values
    const DEFAULT_DECK_COUNT = 6; // 6 decks is the default count for european casinos
    const DEFAULT_HOUSE_NAME = "the House";
    const DEFAULT_BLACKJACK_MULTIPLICATOR = 1.5;
    const DEFAULT_WIN_MULTIPLICATOR = 1;
    const DEFAULT_JOIN_TIMEOUT = 60;
    const DEFAULT_MESSAGES = [
        "init" => "A BlackJack game has been started ! Type !join <bet> to join the game !",
        "joinTimeout" => "The game will start in @timeout seconds or when a player types !start !",
        "joinStart" => "The game will start when a player types !start !",
        "gameReset" => "No player joined, the game is stopped. Try again later !",
        "gameInProgress" => "You can't do that, there's already a game in progress.",
        "noGame" => "There is no game currently in progress.",
        "alreadyInGame" => "You are already in the game.",
        "noPlayer" => "There is no player in the game.",
        "missingBet" => "You must enter a bet to join.",
        "notEnoughMoney" => "You don't have enough to bet this amount.",
        "hand" => "Hand for @player: @hand",
        "gameStarted" => "The game is started !",
        "playInstructions" => "To draw a card, type !draw. To stay with your hand, type !stay.",
        "notPlaying" => "You can't do that, you are not playing.",
        "playerBlackjack" => "Congratulations, @player ! You got a blackjack !",
        "playerLost" => "Sorry, @player, but you lost.",
        "houseTurn" => "Every player's turn is finished, now it's time for the house to play !",
        "finalHand" => "Final hand for @player: @hand",
        "houseLost" => "@houseName has lost.",
        "houseBlackjack" => "@houseName has a Blackjack !",
        "winnersList" => "Winners: @players",
        "blackjackList" => "Blackjacks: @players",
        "tiesList" => "Ties: @players",
        "noWinner" => "Nobody has won, bad luck !"
    ];

    //Traits
    use PropertyConfigMapping;

    /**
     * @return bool|void
     */
    public function init()
    {
        $this->currency = Plugin::get('Currency');
        $this->reloadConfig();

        $pluginManager = Plugin::getManager();
        $pluginManager->addRoutine($this, 'RoutineJoinTimeout');
    }

    /**
     *
     */
    public function RoutineJoinTimeout()
    {
        $time = time();
        foreach ($this->games as $channel => $game) {
            if ($game->getState() == Game::STATE_JOIN) {
                $joinTimeout = $this->getConfigParameter($channel, 'joinTimeout');
                if ($time - $game->getIdleTime() >= $joinTimeout) {
                    // If there is players, trigger a start command, else, reset the game
                    if (count($game->getPlayers()) > 0) {
                        $this->CommandStart(array('channel' => $channel), array());
                    } else {
                        $game->reset();
                        IRC::message($channel, $this->getConfigParameter($channel, 'messages.gameReset'));
                    }
                }
            }
        }
    }

    /**
     * Initializes a blackjack game. Basically, one game per channel at a time, and a game can be
     * reused after it has been finished, with the deck not being reinitialized.
     *
     * @param CommandEvent $ev
     * @return bool
     */
    public function CommandBlackJack(CommandEvent $ev)
    {
        // Create a game if it doesn't exist
        if (empty($this->games[$ev->channel])) {
            $this->games[$ev->channel] = new Game($ev->channel, $this);
        }

        $messages = $this->getConfigParameter($ev->channel, 'messages');
        $game = $this->games[$ev->channel];

        $initialized = $game->init(); // Initialize that s**t

        // Check if the game isn't already started
        if (!$initialized) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.gameInProgress'));
        }

        IRC::message($ev->channel, $this->getConfigParameter($ev->channel, 'messages.init'));

        // Show join timeout if necessary
        $joinTimeout = $this->getConfigParameter($ev->channel, 'joinTimeout');
        $startMessage = 'messages.joinStart';
        if ($joinTimeout > 0) {
            $startMessage = 'messages.joinTimeout';
        }

        $message = str_replace('@timeout', $joinTimeout, $this->getConfigParameter($ev->channel, $startMessage));
        IRC::message($ev->channel, $message);

        return true;
    }

    /**
     * Joins a game.
     * The player has to specify a bet for him to play.
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandJoin(CommandEvent $ev)
    {
        // Check the parameters are there
        if (!isset($ev->arguments[0]) || !is_numeric($ev->arguments[0])) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.missingBet'));
        }

        // Get the bet amount and the account balance for the player
        $bet = intval($ev->arguments[0]);
        $balance = $this->currency->getBalance($ev->channel, $ev->nick);

        // Check all there is to check on the game and the player.
        $canJoin = false;
        $errorMsg = null;
        if (empty($this->games[$ev->channel]) || $this->games[$ev->channel]->getState() == Game::STATE_IDLE) {
            $errorMsg = 'messages.noGame';
        } elseif ($this->games[$ev->channel]->getState() == Game::STATE_PLAY) {
            $errorMsg = 'messages.gameInProgress';
        } elseif ($balance < $bet) {
            $errorMsg = 'messages.notEnoughMoney';
        } else {
            $canJoin = true;
        }

        // If the player can't join the game, show the error and return.
        if (!$canJoin) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, $errorMsg));
        }

        // Join the game and handle the errors.
        $result = $this->games[$ev->channel]->joinGame($ev->nick, $bet);

        if (!$result) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.alreadyInGame'));
        }

        // Remove the bet from the account of the player
        $this->currency->takeAmount($ev->channel, $ev->nick, $bet);
    }

    /**
     * Starts the game.
     * There has to be at least one player in the game to start it.
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandStart(CommandEvent $ev)
    {
        $channel = $ev->channel;

        // Checking that the game exists
        if (empty($this->games[$channel]) || $this->games[$channel]->getState() == Game::STATE_IDLE) {
            return IRC::message($channel, $this->getConfigParameter($channel, 'messages.noGame'));
        }

        // Checking that the game isn't already playing
        if ($this->games[$channel]->getState() == Game::STATE_PLAY) {
            return IRC::message($channel, $this->getConfigParameter($channel, 'messages.gameInProgress'));
        }

        // The game cannot start if there is no player entered.
        if (count($this->games[$channel]->getPlayers()) == 0) {
            return IRC::message($channel, $this->getConfigParameter($channel, 'messages.noPlayer'));
        }

        IRC::message($channel, $this->getConfigParameter($channel, 'messages.gameStarted'));

        $game = $this->games[$channel];
        $result = $game->startGame();
        $this->showHands($channel);

        $blackjacks = [];
        foreach ($game->getPlayers() as $player) {
            $player = $game->getPlayer($player);
            if ($player->status == Game::PLAYER_BLACKJACK) {
                $blackjacks[] = $player;
            }
        }

        // Handling players who have blackjacks
        if (!empty($blackjacks)) {
            // Preparing message
            $message = $this->getConfigParameter($channel, 'messages.playerBlackjack');

            // Giving the players who have blackjack its earnings and telling that they won
            foreach ($blackjacks as $player) {
                IRC::message($channel, str_replace('@player', $player->name, $message));
            }
        }

        // Show the instructions only if there is still players in the game
        if (!empty($game->getPlayers(true))) {
            IRC::message($channel, $this->getConfigParameter($channel, 'messages.playInstructions'));
        }
    }

    /**
     * Draws a card
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandDraw(CommandEvent $ev)
    {
        // Checking that there is a game
        if (empty($this->games[$ev->channel]) || $this->games[$ev->channel]->getState() != Game::STATE_PLAY) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.noGame'));
        }

        // Trying to draw
        $return = $this->games[$ev->channel]->draw($ev->nick);
        if (!$return) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.notPlaying'));
        }

        $this->showHand($ev->channel, $ev->nick);
        if ($this->games[$ev->channel]->getPlayer($ev->nick)->status == Game::PLAYER_LOST) {
            IRC::reply(
                $ev,
                str_replace('@player', $ev->nick, $this->getConfigParameter($ev->channel, 'messages.playerLost'))
            );
            $this->finishGameIfNecessary($ev->channel);
        }
    }

    /**
     * Ends a player's turn
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandStay(CommandEvent $ev)
    {
        // Checking that there is a game
        if (empty($this->games[$ev->channel]) || $this->games[$ev->channel]->getState() != Game::STATE_PLAY) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.noGame'));
        }

        // Trying to draw
        $return = $this->games[$ev->channel]->stay($ev->nick);
        if (!$return) {
            return IRC::reply($ev, $this->getConfigParameter($ev->channel, 'messages.notPlaying'));
        }

        $this->finishGameIfNecessary($ev->channel);
    }

    /**
     * Event on configuration update detection. Reloads the configuration.
     */
    public function CoreEventConfigUpdate()
    {
        $this->config = HedgeBot::getInstance()->config->get('plugin.Currency');
        $this->reloadConfig();
    }

    /**
     * Shows the hands of every player, with the house hand.
     *
     * @param string $channel The channel from which display the hands.
     * @return bool True if success, false otherwise (channel hasn't got a game or game isn't playing).
     */
    private function showHands($channel)
    {
        if (empty($this->games[$channel]) || $this->games[$channel]->getState() != Game::STATE_PLAY) {
            return false;
        }

        $players = $this->games[$channel]->getPlayers();

        foreach ($players as $player) {
            $this->showHand($channel, $player);
        }

        $this->showHouseHand($channel);

        return true;
    }

    /**
     *  Shows the hand of one player.
     *
     * @param string $channel The channel of the game
     * @param object $player The player's hand
     */
    private function showHand($channel, $player)
    {
        $handMessage = $this->getConfigParameter($channel, 'messages.hand');
        $playerObject = $this->games[$channel]->getPlayer($player, true);
        $hand = $playerObject->hand;
        $playerHandMessage = str_replace('@player', $player, $handMessage);
        $playerHandMessage = str_replace('@hand', join('  ', $hand), $playerHandMessage);
        IRC::message($channel, $playerHandMessage . ' (' . $playerObject->handValue . ')');
    }

    /**
     * Shows only the house hand.
     *
     * @param $channel
     * @param bool $hidden Hides the second card or not. Defaults to true
     * @param string $messageName
     */
    private function showHouseHand($channel, $hidden = true, $messageName = 'messages.hand')
    {
        $handMessage = $this->getConfigParameter($channel, $messageName);
        $house = $this->games[$channel]->getHouse(true);
        $houseHand = $house->hand;
        $handSuffix = " (" . $house->handValue . ")";

        if ($hidden) {
            $houseHand[1] = "??";
            $handSuffix = "";
        }

        $houseHandMessage = str_replace('@player', $this->getConfigParameter($channel, "houseName"), $handMessage);
        $houseHandMessage = str_replace('@hand', join('  ', $houseHand) . $handSuffix, $houseHandMessage);
        IRC::message($channel, $houseHandMessage);
    }

    /**
     * Finishes the game if everybody is staying or have lost.
     *
     * @param string $channel The channel on which to do that
     * @return bool
     */
    private function finishGameIfNecessary($channel)
    {
        if (empty($this->games[$channel]) || $this->games[$channel]->getState() != Game::STATE_PLAY) {
            return false;
        }

        // The game is still on when there is still active players
        if (count($this->games[$channel]->getPlayers(true)) > 0) {
            return true;
        }

        IRC::message($channel, $this->getConfigParameter($channel, 'messages.houseTurn'));

        // Finish the game
        $this->games[$channel]->finishGame();
        $this->showHouseHand($channel, false, 'messages.finalHand');

        // Setting all variables
        $house = $this->games[$channel]->getHouse(true);
        $bjRate = $this->getConfigParameter($channel, 'blackjackMultiplicator');
        $winners = [];
        $losers = [];
        $ties = [];
        $blackjacks = [];

        // Giving final scores and paying out
        foreach ($this->games[$channel]->getPlayers() as $player) {
            $player = $this->games[$channel]->getPlayer($player, true);
            switch ($player->status) {
                case Game::PLAYER_STAY:
                    if ($house->status == Game::PLAYER_LOST) {
                        $winners[] = $player;
                    } elseif ($house->status == Game::PLAYER_BLACKJACK) {
                        $losers[] = $player;
                    } else { // House is on stay
                        if ($house->handValue > $player->handValue) {
                            $losers[] = $player;
                        } elseif ($house->handValue < $player->handValue) {
                            $winners[] = $player;
                        } else {
                            $ties[] = $player;
                        }
                    }
                    break;
                case Game::PLAYER_LOST:
                    $losers[] = $player;
                    break;
                case Game::PLAYER_BLACKJACK:
                    if ($house->status == Game::PLAYER_BLACKJACK) {
                        $ties[] = $player;
                    } else {
                        $blackjacks[] = $player;
                    }
                    break;
            }
        }

        // Showing status of house + all the players
        $messageKey = null;
        if ($house->status == Game::PLAYER_LOST) {
            $messageKey = 'messages.houseLost';
        } elseif ($house->status == Game::PLAYER_BLACKJACK) {
            $messageKey = 'messages.houseBlackjack';
        }

        if (!empty($messageKey)) {
            $message = $this->getConfigParameter($channel, $messageKey);
            $houseName = $this->getConfigParameter($channel, 'houseName');
            $message = str_replace('@houseName', $this->getConfigParameter($channel, "houseName"), $message);
            IRC::message($channel, $message);
        }

        // Show players who won and give them their prize
        if (!empty($winners)) {
            $message = $this->getConfigParameter($channel, 'messages.winnersList');
            $winMultiplicator = $this->getConfigParameter($channel, 'winMultiplicator');

            $message = str_replace(
                '@players',
                join(', ', array_map(__CLASS__ . '::getPlayerName', $winners)),
                $message
            );
            IRC::message($channel, $message);

            foreach ($winners as $player) {
                $this->currency->giveAmount($channel, $player->name, $player->bet * (1 + $winMultiplicator));
            }
        }

        // Show players who got a blackjack and give them their prize
        if (!empty($blackjacks)) {
            $message = $this->getConfigParameter($channel, 'messages.blackjackList');
            $blackjackMultiplicator = $this->getConfigParameter($channel, 'blackjackMultiplicator');

            $messages = str_replace(
                '@players',
                join(', ', array_map(__CLASS__ . '::getPlayerName', $blackjacks)),
                $message
            );
            IRC::message($channel, $message);

            foreach ($blackjacks as $player) {
                $this->currency->giveAmount($channel, $player->name, $player->bet * (1 + $blackjackMultiplicator));
            }
        }

        // Show ties and give them their bet back
        if (!empty($ties)) {
            $message = $this->getConfigParameter($channel, 'messages.tiesList');

            $messages = str_replace('@players', join(', ', array_map(__CLASS__ . '::getPlayerName', $ties)), $message);
            IRC::message($channel, $message);

            foreach ($ties as $player) {
                $this->currency->giveAmount($channel, $player->name, $player->bet);
            }
        }

        // If there are no winners, show it
        if (empty($blackjacks) && empty($winners) && empty($ties)) {
            IRC::message($channel, $this->getConfigParameter($channel, 'messages.noWinner'));
        }
    }

    private function reloadConfig()
    {
        $parameters = [
            "deckCount",
            "houseName",
            "messages",
            "blackjackMultiplicator",
            "winMultiplicator",
            "joinTimeout"
        ];

        // Setting default values for scalars
        $this->globalDeckCount = self::DEFAULT_DECK_COUNT;
        $this->globalHouseName = self::DEFAULT_HOUSE_NAME;
        $this->globalMessages = self::DEFAULT_MESSAGES;
        $this->globalWinMultiplicator = self::DEFAULT_WIN_MULTIPLICATOR;
        $this->globalBlackjackMultiplicator = self::DEFAULT_BLACKJACK_MULTIPLICATOR;
        $this->globalJoinTimeout = self::DEFAULT_JOIN_TIMEOUT;

        $this->mapConfig($this->config, $parameters);
    }

    /**
     * @param $player
     * @return mixed
     */
    private static function getPlayerName($player)
    {
        return $player->name;
    }
}
