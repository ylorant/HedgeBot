<?php
namespace HedgeBot\Plugins\HoraroTextFile\Entity;

class FileMapping
{
    /** @var string $type The mapping type */
    protected $type;
    /** @var string $schedule The mapped schedule */
    protected $schedule;
    /** @var string $channel The mapped channel */
    protected $channel;
    /** @var string $path The file path to write on */
    protected $path;

    public function __construct()
    {
        
    }

    /**
     * Get the value of type
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @return  self
     */ 
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of schedule
     */ 
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * Set the value of schedule
     *
     * @return  self
     */ 
    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;

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

    /**
     * Get the value of path
     */ 
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the value of path
     *
     * @return  self
     */ 
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Instanciates a new FileMapping instance from an array representation.
     * 
     * @param array $data The data to create the array from.
     */
    public static function fromArray(array $data)
    {
        $newMapping = new FileMapping();
        
        foreach($this as $key => $value) {
            if(isset($data[$key])) {
                $newMapping->$key = $value;
            }
        }

        return $newMapping;
    }

    /**
     * Exports the mapping as an array for storage.
     * 
     * @return array The file mapping as an array.
     */
    public function toArray()
    {
        $output = [];

        foreach($this as $key => $value) {
            $output[$key] = $value;
        }

        return $output;
    }
}