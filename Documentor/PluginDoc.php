<?php
namespace HedgeBot\Documentor;

use ReflectionClass;
use ReflectionMethod;

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

    private $reflectionClass;

    const PLUGIN_NAMESPACE = "HedgeBot\\Plugins";

    /**
     * Constructor.
     */
    public function __construct($pluginPath)
    {
        $pathParts = explode(DIRECTORY_SEPARATOR, $pluginPath);
        $this->name = end($pathParts);
        $this->path = $pluginPath;
    }

    /**
     * When asked to transform the plugin as a string, generate the documentation for it
     */
    public function __toString()
    {
        $doc = "";

        // Start by the plugin title
        $doc .= "# ". ucfirst($this->name). " Plugin\n\n";

        // Show the plugin general info (tokens represented as paragraphs)
        if(!empty($this->pluginInfo['description']))
            $doc .= join("\n\n", $this->pluginInfo['description']) ."\n\n";

        // Configuration vars
        $doc .= "## Configuration settings\n\n";

        if(!empty($this->pluginInfo['configvar']))
        {
            foreach($this->pluginInfo['configvar'] as $configToken)
            {
                list($var, $description) = explode(' ', $configToken, 2);
                $doc .= "### ". $var. "\n";
                $doc .= trim($description). "\n\n";
            }
        }
        else
            $doc .= "*There are no configuration settings for this plugin.*\n";

        $doc .= "## Commands\n\n";

        foreach($this->commands as $command)
        {
            $doc .= (string) $command;
            $doc .= "--------\n";
        }

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
    public function reflectPlugin()
    {
        // Get required data to instanciate plugin
        $pluginNS = self::PLUGIN_NAMESPACE. '\\'. $this->config['pluginDefinition']['mainClass'];
        $mainClass = $pluginNS. '\\'. $this->config['pluginDefinition']['mainClass'];

        // Reflection \o/
        $this->reflectionClass = new ReflectionClass($mainClass);
        $docComment = $this->reflectionClass->getDocComment();
        $this->pluginInfo = DocParser::parse($docComment);
    }

    /**
     * Read commands from the plugin main class.
     */
    public function readPluginCommands()
    {
        // Only consider public methods
        $pluginMethods = $this->reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach($pluginMethods as $method)
        {
            // Check if ##the method looks like a command method
            if(preg_match("#^Command([a-zA-Z0-9]+)$#", $method->getName()))
                $this->commands[] = new CommandDoc($method);
        }
    }

    /**
     * Returns the plugin's name
     */
    public function getName()
    {
        return $this->name;
    }
}
