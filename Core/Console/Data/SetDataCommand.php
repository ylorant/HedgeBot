<?php
namespace HedgeBot\Core\Console\Data;

use HedgeBot\Core\Console\StorageAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetDataCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('data:set')
             ->setDescription('Sets a data into the data storage. Serves as debug mainly.')
             ->addArgument('path', InputArgument::REQUIRED, 'The path of the data to set.')
             ->addArgument('data', InputArgument::REQUIRED, 'The data to set, as JSON.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $value = json_decode($input->getArgument('data'));
        $this->getDataStorage()->set($path, $value);
    }
}