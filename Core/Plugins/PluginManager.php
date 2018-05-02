<?php
namespace HedgeBot\Core\Plugins;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Data\IniFileProvider;
use HedgeBot\Core\Data\ObjectAccess;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\Events\EventManager;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\TimeoutEvent;
use ReflectionClass;
use ReflectionMethod;
use HedgeBot\Core\API\Security;

class PluginManager extends EventManager
{
	private $_plugins; ///< Plugins list
	private $_routines = array();
	private $_regex = array();
	private $_timeouts = array();
	private $_pluginsDefinition = array(); ///< Reference to plugins definitions
	private $_manuallyLoadedPlugins = array(); ///< List of manually loaded plugins, to avoid unloading them as dependencies

	public $pluginsDirectory; ///< Directory where the plugins are stored.

	const PLUGINS_NAMESPACE = "HedgeBot\\Plugins\\";

	/** Constructor for PluginManager
	 * Initializes the class.
	 *
	 * \param $main A reference to the main program class (HedgeBot class).
	 */
	public function __construct($pluginsDirectory)
	{
		$this->_plugins = array();

		// Creating default event handlers
		$this->addEventListener(CoreEvent::getType(), 'CoreEvent');
		$this->addEventListener(CommandEvent::getType(), 'Command');
		$this->addEventListener(ServerEvent::getType(), 'Server');
		$this->addEventListener(TimeoutEvent::getType(), 'Timeout');

		$this->addRoutine($this, 'timeoutRoutine');

		// Setting plugins directory
		$this->pluginsDirectory = $pluginsDirectory;
	}

	/** Loads plugins from an array.
	 * This function loads plugins from an array, allowing to load multiple plugins in one time. Basically, it justs do a burst call of loadPlugin for all specified
	 * plugins, plus a load verification.
	 *
	 * \param $list Plugin list to be loaded.
	 * \return TRUE if all plugins loaded successfully, FALSE otherwise.
	 */
	public function loadPlugins($list, $manual = TRUE)
	{
		if(!is_array($list))
			return FALSE;

		HedgeBot::message('Loading plugins : $0', array(join(', ', $list)));
		$loadedPlugins = array();
		foreach($list as $plugin)
		{
			if(!in_array($plugin, $this->getLoadedPlugins()))
			{
				$return = $this->loadPlugin($plugin, $manual);
				if($return !== FALSE)
					$loadedPlugins[] = $plugin;
				elseif(!$manual)
					return FALSE;
			}
		}

		return TRUE;
	}

	/** Loads a plugin.
	 * This method loads a single plugin. It is called from
	 */
	public function loadPlugin($plugin, $manual = TRUE)
	{
		HedgeBot::message('Loading plugin $0', array($plugin));

		//We check that the plugin is not already loaded
		if(in_array($plugin, $this->getLoadedPlugins()))
		{
			HedgeBot::message('Plugin $0 is already loaded', array($plugin), E_WARNING);
			return FALSE;
		}

		$pluginDirectory = $this->pluginsDirectory. '/'. $plugin. '/';

		// Checking that the plugin dir exists, and its file too
		if(!is_dir($pluginDirectory))
		{
			HedgeBot::message('Plugin $0 does not exists (Directory: $1)', array($plugin, $pluginDirectory), E_WARNING);
			return FALSE;
		}

		// Loading the definition for the plugin as a storage
		$config = $this->getPluginDefinition($plugin);

		// Checking that the basic plugin configuration is present
		if(!$this->checkPluginConfig($config))
		{
			HedgeBot::message('Plugin $0: configuration is incomplete.', array($plugin), E_WARNING);
			return FALSE;
		}

		//Load dependencies if necessary
		if(!empty($config->pluginDefinition->dependencies) && is_array($config->get('pluginDefinition.dependencies')))
		{
			HedgeBot::message('Loading plugin dependencies for $0.', array($plugin));
			$ret = $this->loadPlugins($config->get('pluginDefinition.dependencies'), FALSE);
			if(!$ret)
			{
				HedgeBot::message('Cannot load plugin dependencies, loading aborted.', array(), E_WARNING);
				return FALSE;
			}
			HedgeBot::message('Loaded plugin dependencies for $0.', array($plugin));
		}
		elseif(!empty($config->get('pluginDefinition.dependencies')))
			HedgeBot::message('Dependencies list is not an array.', array(), E_WARNING);

		// Load the main class
		$className = self::PLUGINS_NAMESPACE. $plugin."\\". $config->pluginDefinition->mainClass;
		$pluginObj = new $className($config->defaultSettings->toArray());

		HedgeBot::message('Autoloading events for $0...', array($plugin));
		$autoloadedEvents = $this->autoload($pluginObj);

		// Register new commands into the security system if the security context is loaded
		foreach($autoloadedEvents as $event)
		{
			if($event['listener'] == CommandEvent::getType() && !empty(Security::getObject()))
				Security::addRights($event['listener']. '/'. $event['event']);
		}

		$this->_plugins[$plugin] = $pluginObj;
		$ret = $pluginObj->init();
		
		if($ret === FALSE)
		{
			HedgeBot::message('Could not load plugin $0', array($plugin));
			$this->unloadPlugin($plugin);
			return FALSE;
		}

		if($manual)
			$this->_manuallyLoadedPlugins[$plugin] = $plugin;

		HedgeBot::message('Loaded plugin $0', array($plugin));

		return true;
	}

