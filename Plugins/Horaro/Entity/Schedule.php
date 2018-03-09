<?php
namespace HedgeBot\Plugins\Horaro\Entity;

use HedgeBot\Core\Service\Horaro\Horaro;
use DateTime;
use DateInterval;

class Schedule
{
    /** @var string The event ID. Can be null. */
    protected $eventId;
    /** @var string The schedule ID. */
    protected $scheduleId;
    /** @var bool True if the event is enabled, false if not. */
    protected $enabled;
    /** @var bool True if the schedule is currently on pause (run advance is being ignored), false if not. */
    protected $paused;
    /** @var object The schedule data, fetched from Horaro. The using entity is expected to fill this. */
    protected $data;
    /** @var string The channel where the schedule is active */
    protected $channel;
    /** @var int The index of the current item on the schedule, to handle manual sync */
    protected $currentIndex;
    /** @var bool Wether the schedule has started or not, to allow the plugin to set the first item */
    protected $started;
    /** @var string The template for the channel title while this schedule is running */
    protected $titleTemplate;
    /** @var string The template for the channel game while this schedule is running. Usually it's the column var for the game name. */
    protected $gameTemplate;
    /** @var string The template for the announce for the next item. It'll be used only if you enable the announce feature in the config.  */
    protected $announceTemplate;
    /** @var bool Wether the next item has been already announced in the chat or not. */
    protected $nextItemAnnounced;

    // Exported keys for the toArray() method
    const EXPORTED_KEYS = [
        "eventId",
        "scheduleId",
        "enabled",
        "paused",
        "channel",
        "currentIndex",
        "started",
        "titleTemplate",
        "gameTemplate",
        "announceTemplate",
        "nextItemAnnounced"
    ];

    public function __construct($scheduleId = null, $eventId = null)
    {
        $this->scheduleId = $scheduleId;
        $this->eventId = $eventId;
        $this->enabled = true;
        $this->paused = false;
        $this->started = false;
        $this->currentIndex = 0;
        $this->channel = "";
        $this->nextItemAnnounced = false;
    }

    // Property access methods

    /**
     * Gets the event ID.
     * 
     * @return string The event ID.
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * Sets the event ID.
     * 
     * @param string $eventId The event ID.
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * Gets the Schedule ID.
     * 
     * @return string The schedule ID.
     */
    public function getScheduleId()
    {
        return $this->scheduleId;
    }

    /**
     * Sets the schedule ID.
     * 
     * @param string $scheduleId The schedule ID.
     */
    public function setScheduleId($scheduleId)
    {
        $this->scheduleId = $scheduleId;
    }

    /**
     * Gets the schedule data. A key can be specified to access a specific portion of the data.
     * 
     * @param string $key The key to get in the data object.
     * 
     * @return mixed The data object or the value in the provided key if given.
     */
    public function getData($key = null)
    {
        if(!empty($key))
            return $this->data->$key ?? null;

        return $this->data;
    }

    /**
     * Sets the schedule data.
     * 
     * @param object $data The schedule data to set.
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Returns wether the schedule is enabled or not.
     * 
     * @return bool True if the plugin is enabled, false otherwise.
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Sets the enabled status of the schedule.
     * 
     * @param bool $enabled True if the schedule is enabled, false if not.
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Returns wether the schedule is on forced pause or not.
     * 
     * @return bool True if the schedule is paused, false if not.
     */
    public function isPaused()
    {
        return $this->paused;
    }

    /**
     * Sets wether the schedule is on forced pause or not.
     * 
     * @param bool $paused True to pause the schedule, false to disable the pause.
     */
    public function setPaused($paused)
    {
        $this->paused = $paused;
    }

    /**
     * Gets the channels where the schedule is active.
     * 
     * @return array The list of channels where the schedule is active.
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * Sets the channels where the schedule is active.
     * 
     * @param string $channels The new channels.
     */
    public function setChannel($channels)
    {
        $this->channels = $channel;
    }

