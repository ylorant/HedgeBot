<?php

namespace HedgeBot\Core\Tikal;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Plugin as PluginAPI;

/**
 * Class HttpServer
 * @package HedgeBot\Core\Tikal
 */
class HttpServer
{
    private $socket; ///< Server socket
    /** @var string $address */
    private $address = '127.0.0.1'; ///< Bound address
    /** @var int $port */
    private $port = 80; ///< Bound port
    private $timeout = 20; ///< Timeout interval
    private $freedIDs = [];
    private $clients = [];
    private $buffers = [];
    private $times = [];

    const PACKET_LENGTH = 65535;

    /**
     * HttpServer constructor.
     * @param null $address
     * @param null $port
     */
    public function __construct($address = null, $port = null)
    {
        //Match IP
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            $this->address = $address;
        } else {
            HedgeBot::message('HTTP: Address not valid : $0', [$address], E_WARNING);
        }
        //Match port
        if (in_array($port, range(0, 65535))) {
            $this->port = $port;
        } else {
            HedgeBot::message('HTTP: Port not valid : $0', [$port], E_WARNING);
        }
    }

    /**
     *
     */
    public function start()
    {
        // Creating event listeners
        $events = PluginAPI::getManager();
        $events->addEventListener(HttpEvent::getType(), 'HTTP');

        // Create the socket and set its default options
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_nonblock($this->socket);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        // Bind it, effectively creating the server
        socket_bind($this->socket, $this->address, $this->port);
        socket_listen($this->socket);

        HedgeBot::message('HTTP: Started listening on $0:$1', [$this->address, $this->port]);
    }

    /**
     *
     */
    public function process()
    {
        //Some client want to connect
        if (($tempSocket = @socket_accept($this->socket)) !== false) {
            if (isset($this->freedIDs[0])) {
                $id = $this->freedIDs[0];
                unset($this->freedIDs[0]);
                sort($this->freedIDs);
            } else {
                $id = count($this->clients);
            }

            $this->clients[$id] = $tempSocket;
            $this->times[$id] = time();
        }

        $read = [];
        $null = null;
        foreach ($this->clients as $id => $current) {
            $read[$id] = $current;
        }

        $modified = 0;
        if (!empty($read)) {
            $modified = socket_select($read, $null, $null, 0);
        }

        if ($modified > 0) {
            foreach ($read as $id => $client) {
                $buffer = socket_read($this->clients[$id], self::PACKET_LENGTH);
                if ($buffer) {
                    $this->times[$id] = time();

                    if (!empty($this->buffers[$id])) {
                        $buffer = $this->buffers[$id] . $buffer;
                    }

                    $request = new HttpRequest($id, $buffer);
                    $requestComplete = false;

                    // Handle incomplete requests by basing ourselves on the content length header
                    if (!empty($request->contentLength)) {
                        if ($request->contentLength <= strlen($request->rawData)) {
                            $requestComplete = true;
                        } else { // Store content data into the buffer for the given connection ID
                            $this->buffers[$id] = $buffer;
                        }
                    } else { // No content-length header specified, we consider the request as complete
                        $requestComplete = true;
                    }
                    
                    if ($requestComplete) {
                        PluginAPI::getManager()->callEvent(new HttpEvent('Request', ['request' => $request]));
                    } elseif (!empty($request->expect) && $request->expect == "100-Continue") {
                        $this->send(HttpResponse::make($request, HttpResponse::CONTINUE));
                    }
                }
            }
        }

        $now = time();
        //Checking timeouts
        foreach ($this->times as $id => $time) {
            if ($time + $this->timeout < $now) {
                $this->closeConnection($id);
            }
        }
    }

    /**
     * @param $id
     */
    public function closeConnection($id)
    {
        if (isset($this->clients[$id])) {
            $return = socket_close($this->clients[$id]);
            if ($return === false) {
                HedgeBot::message('HTTP: Cannot close the socket : ' . socket_strerror(socket_last_error()), E_WARNING);
            }
            unset($this->clients[$id]);
            unset($this->buffers[$id]);
            unset($this->times[$id]);
        }
    }

    /**
     * @param HttpResponse $response
     */
    public function send(HttpResponse $response)
    {
        $replyString = $response->generate();
        socket_write($this->clients[$response->request->clientId], $replyString);

        // Close a client who has not requested to be kept alive
        if (!$response->request->keepAlive) {
            $this->closeConnection($response->request->clientId);
        }
    }
}
