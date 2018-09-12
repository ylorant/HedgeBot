<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class EditMessageCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class EditMessageCommand extends StorageAwareCommand
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:edit-message')
            ->setDescription('Edit a message from Announcements plugin messages list.')
            ->addArgument(
                'channel',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Channel to display all messages you can edit'
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
        $messages = $plugin->getMessagesByChannel($channelName);

        /** @var SymfonyQuestionHelper $questionHelper */
        $helper = $this->getHelper('question');

        $choiceQuestion = new ChoiceQuestion(
            'Which message do you want to edit (type number associated with) ?',
            $messages,
            null
        );
        $choiceQuestion->setErrorMessage('Message n° %s is invalid.');
        $messageId = $helper->ask($input, $output, $choiceQuestion);

        $messageQuestion = new Question('Please type the edited message :', '');
        $messageQuestion->setAutocompleterValues($messages[$messageId]);

        $newMessage = $helper->ask($input, $output, $messageQuestion);

        $plugin->editMessage($messageId, $newMessage, $channelName);

        $output->writeln([
            "Message edited !",
            "",
            "You will see it on channel(s) soon (depends channel(s) displaying messages frequency)."
        ]);
    }
}