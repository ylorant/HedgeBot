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
 * Class RemoveFilterWordCommand
 * @package HedgeBot\Plugins\AutoHost\Console
 */
class RemoveFilterWordCommand extends Command
{
    use PluginAwareTrait;
    /**
     *
     */
    public function configure()
    {
        $this->setName('autohost:filter-word-remove')
            ->setDescription('Removes a word from one of the filter lists for one host channel.')
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'The hosting channel'
            )
            ->addArgument(
                'listName',
                InputArgument::REQUIRED,
                'Name of the filter list to remove the word from. Can be: ' . join(', ', AutoHost::FILTER_TYPES). '.'
            )->addArgument(
                'word',
                InputArgument::REQUIRED,
                'Word to remove into the chosen filter list.'
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
        $listName = $input->getArgument('listName');
        $word = $input->getArgument('word');
        
        if(!in_array($listName, AutoHost::FILTER_TYPES)) {
            throw new RuntimeException("Wrong filter list name supplied.");
        }

        /** @var AutoHost $plugin */
        $plugin = $this->getPlugin();
        $removed = $plugin->removeFilterWord($host, $listName, $word);

        if (!$removed) {
            throw new RuntimeException("The remove operation failed.");
        }
    }
}