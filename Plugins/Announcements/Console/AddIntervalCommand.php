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

/**
 * Class AddIntervalCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class AddIntervalCommand extends StorageAwareCommand
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:add-interval')
            ->setDescription('Add an interval to Announcements plugin channels list.')
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
        $actionText = "Interval added for channel '";

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();
        /** @var SymfonyQuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $existingChannel = $plugin->getChannelByName($channelName);
        if ($existingChannel) {
            $question = new ConfirmationQuestion(
                'Interval already exists for this channel, set to '
                . $existingChannel['interval'] . ' seconds. Do you want to edit it ?',
                true
            );
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln(["'Add interval' action cancelled !"]);
                return;
            }
            $plugin->editInterval($interval, $existingChannel['id'], $channelName);
            $actionText = "Interval edited for channel '";
        } else {
            $plugin->addInterval($interval, $channelName);
        }

        $output->writeln([
            $actionText . $channelName . "'!",
            "",
            "If messages have been already added to this channel, you will see them soon."
        ]);
    }
}