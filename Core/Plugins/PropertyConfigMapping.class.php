<?php
namespace HedgeBot\Core\Plugins;

/** Allows property mapping for configuration.
 * This trait adds a property mapping functionality to a plugin, allowing it to bind configuration values
 * into object properties, while taking care of the channel/global scope for the values. You'll like it.
 */
trait PropertyConfigMapping
{
    /** Gets a config property with channel scope resolution.
     * This method gets the value for a configuration value, taking it from the channel scope if possible, or else taking
     * it from the global scope (prefixed with the "global" keyword).
     *
     * \param $channel The channel to get the parameter from.
     * \param $name The parameter name to get.
     */
    public function getConfigParameter($channel, $name)
    {
        $globalName = 'global'. ucfirst($name);

        $configValue = null;
        if(isset($this->timeoutThreshold[$channel]))
            $configValue = $this->{$name}[$channel];
        elseif(isset($this->$globalName))
            $configValue = $this->$globalName;

        return $configValue;
    }

    /** Does a configuration data property mapping.
     * This method reads the key/values in the given data structure (usually from config), filters them (optional),
     * and them maps them to the properties of the implementing class. Configuration variables are read both per channel,
     * using the first-level key "channel", and globally. Global config settings are prefixed by the keyword "global" if
     * there is an already existing property defined as an array, and a property with the "global" keyword exists.
     * It keeps the camelCase when using the "global" keyword, e.g. "statusCommand" becomes "globalStatusCommand".
     *
     * tl;dr Parses config into properties, per channel. Global settings are prefixed by the keyword "global" if channel var
     * 		 with the same name exists.
     *
     * \param $config The key/value array given to walk through. Refer to the above boring text to understand how it works.
     * \param $parameters The parameter names to keep.
     */
    public function mapConfig(array $config, $parameters = null)
    {
        // Handling global config parameters
		foreach($config as $name => $value)
		{
            // Defining property name to map
            $globalName = 'global'. ucfirst($name);
            $varParameter = $name;
            if(is_array($this->$name) && isset($this->$globalName))
			    $varParameter = $globalName;

			if($name != "channel" && (empty($parameters) || in_array($name, $parameters)))
				$this->$varParameter = $value;
		}

		// Handling per-channel config parameters
		if(!empty($config['channel']))
		{
			foreach($config['channel'] as $channel => $configElement)
			{
				foreach($configElement as $name => $value)
				{
					if(in_array($name, $parameters))
						$this->{$name}[$channel] = $configElement[$name];
				}
			}
		}
    }
}
