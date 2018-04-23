<?php

namespace HedgeBot\Core\Service\Twitch\Kraken;

use ReflectionClass;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Service\Twitch\AuthManager;

/**  Twitch Kraken (v5) API client base.
 * Allows to query Twitch's Kraken API servers via HTTP. This class is using Twitch's self-describing API to build queries,
 * along with PHP's magic methods to get names, it'll have mostly auto-building magic shenanigans in it.
 *
 * To get all of Twitch's API capabilities, go check their doc there :
 * https://github.com/justintv/Twitch-API
 */
class Kraken
{
    protected $services = array();

    /** @var AuthManager The authentication manager that this client will use. */
    protected $authManager;

    const KRAKEN_BASEURL = "https://api.twitch.tv/kraken";
    const RETURN_MIMETYPE = "application/vnd.twitchtv.v5+json";

    // Query types enum
    const QUERY_TYPE_GET = "GET";
    const QUERY_TYPE_POST = "POST";
    const QUERY_TYPE_PUT = "PUT";
    const QUERY_TYPE_DELETE = "DELETE";

    // Useful constants
    const SERVICES_NAMESPACE = "Services";
    const DATA_QUERIES = [
        self::QUERY_TYPE_POST,
        self::QUERY_TYPE_PUT
    ]; // Indicates which methods sends their data in the request body

    /**
     * Constructor.
     *
     * @param string $clientID The client ID that this client will use to connect to the Twitch API.
     */
    public function __construct(AuthManager $authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Discovers the available services, by analyzing the folder where they are supposed to be kept.
     * // FIXME: Use a RecursiveDirectoryIterator here
     */
    public function discoverServices()
    {
        $servicesPath = __DIR__ . '/' . self::SERVICES_NAMESPACE;
        $dirContents = scandir($servicesPath);

        // Getting self namespace
        $reflectionClass = new ReflectionClass($this);
        $servicesNamespace = $reflectionClass->getNamespaceName() . '\\' . self::SERVICES_NAMESPACE;
        foreach ($dirContents as $item) {
            // Only valable for classes
            if (is_file($servicesPath . '/' . $item) && strpos($item, '.php') === strlen($item) - 4) {
                $className = $servicesNamespace . '\\' . substr($item, 0, -4);
                $serviceName = $className::getServiceName();

                if (!empty($serviceName) && !isset($this->services[$serviceName])) {
                    $this->services[$serviceName] = new $className($this);
                }
            }
        }
    }

    /** Executes a query on the Twitch API.
     * This method allows to execute directly a query on Twitch's Kraken API. It takes into account whether it was called from
     * a defined service or directly from the root Kraken class, to build the correct path.
     *
     * @param $type The query type. You can use Kraken::QUERY_TYPE_* enum values for easier understanding.
     * @param $url The endpoint to query. Auto-magically builds the correct path.
     * @param $parameters The parameters to give to the query, as a key-value array. Optional.
     * @param $accessChannel If the used query needs to have an access token linked to it,
     *                       specifies the channel from which to take the token. Optional.
     *
     * @return The API response, as an object translated from the JSON.
     */
    public function query($type, $url, array $parameters = [], $accessChannel = null)
    {
        // For GET queries, append parameters to url as query parameters
        if (!in_array($type, self::DATA_QUERIES) && !empty($parameters)) {
            if (strpos($url, '?') === false) {
                $url .= '?';
            }

            $url .= http_build_query($parameters);
        }

        $url = self::KRAKEN_BASEURL . '/' . trim($url, '/'); // Remove any trailing slashes from the url endpoint
        $curl = curl_init($url);

        HedgeBot::message("Twitch API Call: $0 $1", [$type, $url], E_DEBUG);

        $httpHeaders = [
            "Accept: " . self::RETURN_MIMETYPE,
            "Client-ID: " . $this->authManager->getClientID()
        ];

        if (!empty($accessChannel) && $this->authManager->hasAccessToken($accessChannel)) {
            $httpHeaders[] = "Authorization: OAuth " . $this->authManager->getAccessToken($accessChannel);
        }


        // Only POSTs and PUTs need to have data defined as body, in JSON
        if (in_array($type, self::DATA_QUERIES)) {
            $httpHeaders[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($parameters));
        }

        // Set base common options
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_HTTPHEADER => $httpHeaders
        ]);

        $reply = curl_exec($curl);

        return json_decode($reply);
    }

    /**
     * Gets a service handler.
     *
     * @param $serviceName The service name to get.
     * @return The service handler if found, else null.
     */
    public function getService($serviceName)
    {
        if (isset($this->services[$serviceName])) {
            return $this->services[$serviceName];
        } else {
            return null;
        }
    }
}
