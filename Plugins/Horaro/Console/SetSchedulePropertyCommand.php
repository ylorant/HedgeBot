<?php
namespace HedgeBot\Plugins\Horaro\Console;

use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Horaro\Horaro;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Exception\RuntimeException;
use HedgeBot\Plugins\Horaro\Entity\Schedule;

class SetSchedulePropertyCommand extends StorageAwareCommand
{
    use PluginAwareTrait;

    public function configure()
    {
        $this->setName('horaro:set-schedule-property')
             ->setDescription('Sets a schedule property. Available properties: '. join(', ', Schedule::EXPORTED_KEYS))
             ->addArgument('identSlug', InputArgument::REQUIRED, 'The schedule ident slug given by the load-schedule command.')
             ->addArgument('property', InputArgument::REQUIRED, 'The property to set the value of.')
             ->addArgument('value', InputArgument::REQUIRED, 'The new property value. JSON can be used for arrays.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $identSlug = $input->getArgument('identSlug');
        $property = $input->getArgument('property');
        $value = $input->getArgument('value');

        // Try to parse the value as JSON, in case it works
        $jsonValue = json_decode($value);
        if($jsonValue !== null) {
            $value = $jsonValue;
        }

        /** @var Horaro $plugin */
        $plugin = $this->getPlugin();

        if(!$plugin->hasScheduleIdentSlug($identSlug))
            throw new RuntimeException("Schedule ident slug not found.");
        
        $schedule = $plugin->getScheduleByIdentSlug($identSlug);

        // Check if the setter exists for the property
        $setterName = 'set'. ucfirst($property);
        if(!method_exists($schedule, $setterName)) {
            throw new RuntimeException("Schedule property isn't modifiable.");
        }

        $schedule->$setterName($value);

        $plugin->saveData();
    }
}