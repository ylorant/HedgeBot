<?php

namespace HedgeBot\Plugins\Timer\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Timer\Entity\RaceTimer;
use HedgeBot\Plugins\Timer\Timer;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;

/**
 * Class NewTimerCommand
 * @package HedgeBot\Plugins\Timer\Console
 */
class AddPlayerCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('timer:player-add')
            ->setDescription('Adds a player to a given timer')
            ->addArgument('timer', InputArgument::REQUIRED, 'The timer to add a player to')
            ->addArgument('name', InputArgument::REQUIRED, 'The player name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('timer');
        $player = $input->getArgument('name');

        /** @var Timer $plugin */
        $plugin = $this->getPlugin();
        $timer = $plugin->getTimerById($id);

        if(empty($timer)) {
            throw new RuntimeException("This timer ID doesn't exists.");
        }

        if(!($timer instanceof RaceTimer)) {
            throw new RuntimeException("This timer isn't a race timer.");
        }

        $playerAdded = $timer->addPlayer($player);
        
        if(!$playerAdded) {
            throw new RuntimeException("This player already exists.");
        }

        $plugin->saveData();
    }
}