<?php

namespace HedgeBot\Core\Traits;

/** Allows property mapping for configuration.
 * This trait adds a property mapping functionality to a plugin, allowing it to bind configuration values
 * into object properties, while taking care of the channel/global scope for the values. You'll like it.
 */
trait PropertyConfigMapping
{
    /**
     ** Gets a config property with channel scope resolution.
     * This method gets the value for a configuration value,
     * taking it from the channel scope if possible, or else taking
     * it from the global scope (prefixed with the "global" keyword).
     * You can use dots in the var name to traverse arrays parameters.
     *
     * @param $channel The channel to get the parameter from.
     * @param string $name The parameter name to get.
     * @return mixed|null
     */
    public function getConfigParameter($channel, $name)
    {
        $nameParts = explode('.', $name);
        $name = array_shift($nameParts);

        $globalName = 'global' . ucfirst($name);

        $configValue = null;
        if (isset($this->{$name}[$channel])) {
            $configValue = $this->{$name}[$channel];
        } elseif (isset($this->$globalName)) {
            $configValue = $this->$globalName;
        }

        if (is_array($configValue) && !empty($nameParts)) {
            if (isset($this->$globalName)) {
                $backupConfigValue = $this->$globalName;
            }

            foreach ($nameParts as $part) {
                if (isset($configValue[$part])) {
                    $configValue = $configValue[$part];
                } elseif (isset($backupConfigValue[$part])) {
                    $configValue = $backupConfigValue[$part];
                } else {
                    return null;
                }

                if (isset($backupConfigValue[$part])) {
                    $backupConfigValue = $backupConfigValue[$part];
                }
            }
        }

        return $configValue;
    }

    /**
     * Does a configuration data property mapping.
     * This method reads the key/values in the given data structure (usually from config), filters them (optional),
     * and them maps them to the properties of the implementing class.
     * Configuration variables are read both per channel, using the first-level key "channel", and globally.
     * Global config settings are prefixed by the keyword "global" if
     * there is an already existing property defined as an array, and a property with the "global" keyword exists.
     * It keeps the camelCase when using the "global" keyword, e.g. "statusCommand" becomes "globalStatusCommand".
     * For global config settings, another check is done: if the setting is an array, then the value is merged with
     * the previous value in the configuration (assumed as the default value), recursively.
     *
     * TL;DR Parses config into properties, per channel.
     *       Global settings are prefixed by the keyword "global" if channel var
     *       with the same name exists. Arrays are merged instead of replaced.
     *
     * @param array $config The key/value array given to walk through.
     *                      Refer to the above boring text to understand how it works.
     * @param $parameters The parameter names to keep.
     */
    public function mapConfig(array $config, $parameters = null)
    {
        // Handling global config parameters
        foreach ($config as $name => $value) {
            // Defining property name to map
            $globalName = 'global' . ucfirst($name);
            $varParameter = $name;
            if (isset($this->$name) && is_array($this->$name) && isset($this->$globalName)) {
                $varParameter = $globalName;
            }

            if ($name != "channel" && (empty($parameters) || in_array($name, $parameters))) {
                if (is_array($this->$varParameter) && is_array($value)) {
                    $this->$varParameter = array_merge_recursive($this->$varParameter, $value);
                } else {
                    $this->$varParameter = $value;
                }
            }
        }

        // Handling per-channel config parameters
        if (!empty($config['channel'])) {
            foreach ($config['channel'] as $channel => $configElement) {
                foreach ($configElement as $name => $value) {
                    if (in_array($name, $parameters)) {
                        $this->{$name}[$channel] = $configElement[$name];
                    }
                }
            }
        }
    }

    /**
     * Gets a parameter from a defined dataset.
     * This method allows a plugin to fetch a specific value for a channel (allowing fallback), using a given
     * set of data as database.
     *
     * @param $data The data to use as database.
     * @param $channel The channel to filter on.
     * @param $valName The parameter value name to fetch.
     * @param $defaultValue The default value to return if nothing has been found.
     * @return mixed The value from the config if found, the default value otherwise.
     */
    public function getParameterFromData($data, $channel, $valName, $defaultValue = null)
    {
        $valNameParts = explode('.', $valName);
        $valName = array_shift($valNameParts);

        if (isset($data['channel'][$channel][$valName])) {
            $value = $data['channel'][$channel][$valName];
            if (empty($valNameParts)) {
                return $value;
            }

            foreach ($valNameParts as $part) {
                if (is_array($value) && !empty($value[$part])) {
                    $value = $value[$part];
                } else {
                    $value = null;
                    break;
                }
            }

            if (!empty($value)) {
                return $value;
            }
        }

        if (isset($data[$valName])) {
            $value = $data[$valName];
            if (empty($valNameParts)) {
                return $value;
            }

            foreach ($valNameParts as $part) {
                if (is_array($value) && !empty($value[$part])) {
                    $value = $value[$part];
                } else {
                    $value = null;
                    break;
                }
            }

            if (!empty($value)) {
                return $value;
            }
        }

        return $defaultValue;
    }
}
