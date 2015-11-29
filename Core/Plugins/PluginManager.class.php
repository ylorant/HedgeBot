<?php
namespace HedgeBot\Core\Plugins;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Data\FileProvider;
use HedgeBot\Core\Data\ObjectAccess;
use HedgeBot\Core\API\Server;
use ReflectionClass;
use ReflectionMethod;

class PluginManager extends Events
{

	private $_main; ///<  Reference to main class
	private $_plugins; ///< Plugins list
	private $_routines = array();
	private $_regex = array();
	private $_timeouts = array();
	private $pluginsDirectory;

	const PLUGINS_NAMESPACE = "HedgeBot\\Plugins\\";

	/** Constructor for PluginManager
	 * Initializes the class.
	 *
	 * \param $main A reference to the main program class (HedgeBot class).
	 */
	public function __construct(&$main)
	{
		$this->_main = $main;
		$this->_plugins = array();

		// Creating default event handlers
		$this->addEventListener('command', 'Command');
		$this->addEventListener('server', 'Server');
		$this->addEventListener('timeout', 'Timeout');

		// Setting plugins directory
		$this->pluginsDirectory = $this->_main->config->general->pluginsDirectory;
	}

	/** Loads plugins from an array.
	 * This function loads plugins from an array, allowing to load multiple plugins in one time. Basically, it justs do a burst call of loadPlugin for all specified
	 * plugins, plus a load verification.
	 *
	 * \param $list Plugin list to be loaded.
	 * \return TRUE if all plugins loaded successfully, FALSE otherwise.
	 */
	public function loadPlugins($list, $manual = FALSE)
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
				else
					return FALSE;
			}
		}

		return TRUE;
	}

	public function loadPlugin($plugin, $manual = FALSE)
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

		// Loading the config for the plugin as a storage
		$pluginConfig = new FileProvider();
		$config = new ObjectAccess($pluginConfig);
		$pluginConfig->connect($pluginDirectory);

		// Checking that the basic plugin configuration is present
		if(!$this->checkPluginConfig($config))
		{
			HedgeBot::message('Plugin $0: configuration is incomplete.', array($plugin), E_WARNING);
			return FALSE;
		}

		//Load dependencies if necessary
		if(!empty($config->pluginDefinition->dependencies) && is_array($pluginConfig->get('pluginDefinition.dependencies')))
		{
			HedgeBot::message('Loading plugin dependencies for $0.', array($plugin));
			$ret = $this->loadPlugins($pluginConfig->get('pluginDefinition.dependencies'));
			if(!$ret)
			{
				HedgeBot::message('Cannot load plugin dependencies, loading aborted.', array(), E_WARNING);
				return FALSE;
			}
			HedgeBot::message('Loaded plugin dependencies for $0.', array($params['name']));
		}
		elseif(!empty($pluginConfig->get('pluginDefinition.dependencies')))
			HedgeBot::message('Dependencies list is not an array.', array(), E_WARNING);

		// Load the main class
		$className = self::PLUGINS_NAMESPACE. $plugin."\\". $config->pluginDefinition->mainClass;
		$pluginObj = new $className($config->defaultSettings->toArray());

		HedgeBot::message('Autoloading events for $0...', array($plugin));
		$this->autoload($pluginObj);
		$ret = $pluginObj->init();

		if($ret === FALSE)
		{
			HedgeBot::message('Could not load plugin $0', array($plugin));
			unset($this->_plugins[$plugin]);
			return FALSE;
		}

		$this->_plugins[$plugin] = $pluginObj;

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
		foreach($this->_plugins as $pluginName => &$pluginData)
		{
			if(in_array($plugin, $pluginData['dependencies']))
			{
				HedgeBot::message('Plugin $0 depends on plugin $1. Cannot unload plugin $1.', array($pluginName, $plugin), E_WARNING);
				return FALSE;
			}
		}

		//Deleting routines
		if(isset($this->_routines[$this->_plugins[$plugin]['className']]))
		{
			foreach($this->_routines[$this->_plugins[$plugin]['className']] as $eventName => $event)
				$this->deleteRoutine($this->_plugins[$plugin]['obj'], $eventName);
		}

		//Deleting server events
		foreach($this->_serverEvents as $eventName => $eventList)
		{
			if(isset($eventList[$this->_plugins[$plugin]['className']]))
				$this->deleteServerEvent($eventName, $this->_plugins[$plugin]['obj']);
		}

		//Deleting commands
		foreach($this->_commands as $eventName => $eventList)
		{
			if(isset($eventList[$this->_plugins[$plugin]['className']]))
				$this->deleteCommand($eventName, $this->_plugins[$plugin]['obj']);
		}

		$dependencies = $this->_plugins[$plugin]['dependencies'];

		$this->_plugins[$plugin]['obj']->destroy();
		unset($this->_plugins[$plugin]['obj']);
		unset($this->_plugins[$plugin]);

		//Cleaning automatically loaded dependencies
		foreach($dependencies as $dep)
		{
			if($this->_plugins[$dep]['manual'] == FALSE)
			{
				HedgeBot::message('Unloading automatically loaded dependency $0.', array($dep));
				$this->unloadPlugin($dep);
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

	public function getPlugin($name)
	{
		if(isset($this->_plugins[$name]))
			return $this->_plugins[$name];

		return null;
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

	/** This function allow the plugin to changes the time interval of one of his routines. It is useful when using automatic revent detection, because it does not
	 * handles custom timers for routines (they are set to 1 second).
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
			return FALSE;
		}

		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			HedgeBot::message('Routine does not exists.', array(), E_DEBUG);
			return FALSE;
		}

		$this->_routines[get_class($plugin)][$method][1] = $time;

		return TRUE;
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

	public function execRegexEvents($cmd, $string)
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
		if(!empty($this->_timeouts[$event]))
			return false;

		$this->_timeouts[$event] = array("time" => time() + $time, "delay" => $time, "event" => $event);

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
		$time = time();
		foreach($this->_timeouts as $timeout)
		{
			if($timeout["time"] >= $time)
				$this->callEvent('timeout', $timeout["event"]);
		}
	}
}
