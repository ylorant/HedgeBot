<?php

namespace HedgeBot\Core\Tikal;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Plugin as PluginAPI;
use HedgeBot\Core\Data\ObjectAccess;
use ReflectionClass;
use ReflectionMethod;
use stdClass;

/**
 * Class Server
 * @package HedgeBot\Core\Tikal
 */
class Server
{
    private $httpServer;
    private $endpoints = [];

    private $baseUrl;
    private $token;
    private $tokenlessMode;

    const DEFAULT_KEY_LEN = 32;

    /**
     * Server constructor.
     * @param ObjectAccess $config The configuration for the API.
     * @throws \Exception
     */
    public function __construct(ObjectAccess $config)
    {
        $address = !empty($config->address) ? $config->address : null;
        $port = !empty($config->port) ? $config->port : null;

        if (empty($config->token)) {
            HedgeBot::message('Generating new token for the Tikal API...');
            $config->token = self::randomString(self::DEFAULT_KEY_LEN);
        }

        $this->baseUrl = $config->baseUrl ? $config->baseUrl : '/';
        $this->token = $config->token;
        $this->tokenlessMode = isset($config->tokenless) ? $config->tokenless : false;

        if ($this->tokenlessMode) {
            HedgeBot::message("Token-less mode is insecure! We advise you to use a token unless you know what you're doing !",
                null, E_WARNING);
        }

        $this->httpServer = new HttpServer($address, $port);
    }

    //// INNER HTTPSERVER MANAGEMENT ////

    /**
     * Starts the HTTP Server and binds the request event.
     * We can't bind the event before because the internal Plugin API hasn't been initialized.
     */
    public function start()
    {
        $this->httpServer->start();

        $events = PluginAPI::getManager();
        $events->addEvent(HttpEvent::getType(), 'tikal', 'Request', array($this, 'httpRequest'));
    }

    /**
     * Processes the HTTP Server
     */
    public function process()
    {
        $this->httpServer->process();
    }

    //// API JSON-RPC HANDLING ////

    /**
     * Http request event callback. Called when the Http server has received a request.
     *
     * @param HttpEvent $event The request event received
     * @return bool
     * @throws \ReflectionException
     */
    public function httpRequest(HttpEvent $event)
    {
        $request = $event->request;

        $response = new HttpResponse($request);
        $url = $request->requestURI;

        if (!$this->tokenlessMode && (empty($request->headers['X-Token']) || $request->headers['X-Token'] != $this->token)) {
            return $this->sendErrorResponse($response, HttpResponse::UNAUTHORIZED);
        }

        HedgeBot::message("Tikal endpoint call: $0", [$url], E_DEBUG);
        if(!$this->hasEndpoint($url)) // Endpoint not found, return a 404
	{        
	    return $this->sendErrorResponse($response, HttpResponse::NOT_FOUND);
        }

        if ($request->method == "POST") // Only handle POST requests as JSON-RPC requests
        {
            // Checking that we have JSON.
            if ($request->contentType != "application/json") {
                return $this->sendErrorResponse($response, HttpResponse::BAD_REQUEST);
            }

            $request->setRequestURI($url); // Putting back formatted URL into request URI to avoid an extra parameter
            $result = $this->RPCExec($request, $response);

            if ($result) {
                $this->httpServer->send($response);
            }
        } elseif ($request->method == "GET") // GET queries return the list of available methods for said endpoint
        {
            $response->statusCode = HttpResponse::OK;
            $response->headers['Content-Type'] = 'application/json';
            $response->data = $this->getMethodList($url);
            $this->httpServer->send($response);
        }

    }

    /**
     * * Lists all the available methods in an endpoint.
     *
     * @param  string $url The endpoint's URL.
     * @return array The methods, with their parameters, in a multidimensional array.
     * @throws \ReflectionException
     */
    public function getMethodList($url)
    {
        $methodList = array();
        $reflectionClass = new ReflectionClass($this->getEndpoint($url));

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            // Ignore all magic methods
            if (strpos($reflectionMethod->getName(), "__") === 0) {
                continue;
            }

            $method = ['name' => $reflectionMethod->name, 'args' => []];
            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                $type = "mixed";
                if ($reflectionParameter->hasType()) {
                    $type = (string)$reflectionParameter->getType();
                }

                $method['args'][$reflectionParameter->getName()] = $type;
            }

