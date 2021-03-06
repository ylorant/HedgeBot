<?php
namespace HedgeBot\Plugins\Horaro;

use HedgeBot\Plugins\Horaro\Entity\Schedule;
use \stdClass;

class HoraroEndpoint
{
    /** @var Horaro The plugin reference */
    protected $plugin;

    /**
     * HoraroEndpoint constructor.
     * Initializes the endpoint with the plugin to use as data source.
     *
     * @param Horaro $plugin
     */
    public function __construct(Horaro $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Lists the schedules that are registered on the bot.
     * 
     * @return array The list of registered schedules.
     */
    public function getSchedules()
    {
        return $this->plugin->getSchedules();
    }

    /**
     * Gets the currently running schedules.
     *
     * @param string $channel    Filter the schedules by channel.
     * @param bool   $lookaround Set to true to loosen the search by looking for schedules that are around current time
     *                           by the lookaroundThreshold setting value (default: 1 hour).
     * 
     * @return array The list of schedules that are currently running. If none are found, an empty array is returned.
     */
    public function getCurrentSchedules($channel = null, $lookaround = false)
    {
        return $this->plugin->getCurrentlyRunningSchedules($channel, $lookaround);
    }
    
    /**
     * Get the currently running schedule. If multiple schedules are running, it returns the earliest one. 
     * 
     * @param string $channel    Filter the schedules by channel.
     * @param bool   $lookaround Set to true to loosen the search by looking for schedules that are around current time
     *                           by the lookaroundThreshold setting value (default: 1 hour).
     *
     * @return Schedule|null The earliest running schedule if there is one, null if not.
     * @throws Exception 
     */
    public function getCurrentSchedule($channel = null, $lookaround = false)
    {
        return $this->plugin->getEarliestCurrentlyRunningSchedule($channel, $lookaround);
    }

    /**
     * Gets a schedule by its ident slug.
     * 
     * @param string $identSlug The ident slug of the schedule.
     * 
     * @return Schedule|null The schedule info if found, false if not.
     */
    public function getSchedule($identSlug)
    {
        return $this->plugin->getScheduleByIdentSlug($identSlug);
    }

    /**
     * Saves a schedule's data.
     * 
     * @param string $identSlug The ident slug of the schedule to save.
     * @param stdClass $scheduleData The schedule data.
     */
    public function updateSchedule($identSlug, stdClass $scheduleData)
    {
        return $this->plugin->updateSchedule($identSlug, (array) $scheduleData);
    }

    /**
     * Loads a schedule from Horaro.
     * 
     * @param string $scheduleId The schedule ID.
     * @param string $eventId The event ID.
     * 
     * @return string|bool The schedule ident slug if the schedule was loaded successfully, false if not.
     */
    public function loadSchedule($scheduleId, $eventId = null)
    {
        return $this->plugin->loadSchedule($scheduleId, $eventId);
    }

    /**
     * Loads a schedule from its public Horaro URL.
     * 
     * @param string $url The schedule URL.
     * 
     * @return string|bool The schedule ident slug if the schedule was loaded successfully, false if not.
     */
    public function loadScheduleFromURL($url)
    {
        return $this->plugin->loadScheduleFromURL($url);
    }

    /**
     * Goes to the next item in the schedule.
     * 
     * @param string $identSlug The schedule's ident slug.
     * 
     * @return bool True if the schedule skipped successfully, false if not.
     */
    public function nextItem($identSlug)
    {
        return $this->plugin->nextItem($identSlug);
    }

    /**
     * Goes to the previous item in the schedule.
     * 
     * @param string $identSlug The schedule's ident slug.
     * 
     * @return bool True if the schedule has been rewinded successfully, false if not.
     */
    public function previousItem($identSlug)
    {
        return $this->plugin->previousItem($identSlug);
    }

    public function goToItem($identSlug, $itemIndex)
    {
        return $this->plugin->goToItem($identSlug, $itemIndex);
    }

    /**
     * Pauses the schedule.
     * 
     * @param string $identSlug The ident slug of the schedule to pause.
     * 
     * @return bool True if the schedule has been paused successfully, false otherwise.
     */
    public function pauseSchedule($identSlug)
    {
        return $this->plugin->pauseSchedule($identSlug);
    }
    
    /**
     * Pauses the schedule.
     * 
     * @param string $identSlug The ident slug of the schedule to pause.
     * 
     * @return bool True if the schedule has been paused successfully, false otherwise.
     */
    public function resumeSchedule($identSlug)
    {
        return $this->plugin->resumeSchedule($identSlug);
    }

    /**
     * Deletes a schedule from the list of stored schedules.
     * 
     * @param $identSlug The ident slug of the schedule to delete.
     * 
     * @return bool True if the schedule was deleted successfully, false if not.
     */
    public function deleteSchedule($identSlug)
    {
        return $this->plugin->deleteSchedule($identSlug);
    }

    /**
     * Triggers a schedule data refresh from Horaro.
     * 
     * @param mixed $identSlug The ident slug of the schedule to refresh.
     * @return void 
     */
    public function refreshScheduleData($identSlug)
    {
        return $this->plugin->refreshScheduleData($identSlug);
    }

    /**
     * Gets the data from a schedule that the plugin fetched from Horaro.
     * 
     * @param string $identSlug The ident slug of the schedule to get the data from.
     * 
     * @return object|null The schedule data if found or null if not.
     */
    public function getScheduleData($identSlug)
    {
        $schedule = $this->plugin->getScheduleByIdentSlug($identSlug);

        if (empty($schedule)) {
            return null;
        }

        return $schedule->getData();
    }

    /**
     * Gets the data from all the schedules that the plugin fetched from Horaro.
     * 
     * @return array|null The schedules data.
     */
    public function getSchedulesData()
    {
        $schedulesData = [];
        $schedules = $this->plugin->getSchedules();

        /** @var Schedule $schedule */
        foreach($schedules as $schedule) {
            $schedulesData[$schedule->getIdentSlug()] = $schedule->getData();
        }

        return $schedulesData;
    }
}