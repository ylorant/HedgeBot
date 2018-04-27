<?php

namespace HedgeBot\Core\API\Twitch;

/**
 * Class Kraken
 * @package HedgeBot\Core\API\Twitch
 */
class Kraken
{
    private static $kraken;

    /**
     * @param $object
     */
    public static function setObject($object)
    {
        self::$kraken = $object;
    }

    /**
     * @return mixed
     */
    public static function getObject()
    {
        return self::$kraken;
    }

    /**
     * @param $service
     * @return mixed
     */
    public static function get($service)
    {
        return self::$kraken->getService($service);
    }
}
