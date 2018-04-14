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
use HedgeBot\Core\API\Twitch\Kraken;
use HedgeBot\Plugins\Horaro\Event\HoraroEvent;
use HedgeBot\Core\Store\StoreSourceInterface;
use HedgeBot\Core\API\Store;
use HedgeBot\Core\Store\Formatter\TextFormatter;

class Horaro extends PluginBase implements StoreSourceInterface
{
    /** @var HoraroAPI Horaro API Client instance */
    protected $horaro;
    /** @var array The list of schedules currently loaded in the bot, and their current state */
    protected $schedules;
    /** @var int Refresh schedules current index */
    protected $refreshScheduleIndex;

    const SOURCE_NAMESPACE = "Horaro";
    const CURRENT_DATA_SOURCE_PATH = self::SOURCE_NAMESPACE.".schedule.currentItem.data";
    const NEXT_DATA_SOURCE_PATH = self::SOURCE_NAMESPACE.".schedule.nextItem.data";

    /**
     * Plugin initialization.
     */
    public function init()
    {
        $this->horaro = new HoraroAPI();
        $this->schedules = [];
        $this->refreshScheduleIndex = -1; // Since we pre-increment the current index, we will use -1 to start at 0.

        $this->horaro->setErrorHandler([$this, 'onHoraroError']);

        Plugin::getManager()->addRoutine($this, "RoutineProcessSchedules", 60);
        Plugin::getManager()->addRoutine($this, "RoutineRefreshSchedules", $this->config['refreshInterval'] ?? 300);
        Plugin::getManager()->addRoutine($this, "RoutineCheckAsyncRequests", 1);
        Plugin::getManager()->addEventListener(HoraroEvent::getType(), 'Horaro');
    
        Store::registerSource($this);

        
        $this->loadData();
    }
    
    // Store implementation

    /**
     * Provides the store with schedule data.
     * 
     * @param string $channel The channel from which the data will be restricted.
     * 
     * @return array The schedule data for the store.
     */
    public function provideStoreData($channel = null)
    {
        $storeData = [];
        
        $runningSchedules = $this->getCurrentlyRunningSchedules($channel);

        /** @var Schedule $schedule */
        foreach($runningSchedules as &$schedule)
        {
            $columns = $schedule->getColumns(true);
            $currentItem = $schedule->getCurrentItem();
            $nextItem = $schedule->getNextItem();

            $schedule = $schedule->toArray();
            $schedule['currentItem'] = (array) $currentItem;
            $schedule['currentItem']['data'] = array_combine($columns, $schedule['currentItem']['data']);
            $schedule['nextItem'] = null;

            if(!is_null($nextItem))
            {
                $schedule['nextItem'] = (array) $nextItem;
                $schedule['nextItem']['data'] = array_combine($columns, $schedule['nextItem']['data']);
            }
        }

        // If a specific channel is asked, we get only the first running schedule, anyway, there should be only one.
        if(!empty($channel))
            $storeData['schedule'] = reset($runningSchedules);
        else
            $storeData['schedules'] = $runningSchedules;
        
        return $storeData;
    }

    /**
     * @inheritdoc
     */
    public static function getSourceNamespace()
    {   
        return self::SOURCE_NAMESPACE;
    }

    // Routines

    /**
     * This routine checkes the Horaro API client for asynchronous replies.
     */
    public function RoutineCheckAsyncRequests()
    {
        $this->horaro->asyncListen();
    }

