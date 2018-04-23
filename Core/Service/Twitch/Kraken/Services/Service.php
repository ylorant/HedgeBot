<?php

namespace HedgeBot\Core\Service\Twitch\Kraken\Services;

use HedgeBot\Core\Service\Twitch\Kraken\Kraken;

abstract class Service extends Kraken
{
    protected $kraken; //< Kraken base object reference
    protected $servicepath = ''; ///< Service base path, starting from the api root.

    const SERVICE_NAME = "";

    public function __construct($kraken)
    {
        $this->kraken = $kraken;
    }

    /**
     * Proxy for Kraken::query(). Auto-appends the service base path to the URL.
     *
     * @see Kraken::query()
     */
    public function query($type, $url, array $parameters = [], $accessChannel = null)
    {
        $url = $this->servicepath . '/' . trim($url, '/');
        return $this->kraken->query($type, $url, $parameters, $accessChannel);
    }

    /**
     * Returns the service's name from the defined constants in subclasses.
     *
     * @return The service's name, as a string.
     */
    public static function getServiceName()
    {
        return static::SERVICE_NAME;
    }
}
