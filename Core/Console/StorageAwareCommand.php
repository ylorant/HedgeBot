<?php

namespace HedgeBot\Core\Console;

use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Data\Provider;

/**
 * Class StorageAwareCommand
 * TODO: Replace this class by using the API
 *
 * @package HedgeBot\Core\Console
 */
abstract class StorageAwareCommand extends Command implements StorageAwareInterface
{
    protected $data;
    protected $config;

    /**
     * @return mixed
     */
    public function getConfigStorage()
    {
        return $this->config;
    }

    /**
     * @param Provider $storage
     */
    public function setConfigStorage(Provider $storage)
    {
        $this->config = $storage;
    }

    /**
     * @return mixed
     */
    public function getDataStorage()
    {
        return $this->data;
    }

    /**
     * @param Provider $storage
     */
    public function setDataStorage(Provider $storage)
    {
        $this->data = $storage;
    }
}
