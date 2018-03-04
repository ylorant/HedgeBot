<?php
namespace HedgeBot\Plugins\Horaro;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\Service\Horaro\Horaro as HoraroAPI;
use HedgeBot\Core\API\Config;
use HedgeBot\Plugins\Horaro\Entity\Schedule;
use HedgeBot\Core\API\Data;
use DateTime;
use DateInterval;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;

class Horaro extends PluginBase
{
    /** @var HoraroAPI Horaro API Client instance */
    protected $horaro;
    /** @var array The list of schedules currently loaded in the bot, and their current state */
    protected $schedules;
    /** @var int Refresh schedules current index */
    protected $refreshScheduleIndex;

    /**
     * Plugin initialization.
     */
    public function init()
    {
        $this->horaro = new HoraroAPI();
        $this->schedules = [];
        $this->refreshScheduleIndex = 0;

        Plugin::getManager()->addRoutine($this, "RoutineProcessSchedules", 60);
        Plugin::getManager()->addRoutine($this, "RoutineRefreshSchedules", 900);
        
        $this->loadData();
    }
    
    // Routines

    /**
     * Schedule management routine. Basically handles all the automatic schedule managmeent.
     */
    public function RoutineProcessSchedules()
    {
        HedgeBot::message("Checking Horaro schedules...", [], E_DEBUG);
        $now = new DateTime();
        
        /** @var Schedule $schedule */
        foreach($this->schedules as $identSlug => $schedule)
        {
            HedgeBot::message("Checking schedule $0...", [$schedule->getIdentSlug()], E_DEBUG);

            // Only process schedules that are enabled and not paused (duh)
            if(!$schedule->isEnabled() || $schedule->isPaused())
            {
                HedgeBot::message("Schedule is disabled and/or paused. Skipping.", [], E_DEBUG);
                continue;
            }

            // Schedule isn't started, we check if it's past its start time (and before its end time)
            // and we fast forward to the current item if necessary
            if(!$schedule->isStarted())
            {
                HedgeBot::message("Schedule isn't started.", [], E_DEBUG);
                $startTime = $schedule->getStartTime();
                $endTime = $schedule->getEndTime();

                // We're started, so we fast forward to the current item, set the title and mark the schedule as started
                if($now > $startTime && $now < $endTime)
                {
                    HedgeBot::message("We're in the schedule, starting it.", [], E_DEBUG);

                    $schedule->setStarted(true);

                    $scheduleItems = $schedule->getData('items');
                    foreach($scheduleItems as $index => $item)
                    {
                        $itemStartTime = new DateTime($item->scheduled);
                        $itemEndTime = clone $itemStartTime;
                        $itemEndTime->add(new DateInterval($item->length));

                        // If the item is the one currently running
                        if($now >= $itemStartTime && $now < $itemEndTime) {
                            HedgeBot::message("Current item index: $0, setting title", [$index], E_DEBUG);
                            $schedule->setCurrentIndex($index);
                            $this->setChannelTitleFromSchedule($schedule);
                            $this->saveData();
                            break;
                        }
                    }
                }
                elseif($now > $endTime) // The schedule is outdated, we disable it to save some processing time
                {
                    $schedule->setEnabled(false);
                    $this->saveData();
                }

                continue;
            }
            
            HedgeBot::message("Schedule is started.", [], E_DEBUG);

            // Schedule is started, we check compared to the current item
            $currentItem = $schedule->getCurrentItem();
            $itemEndTime = new DateTime($currentItem->scheduled);
            $itemEndTime->add(new DateInterval($currentItem->length));
            $itemEndTime->add(new DateInterval($schedule->getData('setup')));

            if($now > $itemEndTime)
            {
                HedgeBot::message("Previous item is finished, advancing.", [], E_DEBUG);
                $totalItems = count($schedule->getData('items'));
                $schedule->setCurrentIndex($schedule->getCurrentIndex() + 1);
                
                // We've reached the end of the schedule
                if(!$schedule->getCurrentItem())
                {
                    HedgeBot::message("Reached end of schedule, disabling it.", [], E_DEBUG);
                    $schedule->setCurrentIndex(0);
                    $schedule->setStarted(false);
                    $schedule->setEnabled(false);
                    $this->saveData();
                    
                    continue;
                }

                // Set the new schedule item, since we're not at the end
                $this->setChannelTitleFromSchedule($schedule);
                $this->saveData();
            }
        }
    }

