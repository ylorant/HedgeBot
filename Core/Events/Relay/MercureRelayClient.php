<?php
namespace HedgeBot\Core\Events\Relay;

use Firebase\JWT\JWT;
use HedgeBot\Core\Events\Event;
use RuntimeException;
use Symfony\Component\Mercure\Jwt\StaticJwtProvider;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;

/**
 * MercureRelayClient class.
 * Implements a relay client through a Mercure hub. It will publish 
 * 
 * @package HedgeBot\Core\Events\Relay
 */
class MercureRelayClient extends RelayClient
{
    /** @var array $config */
    protected $config;
    /** @var Publisher $publisher */
    protected $publisher;

    const MANDATORY_CONFIG = [
        'hubUrl',
        'topic',
        'jwtKey'
    ];

    /**
     * @inheritDoc
     */
    public static function getType()
    {
        return "mercure";
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

        $this->config = $config;
        $this->publisher = new Publisher($config['hubUrl'], new StaticJwtProvider($this->generateJWT()));
    }

    /**
     * @inheritDoc
     */
    public function publish($listener, Event $event)
    {
        $update = new Update(
            $this->config['topic'], 
            json_encode([
                'listener' => $listener,
                'event' => $event->toArray()
            ])
        );

        ($this->publisher)($update);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        
    }

    /**
     * @inheritDoc
     */
    public function isAvailable()
    {
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function keepAlive()
    {
        
    }

    /**
     * Generates a JSON Web Token for the currently configured key that allows publishing through
     * Mercure.
     * 
     * @return string The JSON Web Token.
     */
    protected function generateJWT()
    {
        // Standard payload for Mercure publishing
        $payload = [
            'mercure' => [
                'publish' => ['*'],
            ],
        ];

        return JWT::encode($payload, $this->config['jwtKey']);
    }
}