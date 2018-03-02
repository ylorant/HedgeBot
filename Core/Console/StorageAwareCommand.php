<?php
namespace HedgeBot\Core\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use HedgeBot\Core\HedgeBot;
use RuntimeException;
use HedgeBot\Core\Data\Provider;

/**
 * TODO: Replace this class by using the API
 */
abstract class StorageAwareCommand extends Command implements StorageAwareInterface
{
    protected $data;
    protected $config;

    public function getConfigStorage()
    {
        return $this->config;
    }

    public function setConfigStorage(Provider $storage)
    {
        $this->config = $storage;
    }

    public function getDataStorage()
    {
        return $this->data;
    }

    public function setDataStorage(Provider $storage)
    {
        $this->data = $storage;
    }
}
