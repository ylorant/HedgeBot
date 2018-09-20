<?php
namespace HedgeBot\Core\Console\Data;

use HedgeBot\Core\Console\StorageAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * GetDataCommand class.
 * Allows to get data from the storage and output it to the standard output. Serves as a debug tool mainly.
 */
class GetDataCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('data:get')
             ->setDescription('Gets a data from the data storage and outputs it to the console. Serves as debug mainly.')
             ->addArgument('path', InputArgument::REQUIRED, 'The path of the data to get.');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $value = $this->getDataStorage()->get($path);
        $output->writeln(json_encode($value, JSON_PRETTY_PRINT));
    }
}