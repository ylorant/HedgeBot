<?php
namespace HedgeBot\Plugins\Currency;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\IRC;

/**
 * Currency plugin
 * Holds a currency system on the bot, per channel.
 *
 * Configuation vars:
 *
 * - currencyName: Currency singular name (default: coin)
 * - currencyNamePlural: Currency plural name (default: coins)
 * - statusCommand: The command the bot will respond to display user status (default: coins)
 * - statusMessage: The message to be shown when the status command is requested. Message vars:
 * 		* @name: The name of the person who requested the message.
 * 		* @total: The current total of his/her account.
 * 		* @currency: The currency name. Plural form is computed automatically.
 * - initialAmount: The initial amount each viewer/chatter is given when initially joining the chat.
 *
 * These config vars are definable in a global manner using the config namespace "plugin.Currency",
 * and per-channel, using the config namespaces "plugin.Currency.channel.<channel-name>". If one config parameter
 * misses from the per-channel config, then it is taken from the global config.
 * It is advised to define both, to avoid having situations where the default ones are used.
 */
class Currency extends Plugin
{
	private $accounts = array(); // Accounts, by channel
	private $currencyName = array(); // Money names, by channel
	private $currencyNamePlural = array(); // Money plural name, by channel
	private $statusCommand = array(); // Money status command names, by channel
	private $statusMessage = array(); // Money status command message, by channel
	private $initialAmount = array(); // Initial money amount, by channel
	private $globalCurrencyName; // Global money name, used if no money name is overridden for the channel
	private $globalCurrencyNamePlural; // Global money plural name, ditto
	private $globalStatusCommand; // Global money command, ditto
	private $globalStatusMessage; // Global money message, ditto
	private $globalInitialAmount; // Global initial money amount, ditto

	const DEFAULT_CURRENCY_NAME = 'coin';
	const DEFAULT_CURRENCY_NAME_PLURAL = 'coins';
	const DEFAULT_STATUS_COMMAND = 'coins';
	const DEFAULT_STATUS_MESSAGE  = '@name, you currently have @total @currency';
	const DEFAULT_INITIAL_AMOUNT = 0;

	/** Plugin initialization */
	public function init()
	{
		if(!empty($this->data->accounts))
			$this->accounts = $this->data->accounts->toArray();

		$this->reloadConfig();
	}

	public function SystemEventConfigUpdate()
	{
		$this->config = HedgeBot::getInstance()->config->get('plugin.Currency');
		$this->reloadConfig();
	}

	/** Initializes an account on join */
	public function ServerJoin($command)
	{
		if(!empty($this->accounts[$command['channel']][$command['nick']]))
			return;

		$serverConfig = Server::getConfig();
		if(strtolower($command['nick']) == strtolower($serverConfig['name']))
			return;

		$initialAmount = 0;
		if(!empty($this->initialAmount[$command['channel']]))
			$initialAmount = $this->initialAmount[$command['channel']];
		else
			$initialAmount = $this->globalInitialAmount;

		$this->accounts[$command['channel']][$command['nick']] = $initialAmount;
		$this->data->set('accounts', $this->accounts);
	}

	/** Does the same as above, if an user talks before the join notice hase come over */
	public function ServerPrivmsg($command)
	{
		$this->ServerJoin($command);

		$cmd = explode(' ', $command['message']);
		if($cmd[0][0] == '!')
		{
			$cmd = substr($cmd[0], 1);
			if(!empty($this->statusCommand[$command['channel']]))
			{
				if($this->statusCommand[$command['channel']] == $cmd)
					$this->RealCommandAccount($command, array());
			}
			elseif($this->globalStatusCommand == $cmd)
				$this->RealCommandAccount($command, array());
		}

	}

