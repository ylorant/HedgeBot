<?php

namespace HedgeBot\Core\Console;

use Symfony\Component\Console\Application;
use HedgeBot\Core\Data\IniFileProvider;
use HedgeBot\Core\Data\ObjectAccess;
use HedgeBot\Core\HedgeBot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use RecursiveIteratorIterator;
use RegexIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use RuntimeException;
use HedgeBot\Core\Plugins\PluginManager;
use HedgeBot\Core\API\Config;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Store as StoreAPI;
use HedgeBot\Core\Console\Bot\StartBotCommand;
use HedgeBot\Core\Store\Store;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Console provider, provides the console application with all the commands it can
 * find in the bot files.
 *
 * This class will search recursively in its own namespace for console commands,
 * and will check on loaded plugins (according to configuration) for additional commands.
 */
class ConsoleProvider
{
    /** @var ObjectAccess $data The data storage provider, encapsuled in an ObjectAccess wrapper */
    protected $data;
    /** @var ObjectAccess $config The configuration storage provider, encapsuled in an ObjectAccess wrapper */
    protected $config;
    /** @var Store The data store. */
    protected $store;
    /** @var string $arguments The arguments passed to the console command */
    protected $arguments;

    /** @var string If this var is set to true, then most of the loading will be skipped and only the core commands will be loaded */
    protected $bareInit;

    protected static $hedgebot;

    /**
     * ConsoleProvider constructor.
     * @param array $arguments
     */
    public function __construct(array $arguments)
    {
        if (empty(self::$hedgebot)) {
            self::$hedgebot = new HedgeBot();
            HedgeBot::setEnv("console");
        }

        array_shift($arguments);
        $this->arguments = implode(' ', $arguments);
    }

    /**
     * Populates the console application using both core commands and plugin commands.
     *
     * @param Application $application
     * @throws \ReflectionException
     */
    public function populateApplication(Application $application)
    {
        // Load the storages
        $this->init($application);

        if(!$this->bareInit) {
            $this->loadStorages();
        }

        // Populate the different parts
        $this->populateCoreCommands($application);

        if(!$this->bareInit) {
            $this->populatePluginCommands($application);
        }
    }

    /**
     * Initializes the common parts of the bot that may be used by the plugins.
     */
    public function init(Application $application)
    {
        $this->store = new Store();
        StoreAPI::setObject($this->store);
        
        // Set global options
        $application->getDefinition()->addOptions([
            new InputOption('--config', null, InputOption::VALUE_REQUIRED, 'The bot configuration to use')
        ]);
        
        // Get the log verbosity option using low level input reading
        $input = new ArgvInput();

        $this->setVerbosityLevel($input);

        $verbosityLevel = $input->getParameterOption('--log-verbosity');

        // If we ask to start the bot (a bit of a direct call here), we have to skip loading most of the console environment
        if ($input->getFirstArgument() == StartBotCommand::COMMAND_NAME) {
            define('ENV', 'main');
            $this->bareInit = true;
        } else {
            define('ENV', 'console');
        }
    }

    /**
     * Sets the verbosity level from the given input arguments.
     * 
     * @param InputInterface $input The input providing the arguments.
     * @return void 
     * 
     * @see Application::populateIO() does the same thing, but isn't accessible.
     */
    public function setVerbosityLevel(InputInterface $input)
    {
        $verbosityLevel = 0; // Default to a completely silent output

        if ($input->hasParameterOption(array('--quiet', '-q'), true) !== true) {
            if ($input->hasParameterOption('-vvv', true) || $input->hasParameterOption('--verbose=3', true) || 3 === $input->getParameterOption('--verbose', false, true)) {
                $verbosityLevel = 3;
            } elseif ($input->hasParameterOption('-vv', true) || $input->hasParameterOption('--verbose=2', true) || 2 === $input->getParameterOption('--verbose', false, true)) {
                $verbosityLevel = 2;
            } elseif ($input->hasParameterOption('-v', true) || $input->hasParameterOption('--verbose=1', true) || $input->hasParameterOption('--verbose', true) || $input->getParameterOption('--verbose', false, true)) {
                $verbosityLevel = 1;
            }
        }

        HedgeBot::$verbose = $verbosityLevel;
    }