	/** Checks if the configuration for a plugin is correct.
	 * Checks if the configuration for a plugin is correct. It will mainly check that the minimal data
	 * needed for loading it is present.
	 *
	 * \param $config The plugin's configuration.
	 *
	 * \return True if the configuration is correct, False otherwise.
	 */
	public function checkPluginConfig($config)
	{
		if(empty($config->pluginDefinition))
			return false;

		if(empty($config->pluginDefinition->mainClass))
			return false;

		// Creating empty sections if they're not present
		if(empty($config->defaultSettings))
			$config->defaultSettings = array();

		return true;
	}

	/** Unloads a plugin.
	 * This function unloads a plugin. It does not unload the dependencies with it yet.
	 *
	 * FIXME: Currently works with the old plugin structure, adapt to the new one.
	 *
	 * \param $plugin The plugin to unload.
	 *
	 * \return TRUE if the plugin successuly unloaded, FALSE otherwise.
	 */
	public function unloadPlugin($plugin)
	{
		HedgeBot::message('Unloading plugin $0', array($plugin));

		//We check that the plugin is not already loaded
		if(!in_array($plugin, $this->getLoadedPlugins()))
		{
			HedgeBot::message('Plugin $0 is not loaded', array($plugin), E_WARNING);
			return FALSE;
		}

		//Searching plugins that depends on the one we want to unload
		foreach($this->_pluginsDefinition as $pluginName => $pluginData)
		{
			if($pluginName != $plugin)
			{
				$dependencies = $pluginData->get('dependencies');
				if(is_array($dependencies) && in_array($plugin, $dependencies))
				{
					HedgeBot::message('Plugin $0 depends on plugin $1. Cannot unload plugin $1.', array($pluginName, $plugin), E_WARNING);
					return FALSE;
				}
			}
		}

		// Starting of unloading procedure
		$this->_plugins[$plugin]->destroy();

		// Getting routines linked to the plugin classes
		$pluginNamespace = self::PLUGINS_NAMESPACE. $pluginName;
		$pluginNames = array_keys($this->_routines);

		$routinesClasses = array();
		foreach($pluginNames as $pluginName)
		{
			if(strpos($pluginName, $pluginNamespace) === 0)
				$routinesClasses[] = $pluginName;
		}

		//Deleting routines for all the needed classes of the plugin
		if(!empty($routinesClasses))
		{
			foreach($routinesClasses as $pluginClass)
			{
				foreach($this->_routines[$pluginClass] as $eventName => $event)
					$this->deleteRoutine($this->_plugins[$plugin], $eventName);
			}
		}

		$reflectionClass = new ReflectionClass($this->_plugins[$plugin]);

		// Remove all the rights references
		$pluginEvents = $this->getEventsById($reflectionClass->getName());
		$rightsToRemove = [];
		foreach($pluginEvents as $event)
		{
			if($event['listener'] == CommandEvent::getType())
				$rightsToRemove[] = $event['listener']. '/'. $event['event'];
		}

		// Remove the rights from the security context if it exists
		if(!empty(Security::getObject()))
			Security::removeRights(...$rightsToRemove);

		// Deleting all the autoloaded events for the plugin
		$this->deleteEventsById($reflectionClass->getName());

		unset($this->_plugins[$plugin]);
		unset($this->_manuallyLoadedPlugins[$plugin]);

		//Cleaning automatically loaded dependencies
		$dependencies = $this->_pluginsDefinition[$plugin]->get('dependencies');
		if(is_array($dependencies))
		{
			foreach($dependencies as $dep)
			{
				if(!empty($this->_plugins[$dep]) && !isset($this->_manuallyLoadedPlugins[$dep]))
				{
					HedgeBot::message('Unloading automatically loaded dependency $0.', array($dep));
					$this->unloadPlugin($dep);
				}
			}
		}

		HedgeBot::message('Plugin $0 unloaded successfully', array($plugin));
		return TRUE;
	}

