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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use RuntimeException;

/**
 * Class EditMessageCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class EditMessageCommand extends Command
{
    use PluginAwareTrait;
    
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:message-edit')
            ->setDescription('Edit a message from Announcements plugin messages list.')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'The ID of the message to edit.'
            )
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'The message text to display.'
            )
            ->addOption(
                'channels',
                'c',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'One or multiples channels to apply this message on '
                . '(you can specify multiple channels by using the option multiple times or separating them with a comma)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();
        $messageId = $input->getArgument("id");
        $newMessage = $input->getOption("message");
        $newChannels = $input->getOption("channels");

        $editedMessage = $plugin->getMessageById($messageId);

        if(empty($editedMessage)) {
            throw new RuntimeException("Message not found.");
        }
        
        // Set values from the original message if not given as options
        if(empty($newMessage)) {
            $newMessage = $editedMessage['message'];
        }

        if(empty($newChannels)) {
            $newChannels = $editedMessage['channels'];
        } else {
            $channels = [];
            foreach($newChannels as $newChannel) {
                $channels = array_merge($channels, explode(',', $newChannel));
            }

            $newChannels = array_unique($channels);
        }

        $edited = $plugin->editMessage($messageId, $newMessage, $newChannels);

        if(!$edited) {
            throw new RuntimeException("Message edition failed.");
        }
    }
}