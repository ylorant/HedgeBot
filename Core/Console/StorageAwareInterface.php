<?php
namespace HedgeBot\Core\Console;

use HedgeBot\Core\Data\Provider;

interface StorageAwareInterface
{
    public function getConfigStorage();
    public function setConfigStorage(Provider $storage);
    public function getDataStorage();
    public function setDataStorage(Provider $storage);
}