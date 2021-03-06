<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class SetIntervalCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class SetIntervalCommand extends Command
{
    use PluginAwareTrait;

    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:interval-set')
            ->setDescription('Sets an time/message constraint on a channel to send messages on.')
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'One channel to apply this interval'
            )
            ->addOption(
                'time',
                't',
                InputOption::VALUE_REQUIRED,
                'The interval time (in seconds) between two messages display'
            )
            ->addOption(
                'messages',
                'm',
                InputOption::VALUE_REQUIRED,
                'The messages count between two messages display'
            )
            ->addOption(
                'enabled',
                'e',
                InputOption::VALUE_REQUIRED,
                'Toggle to enable messsage display on the channel. Be sure to include it if you want to enable messages.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {

        $time = $input->getOption('time') ?? 0;
        $messages = $input->getOption('messages') ?? 0;
        $enabled = $input->getArgument('enabled') ?? true;
        $channelName = $input->getArgument('channel');

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();
        $plugin->setInterval($channelName, (int) $time, (int) $messages, (bool) $enabled);
    }
}