<?php
namespace HedgeBot\Core\Plugins;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Data;
use ReflectionClass;

class Plugin
{
	protected $config; ///< Plugin configuration, as an array, thus being read only
	protected $data; ///< Plugin data, confined to a namespace, r/w

	public function __construct($defaultConfig)
	{
		$main = HedgeBot::getInstance();

		$reflectorClass = new ReflectionClass($this);
		$pluginName = $reflectorClass->getShortName();

		$this->config = $main->config->get('plugin.'.$pluginName);

		// Loading default configuraiton if present
		if(empty($this->config))
		{
			$main->config->set('plugin.'. $pluginName, $defaultConfig);
			$main->config->writeData();
		}

		$this->config = $main->config->get('plugin.'.$pluginName);

		$dataStorage = Data::getStorage();
		if(empty($dataStorage->plugin))
			$dataStorage->plugin = array();

		if(empty($dataStorage->plugin->{$pluginName}))
			$dataStorage->plugin->{$pluginName} = array();

		$this->data = $dataStorage->plugin->{$pluginName};
	}

	/** Default plugin init function.
	 * This function is empty. Its unique purpose is to avoid using method_exists() on PluginManager::initPlugin(). Returns TRUE.
	 *
	 * \return TRUE.
	 */
	public function init()
	{
		return TRUE;
	}

	/** Default plugin destroy function.
	 * This function is empty. Its unique purpose is to avoid using method_exists() on PluginManager::unloadPlugin(). Returns TRUE.
	 *
	 * \return TRUE.
	 */
	public function destroy()
	{
		return TRUE;
	}

	public function addRegexEvent($regex, $callback)
	{
		$this->_plugins->addRegexEvent($regex, $callback);
	}

	public function execRegexEvents($cmd, $string)
	{
		$this->_plugins->execRegexEvents($cmd, $string);
	}
}
