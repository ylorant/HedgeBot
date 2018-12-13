<?php

namespace HedgeBot\Plugins\AutoHost\Console;

use HedgeBot\Plugins\AutoHost\AutoHost;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Command\Command;

/**
 * Class AddHostedChannelCommand
 * @package HedgeBot\Plugins\AutoHost\Console
 */
class AddHostedChannelCommand extends Command
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('autohost:hosted-channel-add')
            ->setDescription('Add one channel to host on a hosting channel.')
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'The hosting channel'
            )
            ->addArgument(
                'channel',
                 InputArgument::REQUIRED,
                'One channel to host'
            )->addArgument(
                'priority',
                InputArgument::REQUIRED,
                'Priority number for hosting choice'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {

        $host = $input->getArgument('host');
        $channel = $input->getArgument('channel');
        $priority = $input->getArgument('priority');

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();

        $plugin->addHostedChannel($host, $channel, $priority);
    }
}