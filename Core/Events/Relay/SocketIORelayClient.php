<?php
namespace HedgeBot\Core\Events\Relay;

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;
use HedgeBot\Core\Events\Event;
use HedgeBot\Core\HedgeBot;
use RuntimeException;

/**
 * SocketIORelayClient class.
 * 
 * This class implements a relay client using a simple Socket.IO connection to a Socket.IO relay server.
 * Uses Elephant.io as the underlying library.
 * 
 * @package HedgeBot\Core\Events\Relay
 */
class SocketIORelayClient extends RelayClient
{
    protected $relaySocket = null; ///< Relay socket for event dynamic send
    protected $relaySocketLastConnect = null; ///< Relay socket last connection, for periodic reconnects

    const MANDATORY_CONFIG = [
        'host'
    ];

    /**
     * @inheritDoc
     */
    public static function getType()
    {
        return "socketio";
    }

    /**
     * @inheritDoc
     */
    public function initialize(array $config)
    {
        // Check that mandatory configuration is present
        $configCheck = array_diff(self::MANDATORY_CONFIG, array_keys($config));
        if(!empty($configCheck)) {
            throw new RuntimeException("Missing configuration parameters: " . json_encode($configCheck));
        }
        
        $this->relaySocket = new Client(new Version2X($config['host']));
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        try {
            $this->relaySocket->initialize();
            $this->relaySocketLastConnect = time();
        } catch(RuntimeException $e) {
            $this->relaySocket = null;
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        $this->relaySocket->close();
    }

    /**
     * @inheritDoc
     */
    public function keepAlive()
    {
        $this->relaySocket->getEngine()->keepAlive();

        // Every day, reconnect to the relay
        if($this->relaySocketLastConnect + 86400 < time()) {
            HedgeBot::message("Reconnecting to Socket.IO relay routinely...", [], E_DEBUG);
            $this->disconnect();
            $this->connect();
        }
    }

    /**
     * @inheritDoc
     */
    public function isAvailable()
    {
        return $this->relaySocket != null;
    }

    public function publish($listener, Event $event)
    {
        try {
            $this->relaySocket->emit('event', [
                "listener" => $listener,
                "event" => $event->toArray()
            ]);
        } catch(RuntimeException $e) {
            HedgeBot::message("Cannot send event notification to SocketIO: $0", $e->getMessage(), E_WARNING);
        }
    }
}