	/** Gets the currently loaded plugins.
	 * This function returns an array containing the list of all currently loaded plugins' names.
	 *
	 * \return The currently loaded plugins' names, in an array.
	 */
	public function getLoadedPlugins()
	{
		return array_keys($this->_plugins);
	}

	/** Gets a plugin if it is loaded.
	 * Gets a plugin if it is loaded, returns null otherwise.
	 * 
	 * @param string $name The plugin name to get.
	 * 
	 * @return Plugin|null The plugin if found, null otherwise.
	 */
	public function getPlugin($name)
	{
		if(isset($this->_plugins[$name]))
			return $this->_plugins[$name];

		return null;
	}

	/** Returns the definition of a plugin.
	 * This method returns the definition for a plugin. Note that the plugin doesn't have to be loaded,
	 * but a cache is kept to prevent parsing the definition of an already loaded plugin.
	 * 
     * FIXME: Trim the plugin default settings ? Trim all the INI strings ?
	 *
	 * \param $name The name of the plugin to load definition from.
	 *
	 * \return The plugin definition as an ObjectAccess object.
	 */
	public function getPluginDefinition($name)
	{
		if(!isset($this->_pluginsDefinition[$name]))
		{
			$pluginDirectory = $this->pluginsDirectory. '/'. $name. '/';
			$pluginConfig = new IniFileProvider();
			$pluginConfig->readonly = true; // Ensure that we don't try to save the plugin definition.
			$config = new ObjectAccess($pluginConfig);
			$pluginConfig->connect($pluginDirectory);

			$this->_pluginsDefinition[$name] = $config;
		}

		return $this->_pluginsDefinition[$name];

	}

	/** Adds a routine to the event manager.
	 * This function adds a routine to the event manager, i.e. a function that will be executed every once in a while.
	 *
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be executed.
	 * \param $time The time interval between 2 executions of the routine. Defaults to 1 second.
	 *
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addRoutine(&$plugin, $method, $time = 1)
	{
		HedgeBot::message('Adding routine $0, executed every $1s', array(get_class($plugin).'::'.$method, $time), E_DEBUG);

		if(!method_exists($plugin, $method)) //Check if method exists
		{
			HedgeBot::message('Error : Target method does not exists.', array(), E_DEBUG);
			return FALSE;
		}

		$this->_routines[get_class($plugin)][$method] = array($plugin, $time, array());

		return TRUE;
	}

	/** Deletes a routine.
	 * This function deletes a routine from the event manager.
	 *
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 *
	 * \return TRUE if method deleted correctly, FALSE otherwise.
	 */
	public function deleteRoutine(&$plugin, $method)
	{
		HedgeBot::message('Deleting routine $0', array(get_class($plugin).'::'.$method), E_DEBUG);

		if(!isset($this->_routines[get_class($plugin)]))
		{
			HedgeBot::message('Plugin $0 does not exists in routine list.', array($event), E_DEBUG);
			return FALSE;
		}

		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			HedgeBot::message('Routine does not exists.', array(), E_WARNING);
			return FALSE;
		}

		unset($this->_routines[get_class($plugin)][$method]);

