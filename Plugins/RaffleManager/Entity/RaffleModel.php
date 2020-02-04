<?php
namespace HedgeBot\Plugins\RaffleManager\Entity;

class RaffleModel
{
    /** @var string The identifier of the raffle */
    protected $id;
    /** @var string The legible name of the raffle */
    protected $name;
    /** @var string The raffle command name */
    protected $command;
    /** @var string The message shown when the raffle is started */
    protected $message;
    /** @var array The list of channels on which the raffle model is available */
    protected $channels;
    /** @var int Raffle duration in seconds */
    protected $duration;

    const EXPORTED_KEYS = ['id', 'name', 'command', 'message', 'channels'];

    /**
     * Gets the raffle ID.
     * 
     * @return string The raffle ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the raffle ID.
     * 
     * @param string $id The raffle ID.
     * @return RaffleModel self
     */
    public function setId(string $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets the raffle name.
     * 
     * @return string The raffle name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the raffle name.
     * 
     * @param string $id The raffle name.
     * @return RaffleModel self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }
    
    /**
     * Gets the raffle command.
     * 
     * @return string The raffle command.
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Sets the raffle command.
     * 
     * @param string $id The raffle command.
     * @return RaffleModel self
     */
    public function setCommand(string $command)
    {
        $this->command = $command;

        return $this;
    }
    
    /**
     * Gets the raffle message.
     * 
     * @return string The raffle message.
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Sets the raffle message.
     * 
     * @param string $id The raffle message.
     * @return RaffleModel self
     */
    public function setMessage(string $message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get the value of duration
     */ 
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * Set the value of duration
     *
     * @return  self
     */ 
    public function setDuration($duration)
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Creates a raffle from an array representation. This is used when restoring a raffle from the storage.
     * 
     * @param array $data The data to restore the raffle from.
     * @return RaffleModel The created raffle.
     */
    public static function fromArray(array $data)
    {
        $raffle = new Raffle();

        foreach($raffle as $key => $value) {
            if(isset($data[$key])) {
                $raffle->$key = $data[$key];
            }
        }

        return $raffle;
    }

    /**
     * Converts the raffle to an array. This is used to store it.
     * 
     * @return array The store represented as an array.
     */
    public function toArray()
    {
        $out = [];

        foreach ($this as $key => $value) {
            if (in_array($key, self::EXPORTED_KEYS)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Generates an ID for new raffle models.
     * 
     * @return string A new generated ID. It *should* be unique.
     */
    public static function generateId()
    {
        return uniqid("", true);
    }
}