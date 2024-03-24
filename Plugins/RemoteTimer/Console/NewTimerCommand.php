<?php

namespace HedgeBot\Plugins\RemoteTimer\Console;

use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\RemoteTimer\RemoteTimer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NewTimerCommand
 * @package HedgeBot\Plugins\RemoteTimer\Console
 */
class NewTimerCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('remote-timer:new')
        ->setDescription('Creates a new remote timer.')
        ->addArgument('name', InputArgument::REQUIRED, 'The timer name');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        /** @var RemoteTimer $plugin */
        $plugin = $this->getPlugin();
        $timer = $plugin->createTimer($name);
        
        $output->writeln("New timer key: " . $timer->getKey());
    }
}