    /**
     * Schedule management routine. Basically handles all the automatic schedule managmeent.
     * 
     * @param string $identSlug If given, the schedule processing will be limited to this ident slug.
     */
    public function RoutineProcessSchedules($identSlug = null)
    {
        HedgeBot::message("Checking Horaro schedules...", [], E_DEBUG);
        $now = new DateTime($this->config['simulatedTime'] ?? null);
        
        /** @var Schedule $schedule */
        foreach($this->schedules as $currentSlug => $schedule)
        {
            // Check for the given ident slug limitation if necessary, and skip if they differ
            if(!empty($identSlug) && $currentSlug != $identSlug)
                continue;

            HedgeBot::message("Checking schedule $0...", [$schedule->getIdentSlug()], E_DEBUG);

            // Only process schedules that are enabled and not paused (duh)
            if(!$schedule->isEnabled() || $schedule->isPaused())
            {
                HedgeBot::message("Schedule is disabled and/or paused. Skipping.", [], E_DEBUG);
                continue;
            }

            $scheduleStartTime = $schedule->getStartTime();
            $scheduleEndTime = $schedule->getEndTime();

            // Schedule isn't started, we check if it's past its start time (and before its end time)
            // and we fast forward to the current item if necessary
            if(!$schedule->isStarted())
            {
                HedgeBot::message("Schedule isn't started.", [], E_DEBUG);

                // We're started, so we fast forward to the current item, set the title and mark the schedule as started
                if($now > $scheduleStartTime && $now < $scheduleEndTime)
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
                            Plugin::getManager()->callEvent(new HoraroEvent('scheduleUpdated', $schedule));
                            $this->saveData();
                            break;
                        }
                    }
                }
                elseif($now > $scheduleEndTime) // The schedule is outdated, we disable it to save some processing time
                {
                    $schedule->setEnabled(false);
                    $this->saveData();
                }

