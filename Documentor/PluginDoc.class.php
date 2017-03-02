<?php
namespace HedgeBot\Documentor;

use ReflectionClass;

/**
 * Plugin representative class for the documentation generator.
 * Instances of this class represents each a documentation for a plugin.
 * Documentation for the plugin will be kept inside this class as a
 * data tree, but it can be generated as markdown using the __toString()
 * method.
 */
class PluginDoc
{
    private $path;
    private $name;
    private $config;
    private $instance;

    private $commands = [];
    private $pluginInfo;

    const PLUGIN_NAMESPACE = "HedgeBot\\Plugins";

    /**
     * Constructor.
     */
    public function __construct($pluginName, $pluginPath)
    {
        $this->name = $pluginName;
        $this->path = $pluginPath;
    }

    /**
     * When asked to transform the plugin as a string, generate the documentation for it
     */
    public function __toString()
    {
        $doc = "";
        
        // Start by the plugin title
        $doc .= "#". ucfirst($this->name). " Plugin\n\n";
        
        // Show the plugin general info (tokens represented as paragraphs)
        foreach($this->pluginInfo['description'] as $description)
            $doc .= $description ."\n\n";
        
        // Configuration vars
        $doc .= "## Configuration settings\n\n";
        
        if(!empty($this->pluginInfo['configvar']))
        {
            foreach($this->pluginInfo['configvar'] as $configToken)
            {
                list($var, $description) = explode(' ', $configToken, 2);
                $doc .= "### ". $var. "\n\n";
                $doc .= trim($description). "\n\n";
            }
        }
        else
            $doc .= "*There are no configuration settings for this plugin.*\n\n";
        
        return $doc;
    }
    
    /**
     * Reads a plugin's configuration
     */
    public function readPluginConfig()
    {
        $configPath = $this->path. '/'. $this->name. '.ini';

        if(!is_file($configPath))
            return FALSE;

        $this->config = parse_ini_file($configPath, true);
    }

    /**
     * Reads the basic plugin info (from the main class' doc)
     */
    public function readPluginInfo()
    {
        // Get required data to instanciate plugin
        $pluginNS = self::PLUGIN_NAMESPACE. '\\'. $this->config['pluginDefinition']['mainClass'];
        $mainClass = $pluginNS. '\\'. $this->config['pluginDefinition']['mainClass'];
        
        // Reflection \o/
        $reflectionClass = new ReflectionClass($mainClass);
        $docComment = $reflectionClass->getDocComment();
        $this->pluginInfo = DocParser::parse($docComment);
    }
    
    /**
     * Returns the plugin's name
     */
    public function getName()
    {
        return $this->name;
    }
}
