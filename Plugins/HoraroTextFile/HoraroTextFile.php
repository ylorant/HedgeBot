<?php

namespace HedgeBot\Plugins\HoraroTextFile;

use Hedgebot\Plugins\Horaro\Horaro;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Plugins\Horaro\Event\HoraroEvent;
use HedgeBot\Plugins\Horaro\Entity\Schedule;
use DateInterval;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Plugins\HoraroTextFile\Entity\FileMapping;

/**
 * Class HoraroTextFile
 * @package HedgeBot\Plugins\HoraroTextFile
 */
class HoraroTextFile extends PluginBase
{
    /** @var Horaro The Horaro plugin reference, where the schedule info will be fetched */
    protected $horaroPlugin;
    /** @var FileMapping[] The file mapping array. */
    protected $fileMappings;

    const TYPE_SCHEDULE = "schedule";
    const TYPE_CHANNEL = "channel";

    /**
     * @return bool|void
     */
    public function init()
    {
        $this->horaroPlugin = Plugin::getManager()->getPlugin('Horaro'); // Get the Horaro plugin object, it should be loaded since it's a dependency
        $this->loadData();
    }

    /**
     * Data has been updated externally, maybe that means the mapping has been changed by a console command ?
     * In any case, we reload the mapping.
     */
    public function CoreEventDataUpdate()
    {
        $this->loadData();
    }

    /**
     * Event: Schedule has been updated by the Horaro plugin.
     * We match the corresponding file to the new item if needed.
     *
     * @param HoraroEvent $event The event.
     * @throws \Exception
     */
    public function HoraroItemChange(HoraroEvent $event)
    {
        /** @var Schedule $schedule */
        $schedule = $event->schedule;
        $identSlug = $schedule->getIdentSlug();
        $channel = $schedule->getChannel();

        // Try to get the mapping(s) that correspond to the channel and/or slug
        /** @var FileMapping $channelMapping */
        $channelMapping = $this->getMapping(self::TYPE_CHANNEL, $channel);
        /** @var FileMapping $slugMapping */
        $slugMapping = $this->getMapping(self::TYPE_SCHEDULE, $identSlug);

        // Check if we have a file mapping for this schedule slug
        if (!empty($channelMapping) || !empty($slugMapping)) {
            // Get current item
            $item = $schedule->getCurrentItem();
            $itemFileContent = join("\n", $item->data);

            // Add estimate, see to replace with provider stuff
            $dateInterval = new DateInterval($item->length);
            $itemFileContent .= "\n" . $dateInterval->format("%H:%I:%S");
            
            // Get next item
            $nextItem = $schedule->getNextItem();
            if(!empty($nextItem)) {
                $itemFileContent .= "\n\n". join("\n", $nextItem->data);

                // Add estimate, see to replace with provider stuff
                $dateInterval = new DateInterval($nextItem->length);
                $itemFileContent .= "\n" . $dateInterval->format("%H:%I:%S");
            }

            // FIXME: Same fix as the replace in the Schedule class, cf it.
            $itemFileContent = preg_replace("#\[(.+)\]\((.+)\)#isU", "$1", $itemFileContent);

            if(!empty($slugMapping)) {
                HedgeBot::message("Writing mapping for slug $0", [$slugMapping->getId()], E_DEBUG);
                file_put_contents($slugMapping->getPath(), $itemFileContent);
            }

            if(!empty($channelMapping)) {
                HedgeBot::message("Writing mapping for channel $0", [$channelMapping->getId()], E_DEBUG);
                file_put_contents($channelMapping->getPath(), $itemFileContent);
            }
        }
    }

    /**
     * Gets the available file paths.
     * 
     * @return array An associative array of all the files, indexed by their type of linking and their associated schedule identifier.
     */
    public function getMappings()
    {
        return $this->fileMappings;
    }

    /**
     * Gets a specific schedule file path from its type and identifier.
     * 
     * @param string $type The identifier type to use, can be either TYPE_CHANNEL or TYPE_SCHEDULE.
     * @param string $identifier The identifier for the path to get depending on the type, either the schedule ident slug or the channel name.
     * 
     * @return FileMapping|null The schedule file mapping if found, or null if not.
     */
    public function getMapping($type, $identifier)
    {
        foreach($this->fileMappings as $file) {
            if($file->getType() == $type && $file->getId() == $identifier) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Saves a file mapping.
     * 
     * @param string $type The file mapping type to store this path into. Can be either TYPE_CHANNEL or TYPE_SCHEDULE.
     * @param string $identifier The identifier to use, either it be the schedule ident slug or the channel it is bound to.
     * @param string $filePath The new file path to save.
     * 
     * @return bool true
     */
    public function saveMapping($type, $identifier, $filePath)
    {
        // Update the mapping if it already exists
        foreach($this->fileMappings as &$mapping) {
            if($mapping->getType() == $type && $mapping->getId() == $identifier) {
                $mapping->setPath($filePath);
                return true;
            }
        }

        // If the search didn't update anything, this is a new mapping, append it
        $mapping = new FileMapping();
        $mapping->setType($type);
        $mapping->setId($identifier);
        $mapping->setPath($filePath);
        $this->fileMappings[] = $mapping;

        return true;
    }
    
    /**
     * Deletes a mapping.
     * 
     * @param string $type The mapping type.
     * @param string $identifier The mapping identifier, either the channel name or the schedule ident slug.
     * @return bool True if the mapping was successfully deleted, false if not.
     */
    public function deleteMapping($type, $identifier)
    {
        /** @var FileMapping $mapping */
        foreach($this->fileMappings as $key => $mapping) {
            if($mapping->getType() == $type && $mapping->getId() == $identifier) {
                unset($this->fileMappings[$key]);
                return true;
            }
        }

        return false;
    }

    /**
     * Loads the mapping from the data storage.
     */
    public function loadData()
    {
        $fileMappings = $this->data->fileMapping->toArray();
        $this->fileMappings = [];

        if(!empty($fileMappings)) {
            foreach($fileMappings as $mapping) {
                $this->fileMappings[] = FileMapping::fromArray($mapping);
            }
        }
    }

    /**
     * Saves the mapping to the data storage.
     */
    public function saveData()
    {
        $fileMappingsArray = [];

        /** @var FileMapping $mapping */
        foreach($this->fileMappings as $mapping) {
            $fileMappingsArray[] = $mapping->toArray();
        }

        $this->data->fileMapping = $fileMappingsArray;
    }
}