		return TRUE;
	}

	/** This function allows the plugin to change the time interval of one of its routines. 
	 * It is useful when using automatic revent detection, because it does not
	 * handle custom timers for routines (they are set to 1 second).
	 *
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be updated.
	 * \param $time The new time interval.
	 *
	 * \return TRUE if method modified correctly, FALSE otherwise.
	 */
	public function changeRoutineTimeInterval(&$plugin, $method, $time)
	{
		HedgeBot::message('Changing routine $0 time interval to $1s', array(get_class($plugin).'::'.$method, $time), E_DEBUG);

		if(!isset($this->_routines[get_class($plugin)]))
		{
			HedgeBot::message('Plugin $0 does not exists in routine list.', array(get_class($plugin)), E_DEBUG);
			return false;
		}

		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			HedgeBot::message('Routine does not exists.', array(), E_DEBUG);
			return false;
		}

		if(!is_numeric($time))
		{
			HedgeBot::message('Given interval is not a number: "$0"', [$time], E_DEBUG);
			return false;
		}

		$this->_routines[get_class($plugin)][$method][1] = (int) $time;

		return true;
	}

	/** Executes all the routines for all plugins.
	 * This function executes all the routines for all plugins, whether checking if their interval timed out, or not (so all routines are executed), depending
	 * on the value of the \b $force param.
	 *
	 * \param $force Forces the routines to be executed or not. By default it does not executes them.
	 *
	 * \return TRUE if routines executed correctly, FALSE otherwise.
	 */
	public function callAllRoutines($force = FALSE)
	{
		$serverName = Server::getName();
		foreach($this->_routines as $className => &$class)
		{
			foreach($class as $name => &$routine)
			{
				if($force || !isset($routine[2][$serverName]) || (time() >= $routine[2][$serverName] + $routine[1] /*&& time() != $routine[2][$serverName] */))
				{
					$routine[0]->$name();
					$routine[2][$serverName] = time();
				}
			}
		}
	}

	public function addRegexEvent($regex, $callback)
	{
		$this->_regex[] = array($regex, $callback);
	}

	public function callRegexEvents($cmd, $string)
	{
		foreach($this->_regex as $el)
		{
			if(preg_match('#^'.$el[0].'$#isU', $string, $matches))
			{
				call_user_func_array($el[1], array($cmd, $matches));
				break;
			}
		}
	}

	/** Sets a timeout on an event.
	 * Use this function to enable a timeout to be used. For example, you can create a timeout function called TimeoutSample, and them enable it
	 * with a call to setTimeout(15, 'sample').
	 *
	 * \param $time The time in seconds after which the timeout will be triggered.
	 * \param $event The timeout to call. Has to be previously created (with an automatic binding or manually).
	 *
	 * \return TRUE if the timeout has been set, FALSE otherwise.
	 */
	public function setTimeout($time, $event)
	{
		$this->_timeouts[] = array("time" => time() + $time, "delay" => $time, "event" => $event);

		return true;
	}

	/** Resets the timeout for an event.
	 * Use this method if you want the timer for a timeout event to go back to its original time, set when creating it.
	 * To actually clear a timeout, see clearTimeout().
	 *
	 * \param $event The event to reset the timer of.
	 *
	 * \return TRUE on success, FALSE on failure.
	 */
	public function resetTimeout($event)
	{
		if(empty($this->_timeouts[$event]))
			return false;

		$this->_timeouts[$event]["time"] = time() + $this->_timeouts[$event]["delay"];

		return true;
	}

	/** Removes a timeout for an event.
	 * Use this method to remove a timeout for an event, preventing it to be executed.
	 *
	 * \param  $event The event to clear the timeout from.
	 *
	 * \return TRUE on success, FALSE on failure.
	 */
	public function clearTimeout($event)
	{
		if(empty($this->_timeouts[$event]))
			return false;

		unset($this->_timeouts[$event]);

		return true;
	}

	/** Internal - timeout routine
	 * Handles the periodic call of timeout events.
	 */
	public function timeoutRoutine()
	{
		$timeoutsToRemove = array();
		$time = time();
		foreach($this->_timeouts as $i => $timeout)
		{
			if($timeout["time"] >= $time)
			{
				$timeoutsToRemove[] = $i;
				$this->callEvent(new TimeoutEvent($timeout["event"]));
			}
		}

		rsort($timeoutsToRemove);
		foreach($timeoutsToRemove as $key)
			unset($this->_timeouts[$key]);
	}
}
