<?php

namespace HedgeBot\Plugins\Timer\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Timer\Timer;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class NewTimerCommand
 * @package HedgeBot\Plugins\Timer\Console
 */
class NewTimerCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('timer:new')
            ->setDescription('Creates a new timer.')
            ->addArgument('id', InputArgument::REQUIRED, 'The timer ID.')
            ->addArgument('title', InputArgument::OPTIONAL, 'The timer title (optional)')
            ->addOption('race', 'r', InputOption::VALUE_NONE, 'Set the timer as a race timer');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $title = $input->getArgument('title');
        $race = $input->getOption('race');
        $plugin = $this->getPlugin();

        if(!empty($plugin->getTimerById($id))) {
            throw new RuntimeException("This timer ID already exists.");
        }

        /** @var Timer $plugin */
        $plugin->createTimer($id, $title, $race);
    }
}