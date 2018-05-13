<?php
namespace HedgeBot\Plugins\Horaro\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Exception\RuntimeException;

class UnloadScheduleCommand extends StorageAwareCommand
{
    use PluginAwareTrait;

    public function configure()
    {
        $this->setName('horaro:unload-schedule')
             ->setDescription('Unloads a schedule into the bot.')
             ->addArgument('identSlug', InputArgument::REQUIRED, 'The schedule ident slug.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $identSlug = $input->getArgument('identSlug');
        /** @var Horaro $plugin */
        $plugin = $this->getPlugin();

        if (!$plugin->unloadSchedule($identSlug)) {
            throw new RuntimeException("Schedule ident slug not found.");
        }

        $plugin->saveData();
    }
}
