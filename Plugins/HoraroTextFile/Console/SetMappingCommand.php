<?php

namespace HedgeBot\Plugins\HoraroTextFile\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Exception\RuntimeException;
use HedgeBot\Core\API\Plugin;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Plugins\HoraroTextFile\HoraroTextFile;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class SetMappingCommand
 * @package HedgeBot\Plugins\HoraroTextFile\Console
 */
class SetMappingCommand extends Command
{
    use PluginAwareTrait;
    
    /**
     * Configures the command.
     */
    public function configure()
    {
        $allowedTypes = [
            HoraroTextFile::TYPE_CHANNEL,
            HoraroTextFile::TYPE_SCHEDULE
        ];

        $this->setName('horaro-text-file:set-mapping')
            ->setDescription('Set a file where the designated schedule info will be output automagically.')
            ->addArgument('type', InputArgument::REQUIRED, 'The type of data the file should respond to. Can be of: '. join(', ', $allowedTypes))
            ->addArgument('identifier', InputArgument::REQUIRED, 'The schedule ident slug or the channel name.')
            ->addArgument('path', InputArgument::REQUIRED, 'The path to the to-be-generated file.')
            ->addOption('delay-next', 'd', InputOption::VALUE_REQUIRED, 'The delay when updating to update the "next item" blocks.');
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
        $path = $input->getArgument('path');

        /** @var HoraroTextFile $plugin */
        $plugin = $this->getPlugin();
        /** @var Horaro $horaroPlugin */
        $horaroPlugin = Plugin::getManager()->getPlugin('Horaro');
        /** @var Schedule $schedule */

        if($type == HoraroTextFile::TYPE_SCHEDULE) {
            $schedule = $horaroPlugin->getScheduleByIdentSlug($identifier);
    
            if (empty($schedule)) {
                throw new RuntimeException("Cannot find schedule ident slug.");
            }
        }

        $plugin->saveMapping($type, $identifier, $path);
        $plugin->saveData();
    }
}
