<?php
namespace HedgeBot\Core\Logger;

use DateTime;
use HedgeBot\Core\Traits\Hydrator;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

/**
 * Log entity for the logger component.
 * 
 * @package HedgeBot\Core\Logger
 */
class Log implements JsonSerializable
{
    /** @var string $id Log ID */
    protected $id;
    /** @var string $category Log category */
    protected $category;
    /** @var string $type Log type */
    protected $type;
    /** @var DateTime $date Log date */
    protected $date;
    /** @var array $data Log data */
    protected $data = [];

    use Hydrator;

    /**
     * Constructor. Initializes the entity with the given data.
     * 
     * @param array $data Data to initialize the entity with.
     * @return self
     */
    public function __construct(array $data = [])
    {
        $this->hydrate($data);

        if(empty($this->id)) {
            $this->id = Uuid::uuid4();
        }

        if(!empty($this->date) && is_string($this->date)) {
            $this->date = new DateTime($this->date);
        }
    }

    /**
     * Get the value of id
     * 
     * @return string
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     * 
     * @param string $id
     *
     * @return self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of category
     * 
     * @return string|null
     */ 
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set the value of category
     * 
     * @param string $category
     *
     * @return self
     */ 
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get the value of type
     * 
     * @return string|null
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the value of type
     * 
     * @param string $type
     *
     * @return self
     */ 
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of date
     * 
     * @return DateTime
     */ 
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set the value of date
     * 
     * @param DateTime $date
     *
     * @return self
     */ 
    public function setDate(DateTime $date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get the value of data
     * 
     * @return array $data
     */ 
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the value of data
     * 
     * @param array $data
     *
     * @return self
     */ 
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }
    
    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return [
            'category' => $this->category,
            'type' => $this->type,
            'date' => $this->date->format('c'),
            'data' => $this->data
        ];
    }
}