<?php

namespace HedgeBot\Core;

use HedgeBot\Core\API\ServerList;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Config;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Security;
use HedgeBot\Core\API\Tikal;
use HedgeBot\Core\API\Twitch;
use HedgeBot\Core\Plugins\PluginManager;
use HedgeBot\Core\Server\ServerInstance;
use HedgeBot\Core\Server\CoreEvents;
use HedgeBot\Core\Data\IniFileProvider;
use HedgeBot\Core\Data\Provider;
use HedgeBot\Core\Data\ObjectAccess;
use HedgeBot\Core\Security\AccessControlManager;
use HedgeBot\Core\Tikal\Server as TikalServer;
use HedgeBot\Core\Tikal\Endpoint\CoreEndpoint as TikalCoreEndpoint;
use HedgeBot\Core\Tikal\Endpoint\PluginEndpoint as TikalPluginEndpoint;
use HedgeBot\Core\Tikal\Endpoint\SecurityEndpoint as TikalSecurityEndpoint;
use HedgeBot\Core\Tikal\Endpoint\ServerEndpoint as TikalServerEndpoint;
use HedgeBot\Core\Tikal\Endpoint\TwitchEndpoint as TikalTwitchEndpoint;
use HedgeBot\Core\Tikal\Endpoint\StoreEndpoint as TikalStoreEndpoint;
use HedgeBot\Core\Store\Store;
use HedgeBot\Core\API\Store as StoreAPI;
use HedgeBot\Core\Events\Relay\RelayClient;
use HedgeBot\Core\Service\Twitch\TwitchService;
use HedgeBot\Core\Service\Twitch\TwitchLogger;

define('E_DEBUG', 32768);

