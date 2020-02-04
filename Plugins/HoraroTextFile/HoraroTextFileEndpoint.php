<?php
namespace HedgeBot\Plugins\HoraroTextFile;

use \stdClass;

class HoraroTextFileEndpoint
{
    /** @var HoraroTextFile The plugin reference */
    protected $plugin;

    /**
     * HoraroTextFileEndpoint constructor.
     * Initializes the endpoint with the plugin to use as data source.
     *
     * @param HoraroTextFile $plugin
     */
    public function __construct(HoraroTextFile $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the available file paths.
     * @see HoraroTextFile::getFiles()
     */
    public function getFiles()
    {
        return $this->plugin->getFiles();
    }

    /**
     * Gets a specific mapping by its channel.
     * @see HoraroTextFile::getMappingByChannel()
     */
    public function getMappingByChannel($channel)
    {
        return $this->plugin->getMappingByChannel($channel);
    }

    /**
     * Sets a specific file path.
     * @see HoraroTextFile::setFile()
     */
    public function saveMapping()
    {
        // $this->plugin->setFile($type, $identifier, $file);
    }
}