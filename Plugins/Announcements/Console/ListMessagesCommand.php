<?php
namespace HedgeBot\Plugins\Announcements\Console;

use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Plugins\Announcements\Announcements;

/**
 * List message command, lists the messages in the bot.
 */
class ListMessagesCommand extends Command
{
    use PluginAwareTrait;
    
    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:message-list')
            ->setDescription('Lists the currently set messages.')
            ->addOption(
                'channel',
                'c',
                InputOption::VALUE_REQUIRED,
                'Filters the channel from where the messages are listed.'
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
        $channelName = $input->getOption('channel');
        $messages = [];

        // Get the message list from the plugin depending on the channel or not
        if(!empty($channelName)) {
            $messages = $plugin->getMessagesByChannel($channelName);
        } else {
            $messages = $plugin->getMessages();
        }

        foreach($messages as $message) {
            $output->writeln([
                $message['id'] . ":",
                "\tID: " . $message['id'],
                "\tMessage: " . $message['message'],
                "\tChannels: " . join(', ', $message['channels']),
                ""
            ]);
        }
    }
}