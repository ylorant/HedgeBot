<?php
namespace HedgeBot\Core;

use HedgeBot\Core\API\ServerList;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Config;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\Plugins\PluginManager;
use HedgeBot\Core\Server\ServerInstance;
use HedgeBot\Core\Data\FileProvider;
use HedgeBot\Core\Data\Provider;
use HedgeBot\Core\Data\ObjectAccess;
use HedgeBot\Core\Server\CoreEvents;
use HedgeBot\Core\Twitch\Kraken;
use HedgeBot\Core\Tikal\Server as TikalServer;
use HedgeBot\Core\Tikal\CoreAPI as TikalCoreAPI;

class HedgeBot
{
	public $config;
	public $data;
	public $servers;
	private static $_lastError;
	public static $verbose;
	public $plugins;
	public $initialized;
	public $tikalServer;

	private $_run;

	private static $instance;

	const VENDOR_NAMESPACE = "HedgeBot"; // Base "vendor" namespace for autoloading
	const DEFAULT_CONFIG_DIR = "./conf";

	public function init()
	{
		HedgeBot::$instance = $this;
		HedgeBot::$verbose = 1;

		$options = $this->parseCLIOptions();

		if(isset($options['verbose']) || isset($options['v']))
			HedgeBot::$verbose = 2;

		$configDir = self::DEFAULT_CONFIG_DIR;
		if(isset($options['config']) || isset($options['c']))
			$configDir = !empty($options['config']) ? $options['config'] : $options['c'];

		HedgeBot::message('Starting HedgeBot...');
		HedgeBot::message('Starting in verbose mode.', null, E_DEBUG);

		ServerList::setMain($this);

		$this->initialized = false;

		HedgeBot::message('Bootstrapping storage...');

		$fileProvider = new FileProvider();
		$this->config = new ObjectAccess($fileProvider);
		$connected = $fileProvider->connect($configDir);

		if(!$connected)
			return HedgeBot::message('Cannot locate configuration directory', null, E_ERROR);

		// Loading real storage now
		$storageConfig = $this->config->storage->config;
		$dataStorage = $this->config->storage->data;

		HedgeBot::message('Loading main storages...');

		$storageLoaded = $this->loadStorage($this->config, $storageConfig);
		if(!$storageLoaded)
			return HedgeBot::message('Cannot load config storage.', null, E_ERROR);

		$storageLoaded = $this->loadStorage($this->data, $dataStorage);
		if(!$storageLoaded)
			return HedgeBot::message('Cannot load data storage.', null, E_ERROR);

		Data::setObject($this->data);
		Config::setObject($this->config);

		// Setting verbosity
		if(HedgeBot::$verbose == 1 && !empty($this->config->general->verbosity))
			HedgeBot::$verbose = $this->config->general->verbosity;

		// Initializing Twitch API connector
		HedgeBot::message("Discovering available Twitch services...", null, E_DEBUG);
		$kraken = new Kraken();
		$kraken->discoverServices();
		Twitch::setObject($kraken);

		// Initializing "Tikal" API server
		if(!empty($this->config->tikal) && $this->config->tikal->enabled == true)
		{
			HedgeBot::message("Initializing Tikal API server...");
			$this->tikalServer = new TikalServer($this->config->tikal);
			Tikal::setObject($this->tikalServer);

			// Binding core API to the server
			Tikal::addEndpoint('/', new TikalCoreAPI());
		}

		// Loading plugins
		HedgeBot::message('Loading plugins...');

		$pluginList = $this->config->general->plugins;
		if(empty($pluginList))
			return HedgeBot::message('No plugin to load. This bot is pretty much useless without plugins.', null, E_ERROR);

		$pluginList = explode(',', $pluginList);
		$pluginList = array_map('trim', $pluginList);

		$this->plugins = new PluginManager();
		Plugin::setManager($this->plugins);

		$this->plugins->loadPlugins($pluginList);

		// Loading core events manager
		$coreEvents = new CoreEvents($this->plugins, $this);

		$servers = $this->config->get('servers');

		if(empty($servers))
			return HedgeBot::message('No server to connect to. Stopping.', null, E_ERROR);

		HedgeBot::message('Loading servers...');
		$loadedServers = 0;
		foreach($servers as $name => $server)
		{
			HedgeBot::message('Loading server $0', array($name));
			$this->servers[$name] = new ServerInstance();
			IRC::setObject($this->servers[$name]);
			Server::setObject($this->servers[$name]);
			$loaded = $this->servers[$name]->load($server);

			if(!$loaded)
			{
				HedgeBot::message('Cannot connect to server $0.', array($name), E_WARNING);
				unset($this->servers[$name]);
				continue;
			}

			$loadedServers++;
		}

		if($loadedServers == 0)
			return HedgeBot::message('Cannot connect to any servers, stopping.', null, E_ERROR);

		HedgeBot::message('Loaded servers.');

		// Start the Tikal API Server
		if(!empty($this->tikalServer))
			$this->tikalServer->start();

		return true;
	}

