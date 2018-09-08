<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AddMessageCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class AddMessageCommand extends StorageAwareCommand
{
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:add-message')
            ->setDescription('Add a message to Announcements plugin messages list.')
            ->addArgument(
                'message',
                InputArgument::REQUIRED,
                'The text to display. Bot can use Markdown so you can use it here too !'
            )
            ->addArgument(
                'channels',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'One or multiples channels to apply this message (separate multiple names with a space)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {

        $message = $input->getArgument('message');
        $channels = explode(" ", $input->getArgument('channels'));

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();

        $plugin->addMessage($message, $channels);

        $output->writeln([
            "Message added !",
            "",
            "You will see it on channel(s) soon (depends channel(s) displaying messages frequency)."
        ]);
    }
}