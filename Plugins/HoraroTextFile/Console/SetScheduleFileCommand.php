<?php
namespace HedgeBot\Plugins\HoraroTextFile\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Exception\RuntimeException;
use HedgeBot\Core\API\Plugin;

class SetScheduleFileCommand extends StorageAwareCommand
{
    use PluginAwareTrait;

    public function configure()
    {
        $this->setName('horaro-text-file:set-schedule-file')
             ->setDescription('Set a file where the current schedule info will be output automagically.')
             ->addArgument('schedule', InputArgument::REQUIRED, 'The schedule ident slug.')
             ->addArgument('path', InputArgument::REQUIRED, 'The path to the to-be-generated file.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $identSlug = $input->getArgument('schedule');
        $path = $input->getArgument('path');

        /** @var HoraroTextFile $plugin */
        $plugin = $this->getPlugin();
        /** @var Horaro $horaroPlugin */
        $horaroPlugin = Plugin::getManager()->getPlugin('Horaro');
        /** @var Schedule $schedule */
        $schedule = $horaroPlugin->getScheduleByIdentSlug($identSlug);

        if(empty($schedule))
            throw new RuntimeException("Cannot find schedule ident slug.");
        
        $plugin->setScheduleFile($identSlug, $path);
        $plugin->saveData();
    }
}