    /**
     * This routine refreshes all the schedule data from Horaro on the schedules.
     * It does that one schedule at a time to avoid excessive slowdowns if the API becomes laggy.
     */
    public function RoutineRefreshSchedules()
    {
        if(!empty($this->schedules))
        {
            
        }
    }

    // Core events

    /**
     * Data has been updated externally, maybe that means the schedules have changed ?
     * In any case, we reload the schedules
     */
    public function CoreEventDataUpdate()
    {
        $this->loadData();
    }

    // Chat commands

    /**
     * Pauses the diven schedule, or the current schedule if none is given (2 schedules running at the same time would be strange though)
     */
    public function CommandPause(CommandEvent $event)
    {
        // Try to guess the event slug if not given
        if(empty($event->arguments[0]))
        {
            $currentSchedules = $this->getCurrentlyRunningSchedules($event->channel);
            if(count($currentSchedules) > 1)
                return IRC::reply($event, "Couldn't automatically determine which schedule to pause, please specify an ident slug.");
            elseif(count($currentSchedules) == 0)
                return IRC::reply($event, "No schedule is currently running.");
            
            $identSlug = $currentSchedules[0]->getIdentSlug();
        }
        else // Ident slug is given, we check that it exists
        {
            $identSlug = $event->arguments[0];
            if(!$this->hasScheduleIdentSlug($identSlug))
                return IRC::reply($event, "Schedule not found.");
        }

        $scheedule = $this->getScheduleByIdentSlug($identSlug);
        $scheedule->setPaused(true);
        $scheedule->setStarted(false); // Set started status as false, that way when we'll resume, it'll fast forward to whatever item it is.

        // Save the schedule
        $this->saveData();

        IRC::reply($event, "Schedule paused.");
    }

    // Schedule management methods, called by console commands and API //
    
    /**
     * Loads a schedule into the bot.
     * 
     * @param string $scheduleId The schedule ID/slug to load.
     * @param string $eventId    The event ID to get the schedule from. Can be omitted. Sometimes is needed if the schedule slug is too generic.
     * 
     * @return string|bool The schedule ident slug if the schedule was loaded correctly, false if not. 
     *                     Mainly that means that the schedule was not found or that it has already been loaded.
     */
    public function loadSchedule($scheduleId, $eventId = null)
    {
        // Check if the schedule isn't already in our database
        if($this->hasScheduleId($scheduleId, $eventId))
            return false;
    
        $scheduleData = null;
        if(!$this->scheduleExists($scheduleId, $eventId, $scheduleData))
            return false;
        
        // Create schedule and check if it exists on Horaro
        $schedule = new Schedule($scheduleId, $eventId);
        $schedule->setData($scheduleData);
        
        $scheduleIdentSlug = $schedule->getIdentSlug();
        $this->schedules[$scheduleIdentSlug] = $schedule;
        
        return $scheduleIdentSlug;
    }

    /**
     * Unloads a schedule from the bot.
     * 
     * @param string $identSlug The ident slug to unload.
     * 
     * @return bool True if the schedule has been unloaded successfully, false if not.
     */
    public function unloadSchedule($identSlug)
    {
        if(!$this->hasScheduleIdentSlug($identSlug))
            return false;
        
        unset($this->schedules[$identSlug]);
        
        return true;
    }

    /**
     * Checks if a schedule exsists in the loaded schedules of the bot.
     * 
     * @param string $scheduleId The schedule ID to load.
     * @param string $eventId    The event ID to get the schedule from. Can be omitted.
     * 
     * @return bool True if the schedule exists, False if not.
     */
    public function hasScheduleId($scheduleId, $eventId = null)
    {
        /** @var Schedule $schedule */
        foreach($this->schedules as $schedule)
        {
            if($schedule->getScheduleId() == $scheduleId && (is_null($eventId) || $schedule->getEventId() == $eventId))
                return true;
        }

        return false;
    }

    /**
     * Checks if the given schedule ident slug exists within the loaded schedules.
     * 
     * @param string $identSlug The schedule ident slug to look for.
     * 
     * @return bool True if the schedule ident slug has been found, false otherwise.
     */
    public function hasScheduleIdentSlug($identSlug)
    {
        return isset($this->schedules[$identSlug]);
    }

