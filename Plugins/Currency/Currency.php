<?php

namespace HedgeBot\Plugins\Currency;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\Traits\PropertyConfigMapping;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;

/**
 * @plugin Currency
 *
 * Holds a currency system on the bot, per channel. Upon joining the chat for the first time,
 * each user is given a "bank account" with an initial amount of money. Then, this money can
 * be used on functionalities implemented by other plugins (for example the Blackjack plugin),
 * modified by moderators (by adding and removing some), or money can be given periodically to
 * users on the stream to reward them for their fidelity.
 *
 * @configvar currencyName  Currency singular name (default: coin)
 * @configvar currencyNamePlural Plural Currency plural name (default: coins)
 * @configvar statusCommand The command the bot will respond to display user status (default: coins)
 * @configvar statusMessage The message to be shown when the status command is requested. Message vars:
 *                          - @name: The name of the person who requested the message.
 *                          - @total: The current total of his/her account.
 *                          - @currency: The currency name. Plural form is computed automatically.
 *
 * @configvar initialAmount The initial amount each viewer/chatter is given when initially joining the chat.
 * @configvar giveInterval  Interval of time after which each active user in the channel will receive a money amount.
 * @configvar giveAmount    The amount of money being given at each interval.
 * @configvar timeoutThreshold Threshold of inactivity time after which an user will no longer receive money.
 *
 * Config vars are definable in a global manner using the config namespace "plugin.Currency",
 * and per-channel, using the config namespaces "plugin.Currency.channel.<channel-name>". If one config parameter
 * misses from the per-channel config, then it is taken from the global config.
 * It is advised to define both, to avoid having situations where the default ones are used.
 */
class Currency extends PluginBase
{
    private $accounts = array(); // Accounts, by channel
    private $activityTimes = array(); // Last activity times for channels users
    private $giveTimes = array(); // Last times money was given to users

    // Plugin configuration variables by channel
    private $currencyName = array(); // Money names
    private $currencyNamePlural = array(); // Money plural name
    private $statusCommand = array(); // Money status command names
    private $statusMessage = array(); // Money status command message
    private $initialAmount = array(); // Initial money amount
    private $giveInterval = array(); // Money giving interval
    private $giveAmount = array(); // Money giving amount
    private $timeoutThreshold = array(); // Money giving timeout threshold

    // Global plugin configuration variables, used if no money name is overridden for the channel
    private $globalCurrencyName; // Global money name
    private $globalCurrencyNamePlural; // Global money plural name
    private $globalStatusCommand; // Global money command
    private $globalStatusMessage; // Global money message
    private $globalInitialAmount; // Global initial money amount
    private $globalGiveInterval; // Global money giving interval
    private $globalGiveAmount; // Global money giving amout
    private $globalTimeoutThreshold; // Global money giving timeout threshold, by channel

    const DEFAULT_CURRENCY_NAME = 'coin';
    const DEFAULT_CURRENCY_NAME_PLURAL = 'coins';
    const DEFAULT_STATUS_COMMAND = 'coins';
    const DEFAULT_STATUS_MESSAGE = '@name, you currently have @total @currency';
    const DEFAULT_INITIAL_AMOUNT = 0;
    const DEFAULT_GIVE_INTERVAL = 120;
    const DEFAULT_GIVE_AMOUNT = 5;
    const DEFAULT_TIMEOUT_THRESHOLD = 1800;

    // Traits
    use PropertyConfigMapping;

    /**
     * Plugin initialization
     */
    public function init()
    {
        if (!empty($this->data->accounts)) {
            $this->accounts = $this->data->accounts->toArray();
        }

        $this->reloadConfig();

        Plugin::getManager()->addRoutine($this, 'RoutineAddMoney', 10);
    }

    /**
     *
     */
    public function CoreEventConfigUpdate()
    {
        $this->config = HedgeBot::getInstance()->config->get('plugin.Currency');
        $this->reloadConfig();
    }

