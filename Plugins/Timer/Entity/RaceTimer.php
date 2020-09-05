<?php
namespace HedgeBot\Plugins\Timer\Entity;

/**
 * RaceTimer entity class. Represents a race time, i.e. a compound timer where each player has their own
 * timer, and all are synchronized for the start, but each one stops separately.
 * 
 * Some timers can be countdowns, i.e. having a set amount of time to count down to 0.
 */
class RaceTimer extends Timer
{
    /** @var array Players data. Each player has its own stop time. */
    protected $players;
    
    const TYPE = "race-timer";

    /**
     * Constructor.
     * 
     * @return RaceTimer 
     */
    public function __construct()
    {
        $this->players = [];
    }

    /**
     * Get the value of players
     * 
     * @return array
     */ 
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * Set the value of players
     *
     * @return  self
     */ 
    public function setPlayers($players)
    {
        $this->players = $players;

        return $this;
    }

    /**
     * Gets a player's info by its name.
     * 
     * @param string $name The player's name.
     * @return array|null The player's data as an array if it exists, null if not. 
     */
    public function getPlayer($name)
    {
        if(!isset($this->players[$name])) {
            return null;
        }

        return $this->players[$name];
    }

    /**
     * Checks if the timer has a specific player name.
     * 
     * @param string $name The player name to check the existence of.
     * @return bool True if the player exists, false if not.
     */
    public function hasPlayer($name)
    {
        return isset($this->players[$name]);
    }

    /**
     * Adds a player to the players list.
     * 
     * @param string $name The player name to add.
     * @return bool True if the player has been added, false if not.
     */
    public function addPlayer($name)
    {
        if(isset($this->players[$name])) {
            return false;
        }

        $this->players[$name] = [
            'player' => $name,
            'elapsed' => null
        ];

        return true;
    }

    /**
     * Removes a player from the players list.
     * 
     * @param mixed $name The name of the player to remove
     * @return bool True if the player has been removed, false if not.
     */
    public function removePlayer($name)
    {
        if(!isset($this->players[$name])) {
            return false;
        }

        unset($this->players[$name]);
        return true;
    }

    /**
     * Splits the timer for a particular player.
     * 
     * @param string $name The player's name.
     * @return self|bool The itmer if the player's timer has been split, false if not. 
     */
    public function stopPlayer($name)
    {
        if(!isset($this->players[$name])) {
            return false;   
        }
        
        $this->players[$name]['elapsed'] = $this->getElapsedTime();

        return $this;
    }

    /**
     * Resets the player's elapsed timer.
     * 
     * @param string $name The player's name.
     * @return self|bool The itmer if the player's timer has been split, false if not. 
     */
    public function resetPlayer($name)
    {
        if(!isset($this->players[$name])) {
            return false;   
        }

        $this->players[$name]['elapsed'] = null;

        return $this;
    }
}