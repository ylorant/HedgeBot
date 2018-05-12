<?php

namespace HedgeBot\Core\Service\Twitch\Kraken\Services;

use HedgeBot\Core\Service\Twitch\Kraken\Kraken;

/**
 * Class Service
 * @package HedgeBot\Core\Service\Twitch\Kraken\Services
 */
abstract class Service extends Kraken
{
    protected $kraken; //< Kraken base object reference
    protected $servicepath = ''; ///< Service base path, starting from the api root.

    const SERVICE_NAME = "";

    /**
     * Service constructor.
     * @param $kraken
     */
    public function __construct($kraken)
    {
        $this->kraken = $kraken;
    }

    /**
     * Proxy for Kraken::query(). Auto-appends the service base path to the URL.
     * @see Kraken::query()
     *
     * @param $type
     * @param $url
     * @param array $parameters
     * @param null $accessChannel
     * @return mixed
     */
    public function query($type, $url, array $parameters = [], $accessChannel = null)
    {
        $url = $this->servicepath . '/' . trim($url, '/');
        return $this->kraken->query($type, $url, $parameters, $accessChannel);
    }

    /**
     * Returns the service's name from the defined constants in subclasses.
     *
     * @return string The service's name
     */
    public static function getServiceName()
    {
        return static::SERVICE_NAME;
    }
}
