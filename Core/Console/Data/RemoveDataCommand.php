<?php
namespace HedgeBot\Core\Console\Data;

use HedgeBot\Core\Console\StorageAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class RemoveDataCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('data:remove')
             ->setDescription('Removes a data from the data storage. Serves as debug mainly.')
             ->addArgument('path', InputArgument::REQUIRED, 'The path of the data to remove.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $this->getDataStorage()->remove($path);
    }
}