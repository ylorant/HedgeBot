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
class RemoveFilterListCommand extends Command
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('autohost:filter-list-remove')
            ->setDescription('Remove a word into a defined filter list for one host channel.')
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'The hosting channel'
            )
            ->addArgument(
                'typeList',
                InputArgument::REQUIRED,
                'Type of filter list to modify. 1 for blacklist, 2 for whitelist.'
            )->addArgument(
                'word',
                InputArgument::REQUIRED,
                'Word to remove into the chosen filter list'
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
        $typeList = $input->getArgument('typeList');
        $word = $input->getArgument('word');

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();

        $removed = $plugin->removeFilterList($host, $typeList, $word);

        if (!$removed) {
            throw new RuntimeException("The chosen filter list hasn't been modified.");
        }
    }
}