    /**
     * Add money to guys standing on the chat for a certain time
     */
    public function RoutineAddMoney()
    {
        $currentTime = time();
        $accountsModified = false;

        foreach ($this->activityTimes as $channel => $channelTimes) {
            // Get the good interval config value
            $giveInterval = $this->getConfigParameter($channel, 'giveInterval');

            // Check that the give interval between money giving is elapsed, if not, go to next iteration
            if (!empty($this->giveTimes[$channel]) && $this->giveTimes[$channel] + $giveInterval > $currentTime) {
                continue;
            }

            // Get configuration settings
            $timeoutThreshold = $this->getConfigParameter($channel, 'timeoutThreshold');
            $giveAmount = $this->getConfigParameter($channel, 'giveAmount');

            // Setting any configuration value to specifically 0 skips giving money
            if ((!empty($this->giveAmount[$channel]) && $this->giveAmount[$channel] === 0) || $giveAmount === 0) {
                continue;
            }

            // Finally, giving to people their money
            foreach ($channelTimes as $name => $time) {
                if ($time + $timeoutThreshold > $currentTime) {
                    $this->accounts[$channel][$name] += $giveAmount;
                    $accountsModified = true;
                }
            }

            // Updating the give time
            $this->giveTimes[$channel] = time();

            // And saving the accounts
            if ($accountsModified) {
                $this->data->set('accounts', $this->accounts);
            }
        }
    }

    /**
     * Initializes an account on join
     *
     * @param ServerEvent $ev
     */
    public function ServerJoin(ServerEvent $ev)
    {
        if (!empty($this->accounts[$ev->channel][$ev->nick])) {
            return;
        }

        $serverConfig = Server::getConfig();
        if (strtolower($ev->nick) == strtolower($serverConfig['name'])) {
            $this->activityTimes[$ev->channel] = array();
            $this->giveTimes[$ev->channel] = time();
            return;
        }

        $initialAmount = $this->getConfigParameter($ev->channel, 'initialAmount');

        $this->accounts[$ev->channel][$ev->nick] = $initialAmount;
        $this->data->set('accounts', $this->accounts);
    }

    /**
     * Initializes accounts for user that talk before the join notice comes in.
     * Handles status command calls too.
     * Updates last activity time for the user.
     *
     * @param ServerEvent $ev
     */
    public function ServerPrivmsg(ServerEvent $ev)
    {
        $this->ServerJoin($ev);

        $cmd = explode(' ', $ev->message);
        if ($cmd[0][0] == '!') {
            $cmd = substr($cmd[0], 1);
            $statusCommand = $this->getConfigParameter($ev->channel, 'statusCommand');
            if ($statusCommand == $cmd) {
                $this->RealCommandAccount($ev);
            }
        }

        $this->activityTimes[$ev->channel][$ev->nick] = time();
    }

    /**
     * Handles whispers as regular messages for money status command.
     *
     * @param ServerEvent $ev
     */
    public function ServerWhisper(ServerEvent $ev)
    {
        $this->ServerPrivmsg($ev);
    }

    /**
     * Adds a given amount of money to a given user.
     *
     * @access moderator
     * @param CommandEvent $ev
     */
    public function CommandGive(CommandEvent $ev)
    {
        // Check rights
        if (!$ev->moderator) {
            return;
        }

        $args = $ev->arguments;

        // Check that arguments are there
        if (count($args) < 2) {
            return IRC::reply($ev, 'Insufficient parameters.');
        }

        // Lowercasing the username
        $nick = strtolower($args[0]);

        // Check that the account exists
        if (!isset($this->accounts[$ev->channel][$nick])) {
            return IRC::reply($ev, 'Unknown user.');
        }

        $this->accounts[$ev->channel][$nick] += (int)$args[1];
        $this->data->set('accounts', $this->accounts);
    }

    /**
     * Checks the current amount of money an user has.
     *
     * @access moderator
     * @param CommandEvent $ev
     */
    public function CommandCheck(CommandEvent $ev)
    {
        if (!$ev->moderator || count($args) < 1) {
            return;
        }

        $args = $ev->arguments;
        $nick = strtolower($args[0]);
        if (isset($this->accounts[$ev->channel][$nick])) {
            $message = $this->formatMessage("Admin check (@name): @total @currency", $ev->channel, $nick);
            IRC::reply($ev, $message);
        }
    }