	/** Mod function: adds a given amount of money to a given player */
	public function CommandGive($param, $args)
	{
		// Check rights
		if(!$param['moderator'])
			return;

		// Check that arguments are there
		if(count($args) < 2)
			return IRC::message($param['channel'], 'Insufficient parameters.');

		// Lowercasing the username
		$nick = strtolower($args[0]);

		// Check that the account exists
		if(!isset($this->accounts[$param['channel']][$nick]))
			return IRC::message($param['channel'], 'Unknown user.');

		$this->accounts[$param['channel']][$nick] += (int) $args[1];
		$this->data->set('accounts', $this->accounts);
	}

	/** Mod function: show another user's status */
	public function CommandCheck($param, $args)
	{
		if(!$param['moderator'])
			return;

		$channelUsers = IRC::getChannelUsers($param['channel']);
		if(isset($this->accounts[$param['channel']][$nick]))
		{
			$message = $this->formatMessage("Admin check(@name): @total @currency", $param['channel'], $param['nick']);
			IRC::message($param['channel'], $message);
		}
	}

	/** Mod function: removes a given amount of money from a given player */
	public function CommandTake($param, $args)
	{
		// Check rights
		if(!$param['moderator'])
			return;

		// Check that arguments are there
		if(count($args) < 2)
			return IRC::message($param['channel'], 'Insufficient parameters.');

		// Lowercasing the username
		$nick = strtolower($args[0]);

		// Check that the account exists
		if(!isset($this->accounts[$param['channel']][$nick]))
			return IRC::message($param['channel'], 'Unknown user.');

		// Perform account operations
		$sum = (int) $args[1];

		if($this->accounts[$param['channel']][$nick] - $sum > 0)
			$this->accounts[$param['channel']][$nick] -= $sum;
		else
			$this->accounts[$param['channel']][$nick] = 0;

		$this->data->set('accounts', $this->accounts);
	}

	/** Real account show command, shows the current amount of currency for the user */
	public function RealCommandAccount($param, $args)
	{
		$message = null;
		if(!empty($this->statusMessage[$param['channel']]))
			$message = $this->statusMessage[$param['channel']];
		else
			$message = $this->globalStatusMessage;

		IRC::message($param['channel'], $this->formatMessage($message, $param['channel'], $param['nick']));
	}

	/** Reloads configuration variables */
	public function reloadConfig()
	{
		$parameters = array('currencyName', 'currencyNamePlural', 'statusCommand', 'statusMessage', 'initialAmount');

		$this->globalCurrencyName = self::DEFAULT_CURRENCY_NAME;
		$this->globalCurrencyNamePlural = self::DEFAULT_CURRENCY_NAME_PLURAL;
		$this->globalStatusCommand = self::DEFAULT_STATUS_COMMAND;
		$this->globalStatusMessage = self::DEFAULT_STATUS_MESSAGE;
		$this->globalInitialAmount = self::DEFAULT_INITIAL_AMOUNT;

		// Handling global config parameters
		foreach($this->config as $name => $value)
		{
			$varParameter = 'global'. ucfirst($name);
			if(in_array($name, $parameters))
				$this->$varParameter = $value;
		}

		// Handling per-channel config parameters
		if(!empty($this->config['channel']))
		{
			foreach($this->config['channel'] as $channel => $configElement)
			{
				foreach($configElement as $name => $value)
				{
					if(in_array($name, $parameters))
						$this->{$name}[$channel] = $configElement[$name];
				}
			}
		}
	}

	/** Formats a currency message, with plural forms and everything. */
	private function formatMessage($message, $channel, $name)
	{
		if(!empty($this->currencyName[$channel]))
		{
			$currencyName = $this->currencyName[$channel];
			$currencyNamePlural = $this->currencyNamePlural[$channel];
		}
		else
		{
			$currencyName = $this->globalCurrencyName;
			$currencyNamePlural = $this->globalCurrencyNamePlural;
		}

		$message = str_replace(	array(	'@name',
										'@total',
										'@currency'),
								array(	$name,
										$this->accounts[$channel][$name],
										$this->accounts[$channel][$name] > 1 ?
											$currencyNamePlural
										:	$currencyName),
								$message);

		return $message;
	}
}
