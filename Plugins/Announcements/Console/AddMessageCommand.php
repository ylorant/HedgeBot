<?php

namespace HedgeBot\Plugins\Announcements\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Command\Command;

/**
 * Class AddMessageCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class AddMessageCommand extends Command
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:message-add')
            ->setDescription('Add a message to Announcements plugin messages list.')
            ->addArgument(
                'message',
                InputArgument::REQUIRED,
                'The message text to display.'
            )
            ->addArgument(
                'channels',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'One or multiples channels to apply this message on (separate multiple names with a space)'
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
        $channels = $input->getArgument('channels');

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();

        $plugin->addMessage($message, $channels);
    }
}