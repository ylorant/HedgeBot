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
 * Class RemoveHostedChannelCommand
 * @package HedgeBot\Plugins\AutoHost\Console
 */
class RemoveHostedChannelCommand extends Command
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('autohost:hosted-channel-remove')
            ->setDescription('Remove one hosted channel from a hosting channel.')
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'The hosting channel'
            )
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'Hosted channel to remove'
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

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();

        $removed = $plugin->removeHostedChannel($host, $channel);

        if (!$removed) {
            throw new RuntimeException("The given host channel hasn't been configured or the hosted channel wasn't found.");
        }
    }
}