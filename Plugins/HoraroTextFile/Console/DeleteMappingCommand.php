<?php

namespace HedgeBot\Plugins\HoraroTextFile\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Plugins\HoraroTextFile\HoraroTextFile;

/**
 * Class SetMappingCommand
 * @package HedgeBot\Plugins\HoraroTextFile\Console
 */
class DeleteMappingCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('horaro-text-file:delete-mapping')
            ->setDescription('Deletes a previously set mapping.')
            ->addArgument('type', InputArgument::REQUIRED, 'The type of the mapping to delete.')
            ->addArgument('identifier', InputArgument::REQUIRED, 'The identifier of the mapping to delete.');
    }

    /**
     * Executes the command.
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        $identifier = $input->getArgument('identifier');

        /** @var HoraroTextFile $plugin */
        $plugin = $this->getPlugin();
        $deleted = $plugin->deleteMapping($type, $identifier);

        if(!$deleted) {
            throw new RuntimeException("Mapping does not exist.");
        }
        
        $plugin->saveData();
    }
}