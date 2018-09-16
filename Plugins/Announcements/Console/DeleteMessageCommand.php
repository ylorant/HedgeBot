<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class DeleteMessageCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class DeleteMessageCommand extends StorageAwareCommand
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:delete-message')
            ->setDescription('Delete a message from Announcements plugin messages list.')
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'Channel to display all messages you can delete'
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

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();

        $plugin = $this->getPlugin();
        $messages = $plugin->getMessagesByChannel($channelName);
        $messagesAnswer = [];

        $output->writeln([
            "You can delete those messages :",
            ""
        ]);
        foreach ($messages as $message) {
            $messagesAnswer[] = $message['id'];
            $output->writeln([$message['id'] . " => " . $message['message']]);
        }
        $output->writeln([
            ""
        ]);

        /** @var SymfonyQuestionHelper $questionHelper */
        $helper = $this->getHelper('question');

        $choiceQuestion = new ChoiceQuestion(
            'Which message do you want to delete (type number associated with) ?',
            $messagesAnswer,
            null
        );
        $choiceQuestion->setErrorMessage('Message n° %s is invalid.');
        $messageId = $helper->ask($input, $output, $choiceQuestion);

        $plugin->deleteMessage($messageId);

        $output->writeln([
            "Message deleted !",
        ]);
    }
}