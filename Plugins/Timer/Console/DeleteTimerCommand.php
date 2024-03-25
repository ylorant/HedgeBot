<?php

namespace HedgeBot\Plugins\Timer\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Timer\Timer;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;

/**
 * Class DeleteTimerCommand
 * @package HedgeBot\Plugins\Timer\Console
 */
class DeleteTimerCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('timer:delete')
            ->setDescription('Deletes an existing timer.')
            ->addArgument('id', InputArgument::REQUIRED, 'The timer ID.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $plugin = $this->getPlugin();

        /** @var Timer $plugin */
        $deleted = $plugin->deleteTimer($id);

        if (!$deleted) {
            throw new RuntimeException("Cannot delete timer (check that the ID exists).");
        }
    }
}