    /**
     * Gets the current item index for the schedule.
     * 
     * @return int The current item index.
     */
    public function getCurrentIndex()
    {
        return $this->currentIndex;
    }

    /**
     * Sets the current item index for the schedule.
     * 
     * @param int $currentIndex The nez current index.
     */
    public function setCurrentIndex($currentIndex)
    {
        $this->currentIndex = $currentIndex;
    }

    /**
     * Gets wether the bot is started or not. This var allows to handle the first time the schedule starts, to set the initial stream title.
     * 
     * @return bool True if the schedule is marked as started, false if not.
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Sets wether the schedule is started or not.
     * 
     * @param bool $started The schedule started status.
     * 
     * @see self::isStarted()
     */
    public function setStarted($started)
    {
        $this->started = $started;
    }

    /**
     * Gets the title template for the schedule.
     * 
     * @return string The title template.
     */
    public function getTitleTemplate()
    {
        return $this->titleTemplate;
    }

    /**
     * Sets the title template for the schedule.
     * 
     * @param string $titleTemplate The title template.
     */
    public function setTitleTemplate($titleTemplate)
    {
        $this->titleTemplate = $titleTemplate;
    }

    /**
     * Gets the game template string for this schedule.
     * 
     * @return string The game template.
     */
    public function getGameTemplate()
    {
        return $this->gameTemplate;
    }

    /**
     * Sets the game template string for this schedule.
     * 
     * @param string $gameTemplate The game template.
     */
    public function setGameTemplate($gameTemplate)
    {
        $this->gameTemplate = $gameTemplate;
    }

    /**
     * Gets the announce template that is used to announce the next run.
     * 
     * @return string The announce template.
     */
    public function getAnnounceTemplate()
    {
        return $this->announceTemplate;
    }

    /**
     * Sets the announce template that'll be used to announce the next run.
     * 
     * @param string $announceTemplate The announce template.
     */
    public function setAnnounceTemplate($announceTemplate)
    {
        $this->announceTemplate = $announceTemplate;
    }

    /**
     * Gets wether the next item has already been announced or not
     * 
     * @return bool True if the next item has been announced, false if not.
     */
    public function isNextItemAnnounced()
    {
        return $this->nextItemAnnounced;
    }

    /**
     * Sets wether the next item has been announced or not.
     * 
     * @param bool $nextItemAnnounced True if the next item has been announced; false if not.
     */
    public function setNextItemAnnounced($nextItemAnnounced)
    {
        $this->nextItemAnnounced = $nextItemAnnounced;
    }

    // Generated props access methods

    /**
     * Returns the schedule's ident slug. The ident slug is a legible, unique way to identify a schedule loaded
     * in the bot.
     * Truth: the Ident slug is generated from the public horaro URL for the event.
     * 
     * @return string The schedule's ident slug.
     */
    public function getIdentSlug()
    {
        $scheduleUrl = $this->getData('link');
        
        // Remove the Horaro hostname from the URL
        if(strpos($scheduleUrl, Horaro::HORARO_HOST) === 0)
            $scheduleUrl = substr($scheduleUrl, strlen(Horaro::HORARO_HOST));
        
        // Replace the slashes by dashes, as Fatboy Slim told us
        $scheduleIdentSlug = str_replace('/', '-', trim($scheduleUrl, '/'));

        return $scheduleIdentSlug;
    }

    /**
     * Gets the start time of the schedule.
     * 
     * @return DateTime The start time of the scheduie.
     */
    public function getStartTime()
    {
        return new DateTime($this->data->start);
    }

    /**
     * Gets the end time of the schedule. Basically the end time is the start time of the last item + its length.
     * 
     * @return DateTime The end time of the schedule.
     */
    public function getEndTime()
    {
        $lastItem = end($this->data->items);
        $endTime = new DateTime($lastItem->scheduled);
        $endTime->add(new DateInterval($lastItem->length));
        
        return $endTime;
    }

