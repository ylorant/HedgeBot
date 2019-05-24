<?php

namespace HedgeBot\Plugins\HoraroTextFile;

use Hedgebot\Plugins\Horaro\Horaro;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Plugins\Horaro\Event\HoraroEvent;
use HedgeBot\Plugins\Horaro\Entity\Schedule;
use DateInterval;

/**
 * Class HoraroTextFile
 * @package HedgeBot\Plugins\HoraroTextFile
 */
class HoraroTextFile extends PluginBase
{
    /** @var Horaro The Horaro plugin reference, where the schedule info will be fetched */
    protected $horaroPlugin;
    /** @var array The schedule ident slug -> file path mapping array */
    protected $fileMapping;

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
     * We match the file to the new item.
     *
     * @param HoraroEvent $event The event.
     * @throws \Exception
     */
    public function HoraroItemChange(HoraroEvent $event)
    {
        /** @var Schedule $schedule */
        $schedule = $event->schedule;
        $identSlug = $schedule->getIdentSlug();

        // Check if we have a file mapping for this schedule slug
        if (!empty($this->fileMapping[$identSlug])) {
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

            file_put_contents($this->fileMapping[$identSlug], $itemFileContent);
        }
    }

    /**
     * Sets the file path for a schedule slug in the file mapping.
     *
     * @param string $identSlug The ident slug to set the path of.
     * @param string $path The path to put the file into.
     */
    public function setScheduleFile($identSlug, $path)
    {
        $this->fileMapping[$identSlug] = $path;
    }

    /**
     * Loads the mapping from the data storage.
     */
    public function loadData()
    {
        $this->fileMapping = $this->data->fileMapping->toArray();
    }

    /**
     * Saves the mapping to the data storage.
     */
    public function saveData()
    {
        $this->data->fileMapping = $this->fileMapping;
    }
}
