<?php
namespace HedgeBot\Plugins\HoraroTextFile\Console;

use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\HoraroTextFile\Entity\FileMapping;
use HedgeBot\Plugins\HoraroTextFile\HoraroTextFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowMappingsCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('horaro-text-file:show-mappings')
             ->setDescription('Shows the currently set file mappings.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var HoraroTextFile $plugin */
        $plugin = $this->getPlugin();
        $mappings = $plugin->getMappings();
        $sortedMappings = [
            HoraroTextFile::TYPE_CHANNEL => [],
            HoraroTextFile::TYPE_SCHEDULE => []
        ];

        /** @var FileMapping $mapping */
        foreach($mappings as $mapping) {
            if($mapping->getType() == HoraroTextFile::TYPE_CHANNEL) {
                $sortedMappings[HoraroTextFile::TYPE_CHANNEL][] = $mapping;
            } elseif($mapping->getType() == HoraroTextFile::TYPE_SCHEDULE) {
                $sortedMappings[HoraroTextFile::TYPE_SCHEDULE][] = $mapping;
            }
        }

        if(!empty($sortedMappings[HoraroTextFile::TYPE_CHANNEL])) {
            $output->writeln("Channels:");
            /** @var FileMapping $channelMapping */
            foreach($sortedMappings[HoraroTextFile::TYPE_CHANNEL] as $channelMapping) {
                $output->writeln("\t". $channelMapping->getId(). ": ". $channelMapping->getPath());
            }
        }

        if(!empty($sortedMappings[HoraroTextFile::TYPE_SCHEDULE])) {
            $output->writeln("Schedules:");
            /** @var FileMapping $channelMapping */
            foreach($sortedMappings[HoraroTextFile::TYPE_SCHEDULE] as $channelMapping) {
                $output->writeln("\t". $channelMapping->getId(). ": ". $channelMapping->getPath());
            }
        }
    }
}