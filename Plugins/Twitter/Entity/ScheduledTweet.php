<?php
namespace HedgeBot\Plugins\Twitter\Entity;

use JsonSerializable;
use DateTime;

/**
 * Scheduled tweet entity.
 */
class ScheduledTweet implements JsonSerializable
{
    /** @var string The scheduled tweet ID */
    protected $id;
    /** @var string The account the tweet will be posted on */
    protected $account;
    /** @var string The tweet content */
    protected $content;
    /** @var array The list of media URLs that are embedded into the tweet (images) */
    protected $media;
    /** @var bool Wether the tweet has already been sent or not */
    protected $sent;
    /** @var DateTime|null The time at which the tweet has been set */
    protected $sentTime;
    /** @var string The trigger type of the event. Value is one of the TRIGGER_* constants. */
    protected $trigger;
    /** @var DateTime|null The time at which the tweet should be sent (in case of a datetime trigger). */
    protected $sendTime;
    /** @var string The event on which to trigger the tweet (in case of an event trigger). */
    protected $event;
    /** @var array Additional constraints on data for the tweet to be triggered. */
    protected $constraints;
    /** @var string The channel this tweet refers to. Used to define the store context in constraints. */
    protected $channel;

    const TRIGGER_DATETIME = "datetime";
    const TRIGGER_EVENT = "event";

    const CONSTRAINT_STORE = "store";
    const CONSTRAINT_EVENT = "event";

    /**
     * Constructor.
     */
    public function __construct($id = null)
    {
        $this->id = $id;
        $this->sent = false;
        $this->media = [];
        $this->constraints = [];
    }

    // Getters and setters

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of account
     */ 
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set the value of account
     *
     * @return  self
     */ 
    public function setAccount($account)
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Get the value of content
     */ 
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set the value of content
     *
     * @return  self
     */ 
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the value of media
     */ 
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * Set the value of media
     *
     * @return self
     */ 
    public function setMedia(array $media)
    {
        $this->media = array_slice($media, 0, 4);

        return $this;
    }

    /**
     * Adds a media to the list of medias to be embedded in the tweet.
     *
     * @return self
     */
    public function addMedia($media)
    {
        if(count($this->media) < 4) {
            $this->media[] = $media;
        }
        
        return $this;
    }

    /**
     * Removes a media from the list of medias to be embedded in the tweet.
     *
     * @return self
     */
    public function removeMedia($media)
    {
        if(in_array($media, $this->media)) {
            $key = array_search($media, $this->media);
            unset($this->media[$key]);
        }

        return $this;
    }

    /**
     * Get the value of sent
     */ 
    public function isSent()
    {
        return $this->sent;
    }

    /**
     * Set the value of sent
     *
     * @return  self
     */ 
    public function setSent($sent)
    {
        $this->sent = $sent;

        return $this;
    }

    /**
     * Get the value of sentTime
     */ 
    public function getSentTime()
    {
        return $this->sentTime;
    }

    /**
     * Set the value of sentTime
     *
     * @return  self
     */ 
    public function setSentTime(DateTime $sentTime = null)
    {
        $this->sentTime = $sentTime;

        return $this;
    }

    /**
     * Get the value of trigger
     */ 
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * Set the value of trigger
     *
     * @return  self
     */ 
    public function setTrigger($trigger)
    {
        $this->trigger = $trigger;

        return $this;
    }

    /**
     * Get the value of sendTime
     */ 
    public function getSendTime()
    {
        return $this->sendTime;
    }

    /**
     * Set the value of sendTime
     *
     * @return  self
     */ 
    public function setSendTime(DateTime $sendTime = null)
    {
        $this->sendTime = $sendTime;

        return $this;
    }

    /**
     * Get the value of event
     */ 
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Set the value of event
     *
     * @return  self
     */ 
    public function setEvent($event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Get the value of constraints
     */ 
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * Set the value of constraints
     *
     * @return  self
     */ 
    public function setConstraints($constraints)
    {
        $this->constraints = $constraints;

        return $this;
    }

    /**
     * Adds a constraint.
     * 
     * @return self
     */
    public function addConstraint($constraint)
    {
        $this->constraints[] = $constraint;

        return $this;
    }

    /**
     * Removes a constraint.
     * 
     * @return self
     */
    public function removeConstraint($constraint)
    {
        if(in_array($constraint, $this->constraints)) {
            $key = array_search($constraint, $this->constraints);
            unset($this->constraints[$key]);
        }

        return $this;
    }
    
    /**
     * Get the value of channel
     */ 
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * Set the value of channel
     *
     * @return  self
     */ 
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    // Serialization and unserialization

    /**
     * Restores a scheduled tweet from an array representation.
     * 
     * @param array $data The scheduled tweet data.
     */
    public static function fromArray(array $data)
    {
        $obj = new ScheduledTweet();

        // Transform the dates if needed in source data
        if(!empty($data['sendTime']) && is_string($data['sendTime'])) {
            $data['sendTime'] = new DateTime($data['sendTime']);
        }

        if(!empty($data['sentTime']) && is_string($data['sentTime'])) {
            $data['sentTime'] = new DateTime($data['sentTime']);
        }

        foreach ($obj as $key => $value) {
            if (isset($data[$key])) {
                $obj->$key = $data[$key];
            }
        }

        // Handle business logic for the tweet integrity
        if(!$obj->sent) {
            $obj->setSentTime(null);
        }

        // Transform the constraints to arrays if needed
        foreach($obj->constraints as &$constraint) {
            if(!is_array($constraint)) {
                $constraint = (array) $constraint;
            }
        }

        return $obj;
    }

    /**
     * Converts the scheduled tweet to an array.
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'account' => $this->account,
            'channel' => $this->channel,
            'content' => $this->content,
            'media' => $this->media,
            'sent' => $this->sent,
            'sentTime' => $this->sentTime ? $this->sentTime->format('c') : null,
            'trigger' => $this->trigger,
            'sendTime' => $this->sendTime ? $this->sendTime->format('c') : null,
            'event' => $this->event,
            'constraints' => $this->constraints
        ];
    }

    /**
     * Prepares the data for JSON serialization.
     * 
     * @return array The data ready for serialization into JSON.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}