    /**
     * Loads the multiple storages defined in the config file.
     * Said config file can be overridden using the -c parameter on the console call.
     */
    public function loadStorages()
    {
        $input = new ArgvInput();
        $configOption = $input->getParameterOption(['--config']);
        $configDir = $configOption ? $configOption : HedgeBot::DEFAULT_CONFIG_DIR; // Not using null coalescing because if not found, $configOption equals false, not null.

        $fileProvider = new IniFileProvider();
        $storageBootstrap = new ObjectAccess($fileProvider);
        $connected = $fileProvider->connect($configDir);

        if (!$connected) {
            throw new RuntimeException("Cannot connect to storage definition at: " . $configDir);
        }

        $configStorage = $storageBootstrap->storage->config;
        $dataStorage = $storageBootstrap->storage->data;

        $storageLoaded = self::$hedgebot->loadStorage($this->config, $configStorage);
        if (!$storageLoaded) {
            throw new RuntimeException("Cannot connect to config storage.");
        }

        $storageLoaded = self::$hedgebot->loadStorage($this->data, $dataStorage);
        if (!$storageLoaded) {
            throw new RuntimeException("Cannot connect to data storage.");
        }

        Config::setObject($this->config);
        Data::setObject($this->data);
    }

    /**
     * Populates the given console application with the core commands found in the current
     * namespace, via reflection.
     *
     * @param Application $application The application to populate.
     * @throws \ReflectionException
     */
    public function populateCoreCommands(Application $application)
    {
        $commandNamespace = __NAMESPACE__;
        $commandPath = __DIR__;

        $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($commandPath));
        $phpFiles = new RegexIterator($allFiles, '/\.php$/');

        foreach ($phpFiles as $file) {
            $className = str_replace('.php', '', $file->getPathname());
            $className = str_replace($commandPath, '', $className);
            $className = $commandNamespace . str_replace(DIRECTORY_SEPARATOR, "\\", $className);

            $reflectionClass = new ReflectionClass($className);

            if (!$reflectionClass->isAbstract() && !$reflectionClass->isInterface()
                && $reflectionClass->isSubclassOf(Command::class)) {
                $obj = new $className();

                // Give the config and the data provider to the class if it is storage-aware
                if (in_array(StorageAwareTrait::class, class_uses($obj)) && !$this->bareInit) {
                    $obj->setConfigStorage($this->config->getProvider());
                    $obj->setDataStorage($this->data->getProvider());
                }

                $application->add($obj);
            }
        }
    }

    /**
     * @param Application $application
     * @throws \ReflectionException
     */
    public function populatePluginCommands(Application $application)
    {
        $pluginsDirectory = $this->config->general->pluginsDirectory;
        $pluginManager = new PluginManager($pluginsDirectory);

        Plugin::setManager($pluginManager);

        $loadedPlugins = explode(',', $this->config->general->plugins);
        foreach ($loadedPlugins as $pluginName) {
            $pluginName = trim($pluginName);
            $config = $pluginManager->getPluginDefinition($pluginName);

            // Instanciate the plugin through the plugin manager, check if that worked, and get the object
            $pluginInstanciated = $pluginManager->loadPlugin($pluginName);
            if (!$pluginInstanciated) {
                continue;
            }

            $pluginObj = $pluginManager->getPlugin($pluginName);

            // Load the commands if they're present
            if (isset($config->commands)) {
                foreach ($config->commands->toArray() as $command => $commandClass) {
                    $commandClass = PluginManager::PLUGINS_NAMESPACE . $pluginName . "\\" . $commandClass;
                    $obj = new $commandClass();

                    if (in_array(PluginAwareTrait::class, class_uses($obj))) {
                        $obj->setPlugin($pluginObj);
                    }

                    $application->add($obj);
                }
            }
        }
    }

    /**
     * Gets the bot instance.
     * 
     * @return HedgeBot The bot instance. 
     */
    public static function getBot()
    {
        return self::$hedgebot;
    }
}
