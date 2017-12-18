<?php
namespace HedgeBot\Documentor;

use Symfony\Component\Console\Output\OutputInterface;

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
	private $output;

	/**
	 * Constructs a documentor.
	 * @constructor
	 */
	public function __construct(OutputInterface $output)
	{
		$this->outputDir = self::DEFAULT_OUTPUT_DIRECTORY;
		$this->output = $output;
	}

	public function setOutputDirectory($outputDirectory)
	{
		if(!is_dir($outputDirectory) && !mkdir($outputDirectory))
			throw new Exception('Output directory does not exist and is not creatable');

		$this->outputDir = $outputDirectory;
	}

	/**
	 * Start doc generation for the current bot plugins.
	 */
	public function generate()
	{
        $this->output->writeln("HedgeBot plugin doc generator.");

		// List all plugins
		$pluginList = scandir(self::PLUGINS_DIRECTORY);
		$pluginList = array_diff($pluginList, ['.', '..']); // Remove self and parent dir ref

		$this->output->writeln("Discovering plugins...");
		foreach($pluginList as $pluginDir)
		{
            $pluginPath = self::PLUGINS_DIRECTORY. DIRECTORY_SEPARATOR. $pluginDir;
            if(is_dir($pluginPath))
            {
            	$this->output->writeln("\tDiscovered plugin ". $pluginDir);
            	$this->pluginDocs[] = new PluginDoc($pluginPath);
            }
		}

		$this->output->writeln('Plugins found: '. count($this->pluginDocs));

		$this->output->writeln("Reading plugins' config...");
		$this->executePluginDocsFunction('readPluginConfig');

		$this->output->writeln("Reading plugins' general doc...");
		$this->executePluginDocsFunction('reflectPlugin');

		$this->output->writeln("Reading plugins' commands...");
		$this->executePluginDocsFunction('readPluginCommands');

		$this->output->writeln("Writing docs markdown...");
		$writeStatus = $this->writeDocs();

		if(!$writeStatus)
		    $this->output->writeln("Markdown generation failed.");
	}

    private function writeDocs()
    {
        if(is_file($this->outputDir))
            return false;

        if(!is_dir($this->outputDir))
            mkdir($this->outputDir);

        foreach($this->pluginDocs as $plugin)
        {
            $this->output->writeln("\t". $plugin->getName(). "...");
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
            $this->output->writeln("\t". $plugin->getName(). "...");
            $plugin->$methodName(...$params);
        }
    }
}