            $methodList[] = $method;
        }

        return $methodList;
    }

    /**
     * Executes an RPC query.
     *
     * @param HttpRequest $request The HTTP Request containing the RPC.
     * @param HttpResponse $response The HTTP Response object to put the returned value into.
     * @return bool
     * @throws \ReflectionException
     */
    public function RPCExec(HttpRequest $request, HttpResponse $response)
    {
        $rpcQuery = $request->data;

        HedgeBot::message("Tikal: RPC call.", [], E_DEBUG);

        // Raise an error if the required JSON-RPC fields aren't present
        if (!isset($rpcQuery->jsonrpc) || !isset($rpcQuery->method) || !isset($rpcQuery->params)) {
            return $this->sendErrorResponse($response, HttpResponse::BAD_REQUEST);
        }

        $endpointClass = $this->getEndpoint($request->requestURI);
        $reflectionClass = new ReflectionClass($endpointClass);

        HedgeBot::message("Tikal: Called method: $0:$1", [$reflectionClass->getShortName(), $rpcQuery->method],
            E_DEBUG);

        // Check that the method exists and it isn't a magic method
        if (!$reflectionClass->hasMethod($rpcQuery->method) || strpos($rpcQuery->method, "__") === 0) {
            return $this->sendErrorResponse($response, HttpResponse::NOT_FOUND);
        }

        $reflectionMethod = $reflectionClass->getMethod($rpcQuery->method);

        // Binding parameters if they're named
        if ($rpcQuery->params instanceof stdClass) {
            $orderedParams = array();
            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                if (isset($rpcQuery->params->{$reflectionParameter->name})) {
                    $orderedParams[] = $rpcQuery->params->{$reflectionParameter->name};
                } elseif ($reflectionParameter->isOptional()) {
                    $orderedParams[] = $reflectionParameter->getDefaultValue();
                } else {
                    return $this->sendErrorResponse($response, HttpResponse::BAD_REQUEST);
                }
            }

            $rpcQuery->params = $orderedParams;
        }

        HedgeBot::message("Tikal: Calling RPC method: $0::$1",
            [$reflectionClass->getShortName(), $reflectionMethod->getName()], E_DEBUG);
        $funcResult = $reflectionMethod->invokeArgs($endpointClass, $rpcQuery->params);

        // Send result only if this is not a notification, i.e. an ID is given
        if (!empty($rpcQuery->id)) {
            $response->headers['Content-Type'] = 'application/json';
            $response->data = ["jsonrpc" => "2.0", "result" => $funcResult, "id" => $rpcQuery->id];
        }

        HedgeBot::message("Tikal: Success. Reply: $0.",
            [!empty($response->data["result"]) ? gettype($response->data["result"]) : "null"], E_DEBUG);

        return true;
    }

    /**
     * Generates and sends an error HttpResponse by its code.
     * @param HttpResponse $response    The HttpResponse to build from.
     * @param int $code The HTTP code to generate.
     * @return bool return always false
     */
    private function sendErrorResponse(HttpResponse $response, $code)
    {
        HedgeBot::message("Tikal: Error reply: $0.", [$code], E_DEBUG);
        $response->statusCode = $code;
        $response->data = HttpResponse::STATUS_MESSAGES[$response->statusCode];
        $this->httpServer->send($response);

        return false;
    }

    //// ENDPOINT MANAGEMENT ////

    /**
     * Registers an endpoint for the API. The inner methods of the bound object will be automatically bound to it.
     * @param  string $endpoint Endpoint part URL.
     * @param  object $class The object to bind to the endpoint
     * @return boolean True if the endpoint bound successfully, False otherwise (mainly, endpoint already exists).
     */
    public function addEndpoint($endpoint, $class)
    {
        if ($this->hasEndpoint($endpoint)) {
            return false;
        }

        HedgeBot::message("Adding Tikal endpoint '" . $endpoint . "' on class " . get_class($class));

        $this->endpoints[$endpoint] = $class;

        return true;
    }

    /**
     * Unregisters the endpoint from the API.
     * @param  string $endpoint The endpoint to release
     * @return boolean True if it has been released successfully, False otherwise (mainly endpoint doesn't exist).
     */
    public function removeEndpoint($endpoint)
    {
        if (!$this->hasEndpoint($endpoint)) {
            return false;
        }

        $this->endpoints[$endpoint] = $class;
    }

    /**
     * Checks if an endpoint exists.
     * @param  string $endpoint The endpoint to check.
     * @return boolean          True if the endpoint exists, false otherwise.
     */
    public function hasEndpoint($endpoint)
    {
        return isset($this->endpoints[$endpoint]);
    }

    /**
     * Gets an endpoint's class.
     * @param string $endpoint The endpoint to get the class of.
     * @return mixed           The class linked to the endpoint if it exists, False otherwise.
     */
    public function getEndpoint($endpoint)
    {
        if (!$this->hasEndpoint($endpoint)) {
            return false;
        }

        return $this->endpoints[$endpoint];
    }

    //// UTILS ////

    /**
     * Generate a random string, using a cryptographically secure
     * pseudo-random number generator (random_int)
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int $length How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     * @throws \Exception
     */
    public static function randomString(
        $length,
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ) {
        // If random_int() isn't available, try to load random_compat
        if (!function_exists('random_int')) {
            require_once ROOT_DIR . "lib/random_compat/lib/random.php";
        }

        $str = '';
        $max = strlen($keyspace) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }
}