    /**
     * Gets a schedule by its ident slug.
     * 
     * @param string $identSlug The ident slug of the schedule to fetch.
     * 
     * @return Schedule|null The schedule if found, null if not.
     */
    public function getScheduleByIdentSlug($identSlug)
    {
        if(isset($this->schedules[$identSlug]))
            return $this->schedules[$identSlug];
        
        return null;
    }

    /**
     * Gets the currently running schedules, i.e. Those who are enabled and currently in process, time-wise.
     * 
     * @param string $channel Filter the schedules by channel.
     * 
     * @return array The list of schedules that are currently running. If none are found, an empty array is returned.
     */
    public function getCurrentlyRunningSchedules($channel = null)
    {
        $runningSchedules = [];
        $currentTime = new DateTime();

        foreach($this->schedules as $identSlug => $schedule)
        {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();

            if($currentTime > $startTime && $currentTime < $endTime)
                $runningSchedules[$identSlug] = $schedule;
        }

        return $runningSchedules;
    }

    /**
     * Checks that a schedule exists on the Horaro API.
     * 
     * @param string $scheduleId The schedule ID/slug to check the existence of.
     * @param string $eventId    The event ID/slug that the schedule belongs to. Sometimes is needed if the schedule slug is too generic.
     * @param object $schedule   Reference to a var where the schedule data will be put if found, to avoid useless multiple calls.
     * 
     * @return bool True if the schedule exists on Horaro, false if it doesn't.
     */
    public function scheduleExists($scheduleId, $eventId = null, &$schedule = null)
    {
        $schedule = $this->horaro->getSchedule($scheduleId, $eventId);
        return $schedule !== false;
    }

    /**
     * Sets the channel title from the schedule.
     * TODO: replace the direct whisper to the bot by a Twitch API request.
     * 
     * @param Schedule $schedule The schedule to set the channel title from.
     */
    public function setChannelTitleFromSchedule(Schedule $schedule)
    {
        $currentItem = $schedule->getCurrentItem();
        $channelTitle = $schedule->getCurrentTitle();
        $channelGame = $schedule->getCurrentGame();
        $channel = $schedule->getChannels();

        // Set the title & game
        if($this->config['whisperOverride'])
        {
            IRC::whisper($this->config['whisperOverride'], '!settitle '. $channelTitle);
            IRC::whisper($this->config['whisperOverride'], '!setgame '. $channelGame);
        }
        else
        {
            IRC::message($channel, '!settitle '. $channelTitle);
            IRC::message($channel, '!setgame '. $channelGame);
        }
    }

    /**
     * Saves the schedule data into the storage.
     */
    public function saveData()
    {
        HedgeBot::message("Saving schedules...", [], E_DEBUG);

        $schedules = [];

        /** @var Schedule $schedule */
        foreach($this->schedules as $identSlug => $schedule)
            $schedules[$identSlug] = $schedule->toArray();

        $this->data->schedules = $schedules;
    }

    /**
     * Loads the schedule data from the storage.
     * This method will do a diff between the currently loaded schedules, and will reload them at need (since it calls Horaro, it is a
     * time-consuming operation to reload a schedule).
     */
    public function loadData()
    {
        HedgeBot::message("Loading schedules...", [], E_DEBUG);

        $schedules = $this->data->schedules->toArray();

        // Reset the actual schedule list, but keep the refs into a separate var, if a schedule has not been modified, it will not be reloaded that way.
        $oldSchedules = $this->schedules;
        $this->schedules = [];

        if(empty($schedules))
            return;

        foreach($schedules as $identSlug => $schedule)
        {
            $scheduleObj = Schedule::fromArray($schedule);
            $loadSchedule = false;

            // If the schedule isn't already in the schedule list, we load it.
            if(isset($oldSchedules[$identSlug]))
            {
                // We try to reload the data in the new schedule from the previous one, and if that fails, we trigger a full reload
                $dataLoaded = $scheduleObj->loadDataFromSchedule($oldSchedules[$identSlug]);
                if(!$dataLoaded)
                    $loadSchedule = true;
            }
            else
                $loadSchedule = true;

            // Fetch schedule data from Horaro and inject it into the object if needed
            if($loadSchedule)
            {
                $scheduleData = $this->horaro->getSchedule($scheduleObj->getScheduleId(), $scheduleObj->getEventId());
                $scheduleObj->setData($scheduleData);
            }

            $this->schedules[$identSlug] = $scheduleObj;
        }
    }
}