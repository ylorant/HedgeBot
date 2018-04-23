<?php

namespace HedgeBot\Core\API\Twitch;

class Kraken
{
    private static $kraken;

    public static function setObject($object)
    {
        self::$kraken = $object;
    }

    public static function getObject()
    {
        return self::$kraken;
    }

    public static function get($service)
    {
        return self::$kraken->getService($service);
    }
}
