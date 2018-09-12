<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;

/**
 * Class EditIntervalCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class EditIntervalCommand extends StorageAwareCommand
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:edit-interval')
            ->setDescription('Edit an interval from Announcements plugin channels list.')
            ->addArgument(
                'interval',
                InputArgument::REQUIRED,
                'The interval time (in seconds) between two messages display'
            )
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'One channel to apply this interval'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {

        $interval = $input->getArgument('interval');
        $channelName = $input->getArgument('channel');

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();

        $existingChannel = $plugin->getChannelByName($channelName);

        $plugin->editInterval($interval, $existingChannel['id'], $channelName);

        $output->writeln([
            "Interval edited for channel '" . $channelName . "'!",
            "",
            "If messages have been already added to this channel, you will see them soon."
        ]);
    }
}