/**
 * Class HedgeBot
 * @package HedgeBot\Core
 */
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
    public $accessControl;
    public $store;
    public $relayClient;

    private $run;

    private static $instance;
    private static $env;

    const VENDOR_NAMESPACE = "HedgeBot"; // Base "vendor" namespace for autoloading
    const DEFAULT_CONFIG_DIR = "./conf";

    /**
     * Initializes the bot, connects the storages, loads the plugins, load peripheral services...
     *
     * TODO: Instead of initializing services like that, why not do a service container of some sorts ?
     * 
     * @param array $options The options the bot are started with. See StartBotCommand::configure() to see options.
     *
     * @return bool True if the bot initialized correctly, false if not.
     * @throws \ReflectionException
     * @see StartBotCommand::configure()
     */
    public function init($configDir = null)
    {
        HedgeBot::$instance = $this;

        HedgeBot::message('Starting HedgeBot...');
        HedgeBot::message('Starting in verbose mode.', null, E_DEBUG);
        HedgeBot::message('Current environment: $0', [ENV], E_DEBUG);

        ServerList::setMain($this);

        $this->initialized = false;

        HedgeBot::message('Bootstrapping storage...');

        // Bootstrapping storages consists of loading the config and data storage configuration from
        // config files in the given configuration directory.
        $fileProvider = new IniFileProvider();
        $this->config = new ObjectAccess($fileProvider);
        $connected = $fileProvider->connect($configDir ?? self::DEFAULT_CONFIG_DIR);

        if (!$connected) {
            return HedgeBot::message('Cannot locate configuration directory', null, E_ERROR);
        }

        // Loading real storage now
        $storageConfig = $this->config->storage->config;
        $dataStorage = $this->config->storage->data;

        HedgeBot::message('Loading main storages...');

        $storageLoaded = $this->loadStorage($this->config, $storageConfig);
        if (!$storageLoaded) {
            return HedgeBot::message('Cannot load config storage.', null, E_ERROR);
        }

        $storageLoaded = $this->loadStorage($this->data, $dataStorage);
        if (!$storageLoaded) {
            return HedgeBot::message('Cannot load data storage.', null, E_ERROR);
        }

        Data::setObject($this->data);
        Config::setObject($this->config);

        // Setting verbosity according to config if not overriden by console
        if (empty(HedgeBot::$verbose) && isset($this->config->general->verbosity)) {
            HedgeBot::$verbose = $this->config->general->verbosity;
        }

        // Loading internal store
        $this->store = new Store();
        StoreAPI::setObject($this->store);

        // Initializing access control manager
        $this->accessControl = new AccessControlManager($this->data->getProvider());
        Security::setObject($this->accessControl);

        // Initializing Twitch API connector
        HedgeBot::message("Initializing Twitch API client...", null, E_DEBUG);

        $clientID = $this->config->get('twitch.auth.clientId');
        $clientSecret = $this->config->get('twitch.auth.clientSecret');

        $twitchService = new TwitchService($clientID, $clientSecret, $this->data->getProvider());
        $twitchService->getClient(TwitchService::CLIENT_TYPE_HELIX)->setLogger(new TwitchLogger());
        $twitchService->getClient(TwitchService::CLIENT_TYPE_AUTH)->setLogger(new TwitchLogger());
        Twitch::setObject($twitchService);

        // Initializing "Tikal" API server
        if (!empty($this->config->tikal) && $this->config->tikal->enabled == true) {
            HedgeBot::message("Initializing Tikal API server...");
            $this->tikalServer = new TikalServer($this->config->tikal);
            Tikal::setObject($this->tikalServer);

            // Binding core API to the server
            Tikal::addEndpoint('/', new TikalCoreEndpoint());
            Tikal::addEndpoint('/plugin', new TikalPluginEndpoint());
            Tikal::addEndpoint('/security', new TikalSecurityEndpoint());
            Tikal::addEndpoint('/server', new TikalServerEndpoint());
            Tikal::addEndpoint('/twitch', new TikalTwitchEndpoint());
            Tikal::addEndpoint('/store', new TikalStoreEndpoint());
        }

        // Loading plugins
        HedgeBot::message('Loading plugins...');

        $pluginList = $this->config->general->plugins;
        if (empty($pluginList)) {
            return HedgeBot::message(
                'No plugin to load. This bot is pretty much useless without plugins.',
                null,
                E_ERROR
            );
        }

        $pluginList = explode(',', $pluginList);
        $pluginList = array_map('trim', $pluginList);

        $this->plugins = new PluginManager($this->config->general->pluginsDirectory);
        Plugin::setManager($this->plugins);

        $this->plugins->loadPlugins($pluginList);

        // Loading core events handler
        $coreEvents = new CoreEvents();

        $servers = $this->config->get('servers');

        if (empty($servers)) {
            return HedgeBot::message('No server to connect to. Stopping.', null, E_ERROR);
        }

        HedgeBot::message('Loading servers...');
        $loadedServers = 0;
        foreach ($servers as $name => $server) {
            HedgeBot::message('Loading server $0', array($name));
            $this->servers[$name] = new ServerInstance();
            IRC::setObject($this->servers[$name]);
            Server::setObject($this->servers[$name]);
            $loaded = $this->servers[$name]->load($server);

            if (!$loaded) {
                HedgeBot::message('Cannot connect to server $0.', array($name), E_WARNING);
                unset($this->servers[$name]);
                continue;
            }

            $loadedServers++;
        }

        if ($loadedServers == 0) {
            return HedgeBot::message('Cannot connect to any servers, stopping.', null, E_ERROR);
        }

        HedgeBot::message('Loaded servers.');

        // Start the Tikal API Server
        if (!empty($this->tikalServer)) {
            $this->tikalServer->start();
        }

        // Connect to the event relay server if needed
        if(!empty($this->config->relay)) {
            HedgeBot::message('Connecting to the event relay...');

            $relayType = $this->config->relay->type ?? "";
            $relayClient = RelayClient::resolveClient($relayType);

            if(empty($relayClient)) {
                HedgeBot::message("Cannot load event relay client type $0", $relayType, E_WARNING);
            }

            $relayClient->initialize($this->config->relay->toArray());
            $connected = $relayClient->connect();

            if($connected) {
                HedgeBot::message('Connected to the Socket.IO relay.', []);
                $this->relayClient = $relayClient;
                $this->plugins->setRelayClient($relayClient);
            } else {
                HedgeBot::message('Cannot connect to Socket.IO relay.', [], E_WARNING);
            }
        }

        return true;
    }

    /**
     * Autoloads a class depending on its namespace. Kinda follows PSR-4 ?
     *
     * @param string $class the class trying to be loaded.
     * @return null
     */
    public static function autoload($class)
    {
        $components = explode('\\', $class);
        $currentDir = '';
        $path = null;

        // Remove the vendor namespace if necessary
        if ($components[0] == self::VENDOR_NAMESPACE) {
            array_shift($components);
        } else { // It's not from the vendor namespace, it should be an external lib then.
            $currentDir .= "lib/";
        }

        foreach ($components as $comp) {
            if (is_dir($currentDir . $comp)) {
                $currentDir .= $comp . "/";
            } elseif (is_file($currentDir . $comp . ".php")) {
                $path = $currentDir . $comp . ".php";
                break;
            } else {
                return;
            }
        }

        // The path is complete, we load the class.
        if (!empty($path)) {
            require $path;
        }
    }

    /**
     * Returns true if the stream supports colorization.
     *
     * Colorization is disabled if not supported by the stream:
     *
     *  -  Windows without Ansicon, ConEmu or Mintty
     *  -  non tty consoles
     *
     * This function was taken shamelessly from the Symfony Console component.
     *
     * @return bool true if the stream supports colorization, false otherwise
     * @author Fabien Potencier <fabien@symfony.com>
     */
    protected static function hasColorSupport()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI') || 'xterm' === getenv('TERM');
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    /**
     * @return mixed
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Sets the bot environment.
     * 
     * @param string $env The environment.
     * @return void
     */
    public static function setEnv(string $env)
    {
        self::$env = $env;
    }

    /**
     * Gets the bot environment.
     * 
     * @return string The bot environment.
     */
    public static function getEnv()
    {
        return self::$env;
    }

    /**
     * Checks that the given object has the given properties in it, and that they are not null.
     * 
     * @param object $object The object to check the properties of.
     * @param array $properties An array containing the property names to check the existence of.
     * 
     * @return bool True if all the properties exist, false if not.
     */
    public static function objectPropertiesExist($object, array $properties)
    {
        foreach($properties as $prop) {
            if(!isset($object->$prop)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks that the given array has the given keys in it, and that they aren't null.
     * 
     * @param array $array The array to check the keys of.
     * @param array $keys An array containing the keys to check the existence of.
     * 
     * @return bool True if all the keys exist, false if not.
     */
    public static function arrayKeysExist(array $array, array $keys)
    {
        foreach($keys as $key) {
            if(!isset($array[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $var
     * @return bool
     */
    public static function parseBool($var)
    {
        if (in_array(strtolower($var), array('1', 'on', 'true', 'yes'))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $var
     * @return bool
     */
    public static function parseRBool($var)
    {
        if (in_array(strtolower($var), array('0', 'off', 'false', 'no'))) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Outputs a message on the console. This is the main way this bot has to communicate with the user.
     * Every message can have a type/gravity assigned to it and, depending on the verbosity settings, this
     * gravity will be allowed to be displayed by the bot. Also, the message can be written in a template-like
     * fashion, using a `$keyname` syntax, where "keyname" is the key of the element to be inserted in the $args
     * array. This has been made to allow for future translations of the bot messages.
     *
     * Depending on the console capabilities, some messages will be colored.
     *
     * TODO: Use a real logger service, to allow logging these into a file & more.
     *
     * @param string $message The message to output. You can use tokens in the form `$keyname` to substitute vars.
     * @param array $args The vars to substitute the tokens with in the message.
     * @param int $type The type/gravity of the message. Verbosity will make the message show or not depending
     *                          on this setting.
     *
     * @return bool True for non-error messages (notice, debug) and false for error-level messages (warning, error).
     */
    public static function message($message, $args = array(), $type = E_NOTICE)
    {
        $returnVal = true;
        $verbosity = 1;

        $hasColors = self::hasColorSupport();
        $colorChanger = '';

        //Getting type string
        switch ($type) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $prefix = 'Notice';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $prefix = 'Warning';
                if ($hasColors) { // If we are on Linux, we use colors
                    $colorChanger = "\033[0;33m";
                }
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $prefix = 'Error';
                $verbosity = 0;
                if ($hasColors) { // If we are on Linux, we use colors (yes, I comment twice)
                    $colorChanger = "\033[0;31m";
                }
                break;
            case E_DEBUG:
                $prefix = 'Debug';
                $verbosity = 2;
                $colorChanger = "\033[38;5;245m";
                break;
            default:
                $prefix = 'Unknown';
        }

        //Parsing message vars
        if (!is_array($args)) {
            $args = array($args);
        }
        foreach ($args as $id => $value) {
            $message = str_replace('$' . $id, $value, $message);
        }

        if (in_array($type, array(E_USER_ERROR, E_ERROR, E_WARNING, E_USER_WARNING))) {
            $returnVal = false;
            HedgeBot::$_lastError = $message;
        }

        //Put it in log, if is opened
        if (HedgeBot::$verbose >= $verbosity) {
            $message = date("m/d/Y h:i:s A") . ' -- ' . $prefix . ' -- ' . $message . PHP_EOL;
            
            if ($hasColors) { // If we are on Linux, we use colors
                echo $colorChanger. $message. "\033[0m";
            } else {
                echo $message;
            }
        }

        return $returnVal;
    }

    /**
     * @param $array
     * @return string
     */
    public function dumpArray($array)
    {
        if (!is_array($array)) {
            $array = array(gettype($array) => $array);
        }

        $return = array();

        foreach ($array as $id => $el) {
            if (is_array($el)) {
                $return[] = $id . '=Array';
            } elseif (is_object($el)) {
                $return[] = $id . '=' . get_class($el) . ' object';
            } elseif (is_string($el)) {
                $return[] = $id . '="' . $el . '"';
            } elseif (is_bool($el)) {
                $return[] = $id . '=' . ($el ? 'TRUE' : 'FALSE');
            } else {
                $return[] = $id . '=' . (is_null($el) ? 'NULL' : $el);
            }
        }

        return join(', ', $return);
    }

    /**
     * @param $object
     * @return int|null|string
     */
    public static function getServerName($object)
    {
        $self = HedgeBot::getInstance();
        foreach ($self->servers as $name => $server) {
            if ($server == $object) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array
     */
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

    /**
     * @param $target
     * @param $parameters
     * @return bool
     * @throws \ReflectionException
     */
    public function loadStorage(&$target, $parameters)
    {
        // Getting storage type
        $storageName = $parameters->type;
        if (empty($storageName)) {
            HedgeBot::message('Cannot load storage: storage type undefined.', null, E_ERROR);
            return false;
        }

        // Checking that requested storage exists.
        $providerClass = Provider::resolveStorage($storageName);
        if ($providerClass === false) {
            HedgeBot::message('Cannot load storage "$0": Storage does not exist.', array($storageName), E_ERROR);
            return false;
        }

        // Try to load the storage
        $provider = new $providerClass();
        $target = new ObjectAccess($provider);
        $connected = $provider->connect($parameters);

        if (!$connected) {
            HedgeBot::message('Cannot connect to storage.', null, E_ERROR);
            return false;
        }

        // Initializing readonly state, present for all storage types
        if (!empty($parameters->readonly)) {
            $provider->readonly = true;
        }

        return true;
    }

    /**
     * Main loop
     *
     * @return bool
     */
    public function run()
    {
        $this->run = true;

        while ($this->run) {
            foreach ($this->servers as $name => $server) {
                //Setting servers for static inner API
                IRC::setObject($this->servers[$name]->getIRC());
                Server::setObject($this->servers[$name]);

                // Only try to process server if it's connected
                if ($this->servers[$name]->isConnected()) {
                    $this->servers[$name]->step();
                }

                usleep(1000);
            }

            if ($this->initialized) {
                $this->plugins->callAllRoutines();
            }

            // Process Tikal API calls if there are some
            if (!empty($this->tikalServer)) {
                $this->tikalServer->process();
            }

            if (!empty($this->relayClient) && $this->relayClient->isAvailable()) {
                $this->relayClient->keepAlive();
            }
            
            usleep(1000);
        }

        foreach ($this->servers as $name => $server) {
            $server->disconnect();
        }

        foreach ($this->plugins->getLoadedPlugins() as $plugin) {
            $this->plugins->unloadPlugin($plugin);
        }

        return true;
    }

    public function stop()
    {
        $this->run = false;
    }
}