                continue;
            }
            
            HedgeBot::message("Schedule is started.", [], E_DEBUG);

            // Disabling the schedule if it has ended
            if($now > $scheduleEndTime)
            {
                HedgeBot::message("Schedule has ended, disabling.", [], E_DEBUG);
                $schedule->setEnabled(false);
                $schedule->setStarted(false);
                $this->saveData();

                continue;
            }

            // Schedule is started, we check compared to the current item
            $currentItem = $schedule->getCurrentItem();
            $nextItem = $schedule->getNextItem();

            // Get current item end time and next item start time
            $currentItemEndTime = new DateTime($currentItem->scheduled);
            $currentItemEndTime->add(new DateInterval($currentItem->length));
            $nextItemStartTime = null;
            $nextItemAnnounceThresholdTime = null;

            // Compute the next item announce threshold time if needed
            if(!empty($nextItem))
            {
                $nextItemStartTime = new DateTime($nextItem->scheduled);
                
                if($this->config['announceNextItem'] && isset($this->config['announceNextDelay']))
                {
                    $nextItemAnnounceThresholdTime = clone $nextItemStartTime;
                    $nextItemAnnounceThresholdTime->sub(new DateInterval($schedule->getData('setup')));
                    $nextItemAnnounceThresholdTime->sub(new DateInterval('PT'. $this->config['announceNextDelay']. 'S'));
                }
            }

            // Increment the item and change the title and game for the stream when coming on the next run time
            if(!empty($nextItemStartTime) && $now > $nextItemStartTime)
            {
                HedgeBot::message("Previous item is finished, advancing.", [], E_DEBUG);
                $totalItems = count($schedule->getData('items'));
                $schedule->setCurrentIndex($schedule->getCurrentIndex() + 1);
                $schedule->setNextItemAnnounced(false);
                
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
                Plugin::getManager()->callEvent(new HoraroEvent('scheduleUpdated', $schedule));
                $this->saveData();
            }
            elseif(!empty($nextItemAnnounceThresholdTime) && $now >= $nextItemAnnounceThresholdTime && !$schedule->isNextItemAnnounced())
            {
                // Announce the next item and mark it as announced
                $textFormatter = Store::getFormatter(TextFormatter::getName());
                $announceMessage = $textFormatter->format($schedule->getAnnounceTemplate(), $schedule->getChannel(), self::NEXT_DATA_SOURCE_PATH);

                IRC::message($schedule->getChannel(), $announceMessage);
                $schedule->setNextItemAnnounced(true); 
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
        $enabledSchedules = $this->getEnabledSchedules();
        if(!empty($enabledSchedules))
        {
            // Increment the currently refreshed schedule index, and make sure it's pointing to a current schedule
            $this->refreshScheduleIndex++;
            if($this->refreshScheduleIndex >= count($enabledSchedules))
                $this->refreshScheduleIndex = 0;
            
            // Get the correct schedule corresponding to the current index with its key.
            $scheduleKeys = array_keys($enabledSchedules);
            $schedule = $enabledSchedules[$scheduleKeys[$this->refreshScheduleIndex]];

            // Finally, fetch the new schedule data
            $newScheduleData = $this->horaro->getScheduleAsync($schedule->getScheduleId(), $schedule->getEventId(), null, [$this, 'onScheduleReceived']);
            
            if(!empty($newScheduleData))
                $schedule->setData($newScheduleData);
        }
    }

    // Callbacks

    /**
     * Event called when a schedule is received by an async call (when updating).
     * 
     * @param string $scheduleId The ID of the schedule.
     * @param string $eventId The ID of the event to which belongs the schedule.
     * @param string $scheduleData The data fetched from the API.
     */
    public function onScheduleReceived($scheduleId, $eventId, $scheduleData)
    {
        $schedule = $this->getScheduleById($scheduleId, $eventId);
        $schedule->setData($scheduleData);
    }

    /**
     * Event called when an error has occured on a schedule retrieval method.
     * 
     * @param int $curlError The error that cURL has threw. If the error comes from the API and not cURL itself, equals CURLE_OK.
     * @param resource $curlHandler The cURL handler, to get additional info from.
     * @param array $parameters The parameters that should have been given to the callback if the call had succeeded.
     * @param object $data The data that has been returned by the curlHandler if the request succeeded but the API returned an error.
     */
    public function onHoraroError($curlError, $curlHandler = null, $parameters = [], $data = null)
    {
        // Check if the HTTP reply denotes a schedule not found error
        if($curlError == CURLE_OK && !empty($data) && $data->status == 404)
        {
            // Getting the schedules by the parameters that should've been passed to the success callback
            $schedule = $this->getScheduleById($parameters[0], $parameters[1]);
            HedgeBot::message("Failed getting schedule data for schedule $0, disabling it.", [$schedule->getIdentSlug()], E_WARNING);
            $schedule->setEnabled(false);
            $this->saveData();
        }
    }

    // Core events

    /**
     * Data has been updated externally, maybe that means the schedules have changed ?
     * In any case, we reload the schedules.
     */
    public function CoreEventDataUpdate()
    {
        $this->loadData();
    }

    /**
     * Config has been updated externally, we reload the refresh interval.
     */
    public function CoreEventConfigUpdate()
    {
        // TODO: Find a way to avoid to re-find the configuration manually
		$this->config = HedgeBot::getInstance()->config->get('plugin.Horaro');
        Plugin::getManager()->changeRoutineTimeInterval($this, "RoutineRefreshSchedules", $this->config['refreshInterval']);
    }

    // Chat commands

    /**
     * Pauses the given schedule, or the current schedule if none is given (2 schedules running at the same time would be strange though)
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
            
            $currentSchedule = reset($currentSchedules);
            $identSlug = $currentSchedule->getIdentSlug();
        }
        else // Ident slug is given, we check that it exists
        {
            $identSlug = $event->arguments[0];
            if(!$this->hasScheduleIdentSlug($identSlug))
                return IRC::reply($event, "Schedule not found.");
        }

        $schedule = $this->getScheduleByIdentSlug($identSlug);
        $schedule->setPaused(true);
        $schedule->setStarted(false); // Set started status as false, that way when we'll resume, it'll fast forward to whatever item it is.

        // Save the schedule
        $this->saveData();

        IRC::reply($event, "Schedule paused.");
    }

    /**
     * Resumes the given schedule, or the current schedule if none is given.
     */
    public function CommandResume(CommandEvent $event)
    {
        // Try to guess the event slug if not given
        if(empty($event->arguments[0]))
        {
            $currentSchedules = $this->getCurrentlyRunningSchedules($event->channel);
            if(count($currentSchedules) > 1)
                return IRC::reply($event, "Couldn't automatically determine which schedule to pause, please specify an ident slug.");
            elseif(count($currentSchedules) == 0)
                return IRC::reply($event, "No schedule is currently running.");
            
            $currentSchedule = reset($currentSchedules);
            $identSlug = $currentSchedule->getIdentSlug();
        }
        else // Ident slug is given, we check that it exists
        {
            $identSlug = $event->arguments[0];
            if(!$this->hasScheduleIdentSlug($identSlug))
                return IRC::reply($event, "Schedule not found.");
        }

        $schedule = $this->getScheduleByIdentSlug($identSlug);
        $schedule->setPaused(false);

        // Save the schedule
        $this->saveData();

        $this->RoutineProcessSchedules($identSlug);

        IRC::reply($event, "Schedule resumed.");
    }

    /**
     * Skips the current item on the given schedule or the one given as argument, and goes straight to the next one.
     */
    public function CommandNext(CommandEvent $event)
    {
        // Try to guess the event slug if not given
        if(empty($event->arguments[0]))
        {
            $currentSchedules = $this->getCurrentlyRunningSchedules($event->channel);
            if(count($currentSchedules) > 1)
                return IRC::reply($event, "Couldn't automatically determine which schedule to pause, please specify an ident slug.");
            elseif(count($currentSchedules) == 0)
                return IRC::reply($event, "No schedule is currently running.");
            
            $currentSchedule = reset($currentSchedules);
            $identSlug = $currentSchedule->getIdentSlug();
        }
        else // Ident slug is given, we check that it exists
        {
            $identSlug = $event->arguments[0];
            if(!$this->hasScheduleIdentSlug($identSlug))
                return IRC::reply($event, "Schedule not found.");
        }

        $schedule = $this->getScheduleByIdentSlug($identSlug);
        $schedule->setCurrentIndex($schedule->getCurrentIndex() + 1);

        // Update title & game
        $this->setChannelTitleFromSchedule($schedule);

        // Save the schedule
        $this->saveData();

        IRC::reply($event, "Item has been skipped.");
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
     * Gets a schedule by its schedule ID and, if given, by its event ID.
     * 
     * @param string $scheduleId The schedule ID.
     * @param string $eventId    The even ID, optional.
     * 
     * @return Schedule|null The schedule object if found, null if not found.
     */
    public function getScheduleById($scheduleId, $eventId = null)
    {
        /** @var Schedule $schedule */
        foreach($this->schedules as $schedule)
        {
            if($schedule->getScheduleId() == $scheduleId && (is_null($eventId) || $schedule->getEventId() == $eventId))
                return $schedule;
        }

        return null;
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
        $currentTime = new DateTime($this->config['simulatedTime']);

        foreach($this->schedules as $identSlug => $schedule)
        {
            $startTime = $schedule->getStartTime();
            $endTime = $schedule->getEndTime();

            if($currentTime > $startTime && $currentTime < $endTime && (empty($channel) || $schedule->getChannel() == $channel))
                $runningSchedules[$identSlug] = $schedule;
        }

        return $runningSchedules;
    }

    /**
     * Gets the enabled schedules.
     * 
     * @return array The schedules that are enabled.
     */
    public function getEnabledSchedules()
    {
        $enabledSchedules = [];
        foreach($this->schedules as $identSlug => $schedule)
        {
            if($schedule->isEnabled())
                $enabledSchedules[$identSlug] = $schedule;
        }

        return $enabledSchedules;
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
        $titleTemplate = $schedule->getTitleTemplate();
        $gameTemplate = $schedule->getGameTemplate();
        $channel = $schedule->getChannel();

        HedgeBot::message("Updating title from Horaro for channel $0", [$channel], E_DEBUG);

        // Format the title and the game with the text formatter, providing a root namespace to avoid 
        $textFormatter = Store::getFormatter(TextFormatter::getName());
        $channelTitle = $textFormatter->format($titleTemplate, $channel, self::CURRENT_DATA_SOURCE_PATH);
        $channelGame = $textFormatter->format($gameTemplate, $channel, self::CURRENT_DATA_SOURCE_PATH);
        
        HedgeBot::message("New title: $0", [$channelTitle], E_DEBUG);
        HedgeBot::message("New game: $0", [$channelGame], E_DEBUG);

        Kraken::get('channels')->update($channel, ['title' => $channelTitle, 'game' => $channelGame]);
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
        $saveData = false;

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

                if($scheduleData)
                    $scheduleObj->setData($scheduleData);
                else // We skip loading the schedule if getting the schedule data fails.
                {
                    HedgeBot::message("Failed getting schedule data for schedule $0, disabling it.", [$identSlug], E_WARNING);
                    $scheduleObj->setEnabled(false);
                    $saveData = true; // Since we disabled a schedule, we mark the data to be saved after loading
                }
            }

            $this->schedules[$identSlug] = $scheduleObj;
        }

        if($saveData)
            $this->saveData();
    }
}