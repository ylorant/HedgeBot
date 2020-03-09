<?php
namespace HedgeBot\Plugins\HoraroTextFile\Entity;

class FileMapping
{
    /** @var string $type The mapping type */
    protected $type;
    /** @var string $id The mapping identifier, either a schedule ident slug or a channel name */
    protected $id;
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
        
        foreach($newMapping as $key => $value) {
            if(isset($data[$key])) {
                $newMapping->$key = $data[$key];
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