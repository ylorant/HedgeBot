<?php
namespace HedgeBot\Plugins\Currency;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\IRC;

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

	/** Plugin initialization */
	public function init()
	{
		$this->reloadConfig();
	}

	public function SystemEventConfigUpdate()
	{
		$this->config = HedgeBot::getInstance()->config->get('plugin.'.$pluginName);
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
		elseif(!empty($this->globalInitialAmount))
			$initialAmount = $this->globalInitialAmount;

		$this->accounts[$command['channel']][$command['nick']] = $initialAmount;
	}

	/** Does the same as above, if an user talks before the join notice hase come over */
	public function ServerPrivmsg($command)
	{
		$this->ServerJoin($command);

		$cmd = explode(' ', $command['message']);
		if($cmd[0][0] == '!')
		{
			$cmd = substr($cmd[0], 1);
			if(!empty($this->statusCommand[$command['channel']]) && $this->statusCommand[$command['channel']] == $cmd)
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

		// Check that the account exists
		if(!isset($this->accounts[$param['channel']][$args[0]]))
			return IRC::message($param['channel'], 'Unknown user.');

		$this->accounts[$param['channel']][$args[0]] += (int) $args[1];
	}

	/** Mod function: removes a given amount of money from a given player */
	public function CommandTake()
	{
		// Check rights
		if(!$param['moderator'])
			return;

		// Check that arguments are there
		if(count($args) < 2)
			return IRC::message($param['channel'], 'Insufficient parameters.');

		// Check that the account exists
		if(!isset($this->accounts[$param['channel']][$args[0]]))
			return IRC::message($param['channel'], 'Unknown user.');

		// Perform account operations
		$sum = (int) $args[1];

		if($this->accounts[$param['channel']][$args[0]] - $sum > 0)
			$this->accounts[$param['channel']][$args[0]] -= $sum;
		else
			$this->accounts[$param['channel']][$args[0]] = 0;
	}

	/** Utility: Loads accounts from account file defined in config */
	public function loadAccounts()
	{
		// if(!empty($this->config['AccountFile']))
			// $this->accounts = ;
		// else
			// HedgeBot::message('Can\'t load accounts, no "AccountFile" parameter specified in config.', null, E_WARNING);
	}

	/** Utility: Saves accounts from account file defined in config */
	public function saveAccounts()
	{
		if(!empty($this->config['AccountFile']))
			file_put_contents($this->config['AccountFile'], HedgeBot::generateINIStringRecursive($this->accounts));
		else
			HedgeBot::message('Can\'t load accounts, no "AccountFile" parameter specified in config.', null, E_WARNING);
	}

	/** Real account show command, shows the current amount of currency for the user */
	public function RealCommandAccount($param, $args)
	{
		$message = null;
		if(!empty($this->statusMessage[$param['channel']]))
			$message = $this->statusMessage[$param['channel']];
		elseif(!empty($this->globalStatusMessage))
			$message = $this->globalStatusMessage;
		else
			$message = '@name, you currently have @total @currency';

		if(!empty($this->currencyName[$channel]))
		{
			$currencyName = $this->currencyName[$channel];
			$currencyNamePlural = $this->currencyNamePlural[$channel];
		}
		elseif(!empty($this->globalCurrencyName))
		{
			$currencyName = $this->globalCurrencyName;
			$currencyNamePlural = $this->globalCurrencyNamePlural;
		}
		else
		{
			$currencyName = 'point';
			$currencyNamePlural = 'points';
		}

		$message = str_replace(	array(	'@name',
										'@total',
										'@currency'),
								array(	$param['nick'],
										$this->accounts[$param['channel']][$param['nick']],
										$this->accounts[$param['channel']][$param['nick']] > 1 ?
											$currencyNamePlural
										:	$currencyName),
								$message);

		IRC::message($param['channel'], $message);
	}

	/** Reloads configuration variables */
	public function reloadConfig()
	{
		$parameters = array('currencyName', 'currencyNamePlural', 'statusCommand', 'statusMessage', 'initialAmount');

		if(!empty($this->config['channel']))
		{
			foreach($this->config['channel'] as $channel => $configElement)
			{
				$this->currencyName[$channel] = $configElement['currencyName'];
				$this->currencyNamePlural[$channel] = $configElement['currencyNamePlural'];
				$this->statusCommand[$channel] = $configElement['statusCommand'];
				$this->statusMessage[$channel] = $configElement['statusMessage'];
				$this->initialAmount[$channel] = $configElement['initialAmount'];
			}
		}
		elseif(!empty($this->config['currencyName']) && !empty($this->config['currencyNamePlural'])&& !empty($this->config['statusCommand']))
		{
			$this->globalCurrencyName = $this->config['currencyName'];
			$this->globalCurrencyNamePlural = $this->config['currencyNamePlural'];
			$this->globalStatusCommand = $this->config['statusCommand'];
			$this->globalStatusMessage = $this->config['statusMessage'];
			$this->globalInitialAmount = $this->config['initialAmount'];
		}
	}
}
