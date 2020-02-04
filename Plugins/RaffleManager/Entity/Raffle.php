<?php
namespace HedgeBot\Plugins\RaffleManager\Entity;

class Raffle
{
    /** @var string Raffle ID */
    protected $id;
    /** @var string Raffle model ID */
    protected $raffleModelId;
    /** @var string Raffle channel */
    protected $channel;
    /** @var array Contestants */
    protected $contestants;
    /** @var DateTime Start time */
    protected $startTime;

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
     * @return Raffle self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of raffleModelId
     */ 
    public function getRaffleModelId()
    {
        return $this->raffleModelId;
    }

    /**
     * Set the value of raffleModelId
     *
     * @return Raffle self
     */ 
    public function setRaffleModelId($raffleModelId)
    {
        $this->raffleModelId = $raffleModelId;

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
     * @return Raffle self
     */ 
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Get the value of contestants
     */ 
    public function getContestants()
    {
        return $this->contestants;
    }

    /**
     * Set the value of contestants
     *
     * @return Raffle self
     */ 
    public function setContestants($contestants)
    {
        $this->contestants = $contestants;

        return $this;
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