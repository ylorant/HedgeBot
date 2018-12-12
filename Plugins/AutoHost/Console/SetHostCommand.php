<?php

namespace HedgeBot\Plugins\AutoHost\Console;

use HedgeBot\Plugins\AutoHost\AutoHost;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Command\Command;
use RuntimeException;

/**
 * Class SetHostCommand
 * @package HedgeBot\Plugins\AutoHost\Console
 */
class SetHostCommand extends Command
{
    use PluginAwareTrait;

    /**
     *
     */
    public function configure()
    {
        $this->setName('autohost:host-set')
            ->setDescription('Sets an time constraint on a channel to launch one hosting.')
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'One channel to apply this time'
            )
            ->addArgument(
                'time',
                InputArgument::REQUIRED,
                'The interval time (in seconds, must be more than 600) between two messages display'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $channelName = $input->getArgument('channel');
        $time = $input->getArgument('time');
        // Twitch doesn't allow less than 10 minutes between two hosting
        if ($time < 600) {
            throw new RuntimeException("Twitch doesn't allow less than 10 minutes between two hosting. Time must be greater than 600.");
        }

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();
        $plugin->setHost($channelName, (int) $time);
    }
}