	/**
	 * Autoloads a class depending on its namespace. Kinda follows PSR-4 ?
	 * \param $class the class trying to be loaded.
	 * \return null.
	 */
	public static function autoload($class)
	{
		$components = explode('\\', $class);
		$currentDir = '';
		$path = null;

		// Remove the vendor namespace if necessary
		if($components[0] == self::VENDOR_NAMESPACE)
			array_shift($components);
		else // It's not from the vendor namespace, it should be an external lib then.
			$currentDir .= "lib/";

		foreach($components as $comp)
		{
			if(is_dir($currentDir. $comp))
				$currentDir .= $comp."/";
			elseif(is_file($currentDir.$comp.".class.php"))
			{
				$path = $currentDir. $comp. ".class.php";
				break;
			}
			else
				return;
		}

		// The path is complete, we load the class.
		if(!empty($path))
			require $path;
	}

	public static function getInstance()
	{
		return self::$instance;
	}

	public static function parseBool($var)
	{
		if(in_array(strtolower($var), array('1', 'on', 'true', 'yes')))
			return TRUE;
		else
			return FALSE;
	}

	public static function parseRBool($var)
	{
		if(in_array(strtolower($var), array('0', 'off', 'false', 'no')))
			return FALSE;
		else
			return TRUE;
	}

	public static function message($message, $args = array(), $type = E_NOTICE)
	{
		$returnVal = true;
		$verbosity = 1;
		$prefix = "";
		//Getting type string
		switch($type)
		{
			case E_NOTICE:
			case E_USER_NOTICE:
				$prefix = 'Notice';
				break;
			case E_WARNING:
			case E_USER_WARNING:
			$prefix = 'Warning';
				if(PHP_OS == 'Linux') //If we are on Linux, we use colors
					echo "\033[0;33m";
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$prefix = 'Error';
				$force = TRUE;
				$verbosity = 0;
				if(PHP_OS == 'Linux') //If we are on Linux, we use colors (yes, I comment twice)
					echo "\033[0;31m";
				break;
			case E_DEBUG:
				$prefix = 'Debug';
				$verbosity = 2;
				break;
			default:
				$prefix = 'Unknown';
		}

		//Parsing message vars
		if(!is_array($args))
			$args = array($args);
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);

		if(in_array($type, array(E_USER_ERROR, E_ERROR, E_WARNING, E_USER_WARNING)))
		{
			$returnVal = false;
			HedgeBot::$_lastError = $message;
		}

		//Put it in log, if is opened
		if(HedgeBot::$verbose >= $verbosity)
		{
			echo date("m/d/Y h:i:s A").' -- '.$prefix.' -- '.$message.PHP_EOL;
			if(PHP_OS == 'Linux')
				echo "\033[0m";
		}

		return $returnVal;
	}

	public function dumpArray($array)
	{
		if(!is_array($array))
			$array = array(gettype($array) => $array);

		$return = array();

		foreach($array as $id => $el)
		{
			if(is_array($el))
				$return[] = $id.'=Array';
			elseif(is_object($el))
				$return[] = $id.'='.get_class($el).' object';
			elseif(is_string($el))
				$return[] = $id.'="'.$el.'"';
			elseif(is_bool($el))
				$return[] = $id.'='.($el ? 'TRUE' : 'FALSE');
			else
				$return[] = $id.'='.(is_null($el) ? 'NULL' : $el);
		}

		return join(', ', $return);
	}

	public static function getServerName($object)
	{
		$self = HedgeBot::getInstance();
		foreach($self->servers as $name => $server)
		{
			if($server == $object)
				return $name;
		}

		return NULL;
	}

	public function parseCLIOptions()
	{
		// Setting up options for CLI parsing
		$shortOpts = array(
			"c:",
			"v"
		);

		$longOps = array(
			"config::",
			"verbose"
		);

		return getopt(join('', $shortOpts), $longOps);
	}

	public function loadStorage(&$target, $parameters)
	{
		// Getting storage type
		$storageName = $parameters->type;
		if(empty($storageName))
		{
			HedgeBot::message('Cannot load storage: storage type undefined.', null, E_ERROR);
			return false;
		}

		// Checking that requested storage exists.
		$providerClass = Provider::resolveStorage($storageName);
		if($providerClass === false)
		{
			HedgeBot::message('Cannot load storage "$0": Storage does not exist.', array($storageName), E_ERROR);
			return false;
		}

		// Try to load the storage
		$provider = new $providerClass();
		$target = new ObjectAccess($provider);
		$connected = $provider->connect($parameters);

		if(!$connected)
		{
			HedgeBot::message('Cannot connect to storage.', null, E_ERROR);
			return false;
		}

		// Initializing readonly state, present for all storage types
		if(!empty($parameters->readonly))
			$provider->readonly = true;

		return true;
	}

	/**
	 * Main loop.
	 * @return TRUE.
	 */
	public function run()
	{
		$this->_run = TRUE;

		while($this->_run)
		{
			foreach($this->servers as $name => $server)
			{
				//Setting servers for static inner API
				IRC::setObject($this->servers[$name]->getIRC());
				Server::setObject($this->servers[$name]);

				$this->servers[$name]->step();
				usleep(1000);
			}

			if($this->initialized)
				$this->plugins->callAllRoutines();

			// Process Tikal API calls if there are some
			if(!empty($this->tikalServer))
				$this->tikalServer->process();
		}

		foreach($this->servers as $name => $server)
			$server->disconnect();

		foreach($this->plugins->getLoadedPlugins() as $plugin)
			$this->plugins->unloadPlugin($plugin);

		return TRUE;
	}

	public function stop()
	{
		$this->_run = false;
	}
}
