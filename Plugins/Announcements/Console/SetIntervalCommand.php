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
            ->setDescription('Add/Edit an interval to Announcements plugin channels list.')
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'One channel to apply this interval'
            )
            ->addArgument(
                'interval',
                InputArgument::REQUIRED,
                'The interval time (in seconds) between two messages display'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {

        $time = $input->getArgument('interval');
        $channelName = $input->getArgument('channel');
        $actionText = "Interval added for channel '";

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();
        /** @var SymfonyQuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $existingInterval = $plugin->getIntervalByChannel($channelName);
        if ($existingInterval) {
            $question = new ConfirmationQuestion(
                'Interval already exists for this channel, set to '
                . $existingInterval['time'] . ' seconds. Do you want to edit it ?',
                true
            );
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln(["'Add interval' action cancelled !"]);
                return;
            }
            $actionText = "Interval edited for channel '";
        }
        $plugin->setInterval($channelName, $time);

        $output->writeln([
            $actionText . $channelName . "'!",
            "",
            "If messages have been already added to this channel, you will see them soon."
        ]);
    }
}