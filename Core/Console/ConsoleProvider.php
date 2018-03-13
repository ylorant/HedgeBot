<?php
namespace HedgeBot\Core\Console;

use Symfony\Component\Console\Application;
use HedgeBot\Core\Console\PluginAwareTrait;
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
    /** @var string $arguments The arguments passed to the console command */
    protected $arguments;

    protected static $hedgebot;

    public function __construct(array $arguments)
    {
        if(empty(self::$hedgebot))
            self::$hedgebot = new HedgeBot();
        
        array_shift($arguments);
        $this->arguments = implode(' ', $arguments);
    }

    /**
     * Populates the console application using both core commands and 
     */
    public function populateApplication(Application $application)
    {
        // Load the storages
        $this->loadStorages();

        // Populate the different parts
        $this->populateCoreCommands($application);
        $this->populatePluginCommands($application);
    }

    /**
     * Loads the multiple storages defined in the config file.
     * Said config file can be overridden using the -c parameter on the console call.
     */
    public function loadStorages()
    {
        $input = new ArgvInput();
        $configOption = $input->getParameterOption(['--config', '-c']);
        $configDir = $configOption ? $configOption : HedgeBot::DEFAULT_CONFIG_DIR; // Not using null coalescing because if not found, $configOption equals false, not null.

		$fileProvider = new IniFileProvider();
        $storageBootstrap = new ObjectAccess($fileProvider);
        $connected = $fileProvider->connect($configDir);

        if(!$connected)
            throw new RuntimeException("Cannot connect to storage definition at: ". $configDir);

		$configStorage = $storageBootstrap->storage->config;
		$dataStorage = $storageBootstrap->storage->data;

		$storageLoaded = self::$hedgebot->loadStorage($this->config, $configStorage);
		if(!$storageLoaded)
            throw new RuntimeException("Cannot connect to config storage.");
        
		$storageLoaded = self::$hedgebot->loadStorage($this->data, $dataStorage);
		if(!$storageLoaded)
            throw new RuntimeException("Cannot connect to data storage.");
        
        Config::setObject($this->config);
        Data::setObject($this->data);
    }

    /**
     * Populates the given console application with the core commands found in the current
     * namespace, via reflection.
     * 
     * @param Application $application The application to populate.
     */
    public function populateCoreCommands(Application $application)
    {
        $commandNamespace = __NAMESPACE__;
        $commandPath = __DIR__;

        $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($commandPath));
        $phpFiles = new RegexIterator($allFiles, '/\.php$/');

        foreach($phpFiles as $file)
        {
            $className = str_replace('.php', '', $file->getPathname());
            $className = str_replace($commandPath, '', $className);
            $className = $commandNamespace. str_replace(DIRECTORY_SEPARATOR, "\\", $className);

            $reflectionClass = new ReflectionClass($className);
            
            if(!$reflectionClass->isAbstract() && !$reflectionClass->isInterface() && $reflectionClass->isSubclassOf(Command::class))
            {
                $obj = new $className();

                // Give the config and the data provider to the class if it is storage-aware
                if($obj instanceof StorageAwareInterface)
                {
                    $obj->setConfigStorage($this->config->getProvider());
                    $obj->setDataStorage($this->data->getProvider());
                }

                $application->add($obj);
            }
        }
    }

    public function populatePluginCommands(Application $application)
    {
        $pluginsDirectory = $this->config->general->pluginsDirectory;
        $pluginManager = new PluginManager($pluginsDirectory);

        Plugin::setManager($pluginManager);

        $loadedPlugins = explode(',', $this->config->general->plugins);
        foreach($loadedPlugins as $pluginName)
        {
            $config = $pluginManager->getPluginDefinition(trim($pluginName));

            // Instanciate the plugin through the plugin manager, check if that worked, and get the object
            $pluginInstanciated = $pluginManager->loadPlugin($pluginName);
            if(!$pluginInstanciated)
                continue;

            $pluginObj = $pluginManager->getPlugin($pluginName);

            // Load the commands if they're present
            if(isset($config->commands))
            {
                foreach($config->commands->toArray() as $command => $commandClass)
                {
                    $commandClass = PluginManager::PLUGINS_NAMESPACE. $pluginName. "\\". $commandClass;
                    $obj = new $commandClass();
                    
                    if(in_array(PluginAwareTrait::class, class_uses($obj)))
                        $obj->setPlugin($pluginObj);

                    $application->add($obj);
                }
            }
        }
    }

    protected function loadPlugin()
    {

    }
}