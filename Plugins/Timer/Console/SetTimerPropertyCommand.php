<?php

namespace HedgeBot\Plugins\Timer\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\Timer\Entity\Timer;
use ReflectionClass;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;

/**
 * Class NewTimerCommand
 * @package HedgeBot\Plugins\Timer\Console
 */
class SetTimerPropertyCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $properties = join(', ', $this->getEntityProperties());

        $this->setName('timer:set-property')
            ->setDescription('Creates a new timer.')
            ->addArgument('id', InputArgument::REQUIRED, 'The timer ID.')
            ->addArgument('property', InputArgument::REQUIRED, 'The property to set. Available properties: '. $properties)
            ->addArgument('value', InputArgument::REQUIRED, 'The new value.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $property = $input->getArgument('property');
        $value = $input->getArgument('value');

        $plugin = $this->getPlugin();
        $timer = $plugin->getTimerById($id);

        if(empty($timer)) {
            throw new RuntimeException("This timer ID already exists.");
        }

        if(!in_array($property, $this->getEntityProperties())) {
            throw new RuntimeException("Unknown property name.");
        }
        
        // Type conversions
        if(in_array($value, ['true', 'false'])) {
            $value = $value == 'true';
        }

        if(is_numeric($value)) {
            $value = intval($value);
        }

        $setter = 'set'. ucfirst($property);
        $timer->$setter($value);
        $plugin->saveData();
    }

    /**
     * Gets the entity property names via reflection.
     * @return array The en
     */
    protected function getEntityProperties()
    {
        $reflectionClass = new ReflectionClass(Timer::class);
        $reflectionProperties = $reflectionClass->getProperties();
        $properties = [];

        foreach($reflectionProperties as $reflectionProperty) {
            $properties[] = $reflectionProperty->getName();
        }

        return $properties;
    }
}