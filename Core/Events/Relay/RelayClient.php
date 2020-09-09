<?php
namespace HedgeBot\Core\Events\Relay;

use HedgeBot\Core\Events\Event;
use ReflectionClass;

/**
 * RelayClient interface.
 * 
 * This interface represents a client to send events through, to ensure live updates
 * on remote interfaces.
 * 
 * @package HedgeBot\Core\Events\Relay
 */
abstract class RelayClient
{
    protected static $classCache;

    /**
     * Initializes the client with the given configuration.
     * 
     * @param array $config The configuration as an array.
     * @return void
     */
    public abstract function initialize(array $config);

    /**
     * Connects the client.
     * 
     * @return bool True if the client connected, false if not.
     */
    public abstract function connect();

    /**
     * Disconnects the client.
     * 
     * @return void 
     */
    public abstract function disconnect();

    /**
     * Checks whether the client is available or not.
     * 
     * @return bool True if the relay is available, false if not.
     */
    public abstract function isAvailable();

    /**
     * Keeps the client connection alive, if needed.
     * 
     * @return void 
     */
    public abstract function keepAlive();

    /**
     * Publishes an event via the client.
     * 
     * @param Event $event The event to publish.
     * @return bool True if the event has been published, false if not.
     */
    public abstract function publish($listener, Event $event);

    /**
     * Returns the relay client type.
     * 
     * @return string The relay client type.
     */
    public abstract static function getType();

    // STATIC RESOLVING FUNCTIONS //

    /**
     * Resolves a client from its type, and instantiates an object for it.
     * 
     * @param string $type The client type.
     * @return RelayClient|null A client instance if found, null if not. 
     */
    public static function resolveClient($type)
    {
        $clientClasses = self::getClientClasses();

        if(!empty($clientClasses[$type])) {
            return new $clientClasses[$type]();
        }

        return false;
    }

    /**
     * Gets the list of available relay clients.
     * 
     * @return array The list of available relay clients.
     */
    public static function getClientList()
    {
        $clientClasses = self::getClientClasses();
        return array_keys($clientClasses);
    }

    /**
     * Uses reflection to load all the available relay client classes.
     * 
     * @return array The relay client classes.
     */
    protected static function getClientClasses()
    {
        // Refresh the storage classes cache if needed
        if(empty(self::$classCache)) {
            // Getting the current namespace to be able to load classes correctly
            $reflectionClass = new ReflectionClass(self::class);
            $currentNamespace = $reflectionClass->getNamespaceName();

            // Classes to be ignored
            $ignoreClasses = array(
                'RelayClient',
            );

            // Scanning the directory of this file, which contains the other classes.
            $currentDir = scandir(__DIR__);
            foreach ($currentDir as $file) {
                if (!is_file(__DIR__ . '/' . $file)) {
                    continue;
                }

                $className = str_replace('.php', '', $file);
                if (in_array($className, $ignoreClasses)) {
                    continue;
                }

                $className = $currentNamespace . "\\" . $className;
                $relayType = $className::getType();
                self::$classCache[$relayType] = $className;
            }
        }

        return self::$classCache;
    }
}