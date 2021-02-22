<?php

namespace HedgeBot\Core\Console;

use HedgeBot\Core\Data\Provider;

/**
 * Class StorageAwareTrait
 * TODO: Replace this trait by using the API
 *
 * @package HedgeBot\Core\Console
 */
trait StorageAwareTrait
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
