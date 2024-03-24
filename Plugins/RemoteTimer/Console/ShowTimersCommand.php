<?php

namespace HedgeBot\Plugins\RemoteTimer\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\RemoteTimer\RemoteTimer;
use HedgeBot\Plugins\Timer\Entity\RaceTimer;
use HedgeBot\Plugins\RemoteTimer\Entity\RemoteTimer as RemoteTimerEntity;
use Symfony\Component\Console\Command\Command;

/**
 * Class NShowTimersCommand
 * @package HedgeBot\Plugins\RemoteTimer\Console
 */
class ShowTimersCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('remote-timer:show')
            ->setDescription('Shows the available remote timers.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var RemoteTimer $plugin */
        $plugin = $this->getPlugin();
        $timers = $plugin->getTimers();

        /** @var RemoteTimerEntity $timer */
        foreach($timers as $timer) {
            $status = "stopped";

            if($timer->isStarted()) {
                $status = "started";
            }

            if($timer->isPaused()) {
                $status = "paused";
            }

            $output->writeln($timer->getKey());
            $output->writeln("\tName: ". $timer->getName());
            $output->writeln("\tKey: ". $timer->getKey());
            $output->writeln("\tStatus: ". $status);
            $output->writeln("\tTime: ". $plugin->formatTimerTime($timer));

            $output->writeln("");
        }
    }
}