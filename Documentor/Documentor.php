<?php
namespace HedgeBot\Documentor;

use HedgeBot\Core\HedgeBot;

/**
 * User documentation generator for Hedgebot.
 * It generates commands documentation based on the comments from the code
 * making up the plugins.
 */
class Documentor
{
	const PLUGINS_DIRECTORY = "Plugins";
	const DEFAULT_OUTPUT_DIRECTORY = "generated_doc";

	private $outputDir;
	private $pluginDocs = [];

	public function __construct($outputDir = self::DEFAULT_OUTPUT_DIRECTORY)
	{
		$this->outputDir = $outputDir;
	}

	/**
	 * Start doc generation for the current bot plugins.
	 */
	public function generate()
	{
        HedgeBot::message("HedgeBot plugin doc generator.", null, E_DEBUG);

		// List all plugins
		$pluginList = scandir(self::PLUGINS_DIRECTORY);
		$pluginList = array_diff($pluginList, ['.', '..']); // Remove self and parent dir ref

		HedgeBot::message("Discovering plugins...", null, E_DEBUG);
		foreach($pluginList as $pluginDir)
		{
            $pluginPath = self::PLUGINS_DIRECTORY. DIRECTORY_SEPARATOR. $pluginDir;
            if(is_dir($pluginPath))
            {
            	HedgeBot::message("\tDiscovered plugin $0", [$pluginDir], E_DEBUG);
            	$this->pluginDocs[] = new PluginDoc($pluginPath);
            }
		}

		HedgeBot::message('Plugins found: $0', count($this->pluginDocs), E_DEBUG);

		HedgeBot::message("Reading plugins' config...", null, E_DEBUG);
		$this->executePluginDocsFunction('readPluginConfig');

		HedgeBot::message("Reading plugins' general doc...", null, E_DEBUG);
		$this->executePluginDocsFunction('reflectPlugin');

		HedgeBot::message("Reading plugins' commands...", null, E_DEBUG);
		$this->executePluginDocsFunction('readPluginCommands');

		HedgeBot::message("Writing docs markdown...");
		$writeStatus = $this->writeDocs();

		if(!$writeStatus)
		    HedgeBot::message("Markdown generation failed.", null, E_ERROR);
	}

    private function writeDocs()
    {
        if(is_file($this->outputDir))
            return false;

        if(!is_dir($this->outputDir))
            mkdir($this->outputDir);

        foreach($this->pluginDocs as $plugin)
        {
            HedgeBot::message("\t$0...", [$plugin->getName()], E_DEBUG);
            $filename = $this->outputDir. DIRECTORY_SEPARATOR. $plugin->getName(). ".md";
            $doc = (string) $plugin;

            file_put_contents($filename, $doc);
        }

        return true;
    }

    /**
     * Call a function on each plugin doc class.
     * Basically just a sugar function to save foreach calls every time.
     */
    private function executePluginDocsFunction($methodName, ...$params)
    {
        if(empty($this->pluginDocs))
            return;

        $firstPlugin = $this->pluginDocs[0];
        if(!method_exists($firstPlugin, $methodName))
            return;

        foreach($this->pluginDocs as $plugin)
        {
            HedgeBot::message("\t$0...", [$plugin->getName()], E_DEBUG);
            $plugin->$methodName(...$params);
        }
    }
}
