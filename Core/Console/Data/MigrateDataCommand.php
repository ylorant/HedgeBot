<?php
namespace HedgeBot\Core\Console\Data;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Data\Provider;
use Symfony\Component\Console\Question\ChoiceQuestion;
use stdClass;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\StorageAwareTrait;

/**
 * MigrateDataCommand class
 * Allows to migrate the bot data between two data providers.
 * 
 * @package HedgeBot\Core\Console\Data
 */
class MigrateDataCommand extends Command
{
    use StorageAwareTrait;

    protected function configure()
    {
        $this->setName('data:migrate')
             ->setDescription('Migrates the bot\'s data between two different storages. It\'s an interactive command.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceStorage = null;
        $targetStorage = null;

        $output->writeln(['-- Storage migration tool --', '']);

        // Configure the source storage
        $output->writeln('Source storage configuration:');
        $sourceStorage = $this->askForStorage($input, $output);

        $output->writeln(['', 'Target storage configuration:']);

        // Ask the user if we use the loaded storage for the target
        $targetStorage = $this->askForStorage($input, $output, $sourceStorage == $this->getDataStorage());

        $output->writeln("Clearing target storage...");
        $targetStorage->remove();

        $output->writeln("Copying data...");
        $sourceData = $sourceStorage->get();
        
        foreach($sourceData as $key => $value) {
            $targetStorage->set($key, $value);
        }
    }

    protected function askForStorage(InputInterface $input, OutputInterface $output, $forceCustomStorage = false)
    {
        $helper = $this->getHelper('question');

        if($forceCustomStorage) {
            return $this->getCustomStorage($input, $output);
        }

        // Ask the user if we use the loaded storage for the source
        $useConfigQuestion = new ConfirmationQuestion("Use the bot configuration (data storage) for this storage [y] ? ");
        $useConfig = $helper->ask($input, $output, $useConfigQuestion);

        if($useConfig) {
            $storage = $this->getDataStorage();
        } else {
            $storage = $this->getCustomStorage($input, $output);
        }

        return $storage;
    }

    protected function getCustomStorage(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $storages = Provider::getStorageList();

        // Asking the storage
        $storageTypeQuestion = new ChoiceQuestion("What type of storage is it ?", $storages);
        $storageType = $helper->ask($input, $output, $storageTypeQuestion);
        
        // Getting the storage parameters
        $storageClass = Provider::resolveStorage($storageType);
        $availableParameters = $storageClass::getParameters();

        array_unshift($availableParameters, "");

        // Ask the user to enter the storage parameters, repeatedly, until they have finished
        $storageSettingKeyQuestion = new ChoiceQuestion("Select the setting you want to set (0 to end): ", $availableParameters, "");
        $storageSettingValueQuestion = new Question("Enter the parameter value: ");

        $parameters = new stdClass();
        $parameterKey = null;
        $parameterValue = null;

        do {
            $parameterKey = null;
            $parameterValue = null;

            $output->writeln("");

            $parameterKey = $helper->ask($input, $output, $storageSettingKeyQuestion);
            
            if($parameterKey) {
                $parameterValue = $helper->ask($input, $output, $storageSettingValueQuestion);
                $parameters->$parameterKey = $parameterValue;
            }

        } while(!empty($parameterKey));

        $storage = new $storageClass();
        $storage->connect($parameters);

        return $storage;
    }
}