    /**
     * Removes a given amount of money from an user.
     *
     * @access moderator
     * @param CommandEvent $ev
     */
    public function CommandTake(CommandEvent $ev)
    {
        // Check rights
        if (!$ev->moderator) {
            return;
        }

        $args = $ev->arguments;

        // Check that arguments are there
        if (count($args) < 2) {
            return IRC::reply($ev, 'Insufficient parameters.');
        }

        // Lowercasing the username
        $nick = strtolower($args[0]);

        // Check that the account exists
        if (!isset($this->accounts[$ev->channel][$nick])) {
            return IRC::reply($ev, 'Unknown user.');
        }

        // Perform account operations
        $sum = (int)$args[1];

        if ($this->accounts[$ev->channel][$nick] - $sum > 0) {
            $this->accounts[$ev->channel][$nick] -= $sum;
        } else {
            $this->accounts[$ev->channel][$nick] = 0;
        }

        $this->data->set('accounts', $this->accounts);
    }

    /**
     * Real account show command, shows the current amount of currency for the user
     *
     * @param ServerEvent $ev
     */
    public function RealCommandAccount(ServerEvent $ev)
    {
        $message = $this->getConfigParameter($ev->channel, 'statusMessage');
        IRC::reply($ev, $this->formatMessage($message, $ev->channel, $ev->nick));
    }

    /**
     * Formats a currency message, with plural forms and everything
     *
     * @param $message
     * @param $channel
     * @param $name
     * @return mixed
     */
    private function formatMessage($message, $channel, $name)
    {
        if (!empty($this->currencyName[$channel])) {
            $currencyName = $this->currencyName[$channel];
            $currencyNamePlural = $this->currencyNamePlural[$channel];
        } else {
            $currencyName = $this->globalCurrencyName;
            $currencyNamePlural = $this->globalCurrencyNamePlural;
        }

        $message = str_replace(array(
            '@name',
            '@total',
            '@currency'
        ),
            array(
                $name,
                $this->accounts[$channel][$name],
                $this->accounts[$channel][$name] > 1 ?
                    $currencyNamePlural
                    : $currencyName
            ),
            $message);

        return $message;
    }

    // API methods

    /** Reloads configuration variables */
    public function reloadConfig()
    {
        $parameters = [
            'currencyName',
            'currencyNamePlural',
            'statusCommand',
            'statusMessage',
            'initialAmount',
            'giveInterval',
            'giveAmount',
            'timeoutThreshold'
        ];

        $this->globalCurrencyName = self::DEFAULT_CURRENCY_NAME;
        $this->globalCurrencyNamePlural = self::DEFAULT_CURRENCY_NAME_PLURAL;
        $this->globalStatusCommand = self::DEFAULT_STATUS_COMMAND;
        $this->globalStatusMessage = self::DEFAULT_STATUS_MESSAGE;
        $this->globalInitialAmount = self::DEFAULT_INITIAL_AMOUNT;
        $this->globalGiveInterval = self::DEFAULT_GIVE_INTERVAL;
        $this->globalGiveAmount = self::DEFAULT_GIVE_AMOUNT;
        $this->globalTimeoutThreshold = self::DEFAULT_TIMEOUT_THRESHOLD;

        $this->mapConfig($this->config, $parameters);
    }

    /** Gets a persons' account balance, inside a channel.
     *
     * @param string $channel The channel to search the user from
     * @param string $name The user to get the balance of
     * @return object|null The balance if found, null otherwise
     */
    public function getBalance($channel, $name)
    {
        if (empty($this->accounts[$channel])) {
            return null;
        }

        if (empty($this->accounts[$channel][$name])) {
            return null;
        }

        return $this->accounts[$channel][$name];
    }

    /**
     * Gives money to someone on a channel.
     *
     * @param string $channel The channel on which execute the operation
     * @param string $name  The name of the person to give money to
     * @param int $amount
     * @return object|bool The new balance if the money has been given, false otherwise
     */
    public function giveAmount($channel, $name, $amount)
    {
        if (empty($this->getBalance($channel, $name))) {
            return false;
        }

        $this->accounts[$channel][$name] += $amount;
        $this->data->set('accounts', $this->accounts);

        return $this->accounts[$channel][$name];
    }

    /**
     * Takes money from someone on a channel.
     *
     * @param string $channel The channel on which execute the operation.
     * @param string $name The name of the person to take money from.
     * @param int $amount
     * @return object|bool The new balance if the money has been taken, false otherwise.
     */
    public function takeAmount($channel, $name, $amount)
    {
        if (empty($this->getBalance($channel, $name))) {
            return false;
        }

        $this->accounts[$channel][$name] -= $amount;
        $this->data->set('accounts', $this->accounts);

        return $this->accounts[$channel][$name];
    }
}
