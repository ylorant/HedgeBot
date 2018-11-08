<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class setIntervalCommand
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
        $channelName = $input->getArgument('channel');
        $actionText = "Interval added for channel '";

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();
        $plugin->setInterval($channelName, (int) $time, (int) $messages);
    }
}