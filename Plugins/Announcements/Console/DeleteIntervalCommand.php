<?php
namespace HedgeBot\Plugins\Announcements\Console;

use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Announcements\Announcements;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use RuntimeException;

/**
 * Class SetIntervalCommand
 * @package HedgeBot\Plugins\Announcements\Console
 */
class DeleteIntervalCommand extends Command
{
    use PluginAwareTrait;

    /**
     *
     */
    public function configure()
    {
        $this->setName('announcements:interval-delete')
            ->setDescription('Deletes a message/time constraint set on a channel, effectively disabling message sending on the channel.')
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'One channel to delete the interval of'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $channelName = strtolower($input->getArgument('channel'));

        /** @var Announcements $plugin */
        $plugin = $this->getPlugin();
        $deleted = $plugin->removeInterval($channelName);

        if(!$deleted) {
            throw new RuntimeException("Cannot delete interval (it may not exist).");
        }
    }
}