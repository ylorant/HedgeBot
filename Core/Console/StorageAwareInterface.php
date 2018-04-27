<?php

namespace HedgeBot\Core\Console;

use HedgeBot\Core\Data\Provider;

/**
 * Interface StorageAwareInterface
 * @package HedgeBot\Core\Console
 */
interface StorageAwareInterface
{
    /**
     * @return mixed
     */
    public function getConfigStorage();

    /**
     * @param Provider $storage
     * @return mixed
     */
    public function setConfigStorage(Provider $storage);

    /**
     * @return mixed
     */
    public function getDataStorage();

    /**
     * @param Provider $storage
     * @return mixed
     */
    public function setDataStorage(Provider $storage);
}