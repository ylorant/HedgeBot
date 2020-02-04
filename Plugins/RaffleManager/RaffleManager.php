<?php
namespace HedgeBot\Plugins\RaffleManager;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Plugins\RaffleManager\Entity\RaffleModel;

class RaffleManager extends PluginBase
{
    /** @var array The list of currently loaded raffle models */
    protected $raffleModels;
    /** @var array The list of currently active raffles */
    protected $raffles;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->loadData();
    }

    /**
     * Loads the plugin's data from the storage.
     */
    public function loadData()
    {
        HedgeBot::message("Loading raffles and models...", [], E_DEBUG);

        $this->raffleModels = [];
        $this->raffles = [];

        $raffleModels = $this->data->raffleModels->toArray();
        $raffles = $this->data->raffles->toArray();
        
        foreach($raffleModels as $raffleModel) {
            $this->raffleModels[] = RaffleModel::fromArray($raffleModel);
        }

        foreach($raffles as $raffle) {
            $this->raffles[] = Raffle::fromArray($raffle);
        }
    }
    
    /**
     * Saves the plugin's data into storage.
     */
    public function saveData()
    {
        HedgeBot::message("Saving raffles and models...", [], E_DEBUG);

        
    }
}