    /**
     * Gets the specified item in the schedule by its index.
     * 
     * @param int $index The item index.
     * 
     * @return object|null The item if found, null if not.
     */
    public function getItem($index)
    {
        if(!empty($this->data->items[$index]))
            return $this->data->items[$index];
        
        return null;
    }

    /**
     * Gets the current item.
     * 
     * @return object|null The current item or null if the item is not found.
     */
    public function getCurrentItem()
    {
        if(!empty($this->data->items[$this->currentIndex]))
            return $this->data->items[$this->currentIndex];
        
        return null;
    }

    /**
     * Gets the next item in the schedule, relative to the current set item.
     * 
     * @return object|null The next item, or null if the item isn't found.
     */
    public function getNextItem()
    {
        if(!empty($this->data->items[$this->currentIndex + 1]))
            return $this->data->items[$this->currentIndex + 1];
        
        return null;
    }

    /**
     * Gets the current title for the current item in the schedule.
     * 
     * @return string|null The current title formatted with the curent item data, or null if the item isn't found.
     */
    public function getCurrentTitle()
    {
        $currentItem = $this->getCurrentItem();

        if(empty($currentItem))
            return null;

        return $this->fillTemplateItem($this->titleTemplate, $currentItem);
    }

    /**
     * Gets the current game for the current item in the schedule.
     * 
     * @return string|null The current game formatted with the curent item data, or null if the item isn't found.
     */
    public function getCurrentGame()
    {
        $currentItem = $this->getCurrentItem();

        if(empty($currentItem))
            return null;

        return $this->fillTemplateItem($this->gameTemplate, $currentItem);
    }

    /**
     * Gets the announce for the next item.
     * 
     * @return string|null The announce for the next run.
     */
    public function getNextAnnounce()
    {
        $nextItem = $this->getNextItem();

        if(empty($nextItem))
            return null;
        
        return $this->fillTemplateItem($this->announceTemplate, $nextItem);
    }

    protected function fillTemplateItem($template, $item)
    {
        $columns = $this->getData('columns');
        
        // Prepend a $ to the columns name to prepare them for a big ol' str_replace
        foreach($columns as &$columnName)
            $columnName = "$". $columnName;
        
        $ret = str_replace($columns, $item->data, $template);

        //FIXME: Replace this dirty Markdown URL fix by something a bit more beautiful?
        $ret = preg_replace("#\[(.+)\]\((.+)\)#isU", "$1", $ret);

        return $ret;
    }

    // Serialization

    /**
     * Instanciates a Schedule instance from an array.
     * 
     * @param array $data The data to create the schedule from.
     * 
     * @return Schedule The generated schedule.
     */
    public static function fromArray(array $data)
    {
        $obj = new Schedule();

        foreach($obj as $key => $value)
        {
            if(isset($data[$key]))
                $obj->$key = $data[$key];
        }

        return $obj;
    }

    /**
     * Normalizes the current schedule into an array, for easier storage.
     * 
     * @return array The schedule, represented as an array.
     */
    public function toArray()
    {
        $out = [];

        foreach($this as $key => $value)
        {
            if(in_array($key, self::EXPORTED_KEYS))
                $out[$key] = $value;
        }
        
        return $out;
    }

    // Other methods

    /**
     * Loads the schedule data (fetched from Horaro) that is stored in another schedule.
     * Before loading it, the source schedule will be checked to see if they concern the same event.
     * If not, it will not try to import them and return false.
     * 
     * @param Schedule $schedule The schedule to import the data from.
     * 
     * @return bool True if the schedule data has been imported, false if not (because the schedule are detected to be on different Horaro schedules).
     */
    public function loadDataFromSchedule(Schedule $schedule)
    {
        // Check if the event ID is specified for both schedules and it's different or not
        if(!empty($schedule->getEventId()) && !empty($this->eventId) && $schedule->getEventId() != $this->eventId)
            return false;
        
        // Check the schedule ID
        if($schedule->getScheduleId() != $this->scheduleId)
            return false;
        
        // Schedules correspond, we import the data.
        $this->data = $schedule->getData();
        
        return true;
    }
}