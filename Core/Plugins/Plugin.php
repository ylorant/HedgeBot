<?php
namespace HedgeBot\Core\Plugins;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Config;
use ReflectionClass;

class Plugin
{
	protected $config; ///< Plugin configuration, as an array, thus being read only
	protected $data; ///< Plugin data, confined to a namespace, r/w

	public function __construct($defaultConfig)
	{
		$dataStorage = Data::getObject();
		$configStorage = Config::getObject();
		
		$reflectorClass = new ReflectionClass($this);
		$pluginName = $reflectorClass->getShortName();

		$this->config = $configStorage->plugin->{$pluginName}->toArray();

		// Loading default configuraiton if present
		if(empty($this->config))
			$configStorage->plugin->{$pluginName} = $defaultConfig;

		$this->config = $configStorage->plugin->{$pluginName}->toArray();

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
}
