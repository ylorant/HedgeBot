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
 * Class AddHostedChannelCommand
 * @package HedgeBot\Plugins\AutoHost\Console
 */
class GetHostCommand extends Command
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('autohost:host-info-get')
            ->setDescription('Display host channel info.')
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'The hosting channel'
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

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();

        $displayed = $plugin->getHost($host);

        if (!$displayed) {
            throw new RuntimeException("The given host channel doesn't exist.");
        }
        $output->writeln(json_encode($displayed, JSON_PRETTY_PRINT));
    }
}
