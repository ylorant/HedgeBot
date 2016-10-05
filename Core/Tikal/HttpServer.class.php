<?php
namespace HedgeBot\Core\Tikal;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Plugin as PluginAPI;
use stdClass;

class HttpServer
{
    private $socket; ///< Server socket
    private $address = '127.0.0.1'; ///< Bound address
    private $port = 80; ///< Bound port
    private $timeout = 20; ///< Timeout interval
    private $freedIDs = [];
    private $clients = [];
    private $times = [];

    public function __construct($address = null, $port = null)
    {
        if(filter_var($address, FILTER_VALIDATE_IP)) //Match IP
            $this->address = $address;
        else
            HedgeBot::message('HTTP: Address not valid : $0', [$address], E_WARNING);

        if(in_array($port, range(0, 65535))) //Match port
            $this->port = $port;
        else
            HedgeBot::message('HTTP: Port not valid : $0', [$port], E_WARNING);
    }

    public function start()
    {
        // Creating event listeners
        $events = PluginAPI::getManager();
		$events->addEventListener('http', 'HTTP');

        // Create the socket and set its default options
		$this->socket = socket_create(AF_INET,SOCK_STREAM, SOL_TCP);
        socket_set_nonblock($this->socket);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        // Bind it, effectively creating the server
        socket_bind($this->socket, $this->address, $this->port);
        socket_listen($this->socket);

        HedgeBot::message('HTTP: Started listening on $0:$1', [$this->address, $this->port]);
    }

    public function process()
    {
        //Some client want to connect
		if(($tempSocket = @socket_accept($this->socket)) !== FALSE)
		{
            if(isset($this->freedID[0]))
			{
				$id = $this->freedIDs[0];
				unset($this->freedIDs[0]);
				sort($this->freedID);
			}
			else
				$id = count($this->clients);

			$this->clients[$id] = $tempSocket;
			$this->times[$id] = time();
        }

        $read = [];
        $null = null;
		foreach($this->clients as $id => $current)
			$read[$id] = $current;

        $modified = 0;
        if(!empty($read))
            $modified = socket_select($read, $null, $null, 0);

        if($modified > 0)
        {
            foreach($read as $id => $client)
			{
				$buffer = '';
				$buffer = socket_read($this->clients[$id], 1024);
				if($buffer)
				{
					$this->times[$id] = time();
                    $request = new HttpRequest($id, $buffer);
                    PluginAPI::getManager()->callEvent('http', 'Request', $request);
				}
			}
        }

		$now = time();
		//Checking timeouts
		foreach($this->times as $id => $time)
		{
			if($time + $this->timeout < $now)
			{
				HedgeBot::message('Timeout from client $0', [$id], E_DEBUG);
				$this->closeConnection($id);
			}
		}
    }

	public function closeConnection($id)
	{
		if(isset($this->clients[$id]))
		{
			$return = socket_close($this->clients[$id]);
			if($return === FALSE)
				HedgeBot::message('HTTP: Cannot close the socket : '.socket_strerror(socket_last_error()), E_WARNING);
			unset($this->clients[$id]);
			unset($this->buffers[$id]);
			unset($this->times[$id]);
		}
	}

    public function send(HttpResponse $response)
    {
        $replyString = $response->generate();
        socket_write($this->clients[$response->request->clientId], $replyString);

        // Close a client who has not requested to be kept alive
        if(!$response->request->keepAlive)
            $this->closeConnection($response->request->clientId);
    }
}
