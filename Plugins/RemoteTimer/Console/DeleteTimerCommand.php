<?php

namespace HedgeBot\Plugins\RemoteTimer\Console;

use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\RemoteTimer\RemoteTimer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DeleteTimerCommand
 * @package HedgeBot\Plugins\RemoteTimer\Console
 */
class DeleteTimerCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('remote-timer:delete')
        ->setDescription('Deletes a remote timer.')
        ->addArgument('key', InputArgument::REQUIRED, 'The timer key');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getArgument('key');

        /** @var RemoteTimer $plugin */
        $plugin = $this->getPlugin();
        $deleted = $plugin->deleteTimer($key);

        if (!$deleted) {
            throw new RuntimeException("Error while deleting timer (check that key exists).");
        }
    }
}