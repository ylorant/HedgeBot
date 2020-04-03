<?php

namespace HedgeBot\Plugins\Timer\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Timer\Entity\Timer as TimerEntity;
use HedgeBot\Plugins\Timer\Timer;
use Symfony\Component\Console\Command\Command;

/**
 * Class NewTimerCommand
 * @package HedgeBot\Plugins\Timer\Console
 */
class ShowTimersCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('timer:show')
            ->setDescription('Shows the available timers.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Timer $plugin */
        $plugin = $this->getPlugin();
        $timers = $plugin->getTimers();

        /** @var TimerEntity $timer */
        foreach($timers as $timer) {
            $status = "stopped";

            if($timer->isStarted()) {
                $status = "started";

                if($timer->isPaused()) {
                    $status = "paused";
                }
            }

            $output->writeln($timer->getId());
            $output->writeln("\tTitle: ". $timer->getTitle());
            $output->writeln("\tStatus: ". $status);
            $output->writeln("\tTime: ". $plugin->formatTimerTime($timer));

            $output->writeln("");
        }
    }
}