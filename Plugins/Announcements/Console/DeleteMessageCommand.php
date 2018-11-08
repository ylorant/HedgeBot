<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Command\Command;
use RuntimeException;

/**
 * Class DeleteMessageCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class DeleteMessageCommand extends Command
{
    use PluginAwareTrait;

    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:message-delete')
            ->setDescription('Delete a message from Announcements plugin messages list.')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'The ID of the message to delete.'
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
        $id = $input->getArgument('id');

        $deleted = $plugin->deleteMessage($id);

        if(!$deleted) {
            throw new RuntimeException("Message deletion failed. Check that the ID you're trying to delete exists.");
        }
    }
}