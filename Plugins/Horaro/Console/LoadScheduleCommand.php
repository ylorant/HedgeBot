<?php

namespace HedgeBot\Plugins\Horaro\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Class LoadScheduleCommand
 * @package HedgeBot\Plugins\Horaro\Console
 */
class LoadScheduleCommand extends StorageAwareCommand
{
    use PluginAwareTrait;

    /**
     *
     */
    public function configure()
    {
        $this->setName('horaro:load-schedule')
            ->setDescription('Loads a schedule into the bot.')
            ->addOption('event', 'e', InputOption::VALUE_REQUIRED)
            ->addOption('channel', 'C', InputOption::VALUE_REQUIRED)
            ->addArgument('schedule', InputArgument::REQUIRED, 'The schedule slug or URL.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $eventId = $input->getOption('event');
        $channel = $input->getOption('channel');
        $scheduleId = $input->getArgument('schedule');
        /** @var Horaro $plugin */
        $plugin = $this->getPlugin();

        $scheduleExists = $plugin->scheduleExists($scheduleId, $eventId);
        if (!$scheduleExists) {
            throw new RuntimeException("Cannot load schedule, it has not been found on Horaro.");
        }

        $scheduleIdentSlug = $plugin->loadSchedule($scheduleId, $eventId);

        if (!$scheduleIdentSlug) {
            throw new RuntimeException("Cannot load schedule, it has been already loaded.");
        }

        if (!empty($channel)) {
            /** @var Schedule $schedule */
            $schedule = $plugin->getScheduleByIdentSlug($scheduleIdentSlug);
            $schedule->setChannel($channel);
        }

        $output->writeln([
            "Schedule ident slug: " . $scheduleIdentSlug,
            "",
            "Use this ident slug to reference this schedule in all future console calls that need it."
        ]);
    }
}
