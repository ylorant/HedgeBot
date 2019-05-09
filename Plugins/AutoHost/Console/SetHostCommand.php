<?php
namespace HedgeBot\Plugins\AutoHost\Console;

use HedgeBot\Plugins\AutoHost\AutoHost;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Class SetHostCommand
 * @package HedgeBot\Plugins\AutoHost\Console
 */
class SetHostCommand extends Command
{
    use PluginAwareTrait;

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setName('autohost:host-set')
            ->setDescription('Sets an time constraint on a channel to send host requests.')
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'One channel to apply this time'
            )
            ->addArgument(
                'time',
                InputArgument::REQUIRED,
                'The interval time (in seconds, must be more than 600) between two host requests'
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
        $time = $input->getArgument('time');
        // Twitch doesn't allow more than 3 hosts per 30 minutes
        if ($time < 600) {
            throw new RuntimeException("Twitch doesn't allow less than 30 minutes between 2 hosts. Time must be greater than 600 seconds.");
        }

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();
        $hostSet = $plugin->setHost($channelName, (int) $time);

        if (!$hostSet) {
            throw new RuntimeException("There has been an error while setting the host channel parameters.");
        }
    }
}
