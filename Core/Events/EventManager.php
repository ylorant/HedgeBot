<?php

namespace HedgeBot\Core\Events;

use HedgeBot\Core\HedgeBot;
use ReflectionClass;
use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;
use Exception;
use RuntimeException;

/**
 * Class EventManager
 * @package HedgeBot\Core\Events
 *
 * @warning For server events and commands, the manager will only handle 1 callback by event at a time.
 * It is done for simplicity purposes, both at plugin's side
 * and at manager's side (I've noticed that it is not necessary to have multiple callbacks for an unique event,
 * unless you can think about getting your code clear)
 */
class EventManager
{
    protected $events = []; ///< Events storage
    protected $autoMethods = []; ///< Method prefixes for automatic event recognition
    protected $relaySocket = null; ///< Relay socket for event dynamic send
    protected $relaySocketLastConnect = null; ///< Relay socket last connection time, for auto reconnect
    protected $relayErrorCount = null; ///< Relay socket error count
    protected $relayConnected = false; ///< Relay connected toggle

    const RELAY_RECONNECT_INTERVAL = 3600 * 12; // Interval between socket IO reconnects
    const RELAY_MAX_ERROR_COUNT = 5; // Max error count on relay read/write
    const RELAY_RECONNECT_DELAY = 60; // Relay reconnect delay when disconnected after errors

    /**
     * Adds a custom event listener, with its auto-binding method prefix.
     * This function adds a new event listener to the event system.
     * It allows a plugin to create his own space of events, which it cans trigger after, allowing better
     * and easier interaction between plugins.
     *
     * @param string $name The name the listener will get.
     * @param $autoMethodPrefix The prefix there will be used by other plugins for automatic method binding.
     *
     * @return bool TRUE if the listener was correctly created, FALSE otherwise.
     */
    public function addEventListener($name, $autoMethodPrefix)
    {
        if (isset($this->events[$name])) {
            HedgeBot::message('Error : Already defined Event Listener: $0', $name, E_DEBUG);
            return false;
        }

        $this->events[$name] = [];
        $this->autoMethods[$name] = $autoMethodPrefix;

        return true;
    }

    /**
     * Deletes an event listener.
     * This functions deletes an event listener from the event system.
     * The underlying events for this listener will be deleted as well.
     *
     * @param string $name The listener's name
     *
     * @return bool TRUE if the listener has been deleted succesfully, FALSE otherwise.
     */
    public function deleteEventListener($name)
    {
        if (!isset($this->events[$name])) {
            HedgeBot::message('Error : Undefined Event Listener: $0', $name, E_DEBUG);
            return false;
        }

        unset($this->events[$name]);

        return true;
    }

    /**
     * Adds an event to an event listener.
     * This function adds an event to an anlready defined event listener.
     * The callback linked to the event will be later distinguished of the others by an identifier
     * which must be unique in the same event.
     *
     * @param $listener The listener in which the event will be added. Must be defined when adding the event.
     * @param $id The callback identifier. Must be unique in the same event, can be duplicated across events.
     * @param $event The event to which the callback will be linked.
     * @param $callback The callback that will be called when the event is triggered.
     *
     * @return bool TRUE if the event added correctly, FALSE otherwise.
     */
    public function addEvent($listener, $id, $event, $callback)
    {
        // Normalize event name
        $event = Event::normalize($event);

        if (!isset($this->events[$listener])) {
            HedgeBot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
            return false;
        }

        if (!isset($this->events[$listener][$event])) {
            $this->events[$listener][$event] = [];
        }

        if (isset($this->events[$listener][$event][$id])) {
            HedgeBot::message('Error: Already defined identifier: $0', $id, E_DEBUG);
            return false;
        }

        //Check if method exists
        if (!method_exists($callback[0], $callback[1])) {
            HedgeBot::message('Error : Target method does not exists.', [], E_DEBUG);
            return false;
        }

        $this->events[$listener][$event][$id] = $callback;

        return true;
    }

    /**
     * Deletes an event from the event listener.
     * This function deletes an already defined callback (bound to an event) from the event listener,
     * so it will not be called by event triggering again.
     *
     * @param string $listener The event listener from which the callback will be deleted.
     * @param string $event The event name (from which the callback is triggered).
     * @param $id The callback's ID.
     *
     * @return bool TRUE if the event deleted correctly, FALSE otherwise.
     */
    public function deleteEvent($listener, $event, $id)
    {
        if (!isset($this->events[$listener])) {
            HedgeBot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
            return false;
        }

        if (!isset($this->events[$listener][$event])) {
            HedgeBot::message('Error: Undefined Event: $0', $id, E_DEBUG);
            return false;
        }

        if (!isset($this->events[$listener][$event][$id])) {
            HedgeBot::message('Error: Already defined identifier: $0', $id, E_DEBUG);
            return false;
        }

        unset($this->events[$listener][$event][$id]);

        return true;
    }

    /**
     * Returns the list of defined events on a listener.
     * This function returns all the events defined for an event listener.
     *
     * @param $listener The listener where we'll get the events.
     * @return array The list of the events from the listener.
     */
    public function getEvents($listener)
    {
        return array_keys($this->events[$listener]);
    }

    /**
     * Calls an event for an event listener.
     *
     * @param Event $event The event to call.
     * @return bool TRUE if event has been called correctly, FALSE otherwise.
     */
    public function callEvent(Event $event)
    {
        $listener = $event::getType();
        $name = $event->name;

        if (!isset($this->events[$listener])) {
            HedgeBot::message('Undefined Event Listener: $0', $listener, E_WARNING);
            return false;
        }

        // Process only if there are callbacks bound to the event
        if (!empty($this->events[$listener][$name])) {
            foreach ($this->events[$listener][$name] as $id => $callback) {
                if (!$event->propagation) {
                    break;
                }
            
                call_user_func_array($callback, [$event]);
            }
        }


        // Notifying other programs of the even through the socket if connected
        if(!empty($this->relaySocket) && $this->relayConnected && $event::isBroadcastable()) {
            try {
                $this->relaySocket->emit('event', [
                    "listener" => $listener,
                    "event" => $event->toArray()
                ]);

                $this->relayErrorCount = 0;
            } catch(Exception $e) {
                HedgeBot::message("Cannot send event notification to SocketIO: $0", $e->getMessage(), E_WARNING);
                $this->relayErrorCount++;

                // On 5 errors, we disconnect from the socket altogether and schedule a reconnect
                if($this->relayErrorCount == self::RELAY_MAX_ERROR_COUNT) {
                    $this->disconnectRelay();
                    $this->relaySocketLastConnect = time() - self::RELAY_RECONNECT_INTERVAL + self::RELAY_RECONNECT_DELAY;
                }
            }
        }

        
        return true;
    }

    /**
     * Tries to automatically register events.
     * This methods tries to register events automatically depending on their methods names, using a defined
     * prefix common for the event listener (defined when the listener was created).
     *
     * @param $object The object to load events from.
     * @return array The list of bound events.
     * @throws \ReflectionException
     */
    public function autoload($object)
    {
        $reflectionClass = new ReflectionClass($object);
        $methods = $reflectionClass->getMethods(); //Get all class methods for plugin
        $addedEvents = [];

        //Analyse all class methods
        foreach ($methods as $method) {
            $methodName = $method->getName();
            //Checks for plugin-defined events
            foreach ($this->autoMethods as $listener => $prefix) {
                if (preg_match('#^' . $prefix . '#', $methodName)) {
                    $event = Event::normalize(preg_replace('#' . $prefix . '(.+)#', '$1', $methodName));
                    HedgeBot::message(
                        'Binding method $0::$1 on event $2/$3',
                        [$reflectionClass->getShortName(), $methodName, $listener, $event],
                        E_DEBUG
                    );
                    $this->addEvent($listener, $reflectionClass->getName(), $event, [$object, $methodName]);
                    $addedEvents[] = [
                        'listener' => $listener,
                        'event' => $event,
                        'callback' => [$object, $methodName]
                    ];
                }
            }
        }

        return $addedEvents;
    }

    /**
     * Gets events by their callee ID.
     * This method gets all events with a given callee function ID, regardless of its
     * listener.
     *
     * @param string $id The id to search.
     * @return array
     */
    public function getEventsById($id)
    {
        $matchedEvents = [];
        foreach ($this->events as $listenerName => $events) {
            foreach ($events as $eventName => $callbacks) {
                if (in_array($id, array_keys($callbacks))) {
                    $matchedEvents[] = ['listener' => $listenerName, 'event' => $eventName];
                }
            }
        }

        return $matchedEvents;
    }

    /** Deletes events by their callee ID.
     * This method deletes all events with a given callee function ID, regardless of its
     * listener.
     *
     * @param string $id The id to search and delete from.
     *
     * @return null
     */
    public function deleteEventsById($id)
    {
        $eventsToDelete = $this->getEventsById($id);

        // Delete the events' callbacks
        foreach ($eventsToDelete as $event) {
            unset($this->events[$event['listener']][$event['event']][$id]);
        }

        return null;
    }

    //// Socket IO relay client ////

    /**
     * Initializes a Socket.IO client from the given host.
     * 
     * @param string $host The host to connect to.
     * @return void 
     */
    public function initRelay($host)
    {
        $this->relaySocket = new Client(new Version2X($host));
    }

    /**
     * Connects to the given relay socket via Socket.IO.
     * 
     * @return bool True if the connection succeeded, false if not. 
     */
    public function connectRelay()
    {
        HedgeBot::message("Connecting to Socket.IO relay...", [], E_DEBUG);
        try {
            $this->relaySocket->initialize();
            $this->relaySocketLastConnect = time();
            $this->relayConnected = true;

            HedgeBot::message("Connected to Socket.IO relay.", [], E_DEBUG);
        } catch(RuntimeException $e) {
            $this->relaySocket = null;
            return false;
        }

        return true;
    }

    /**
     * Checks wether the relay is connected.
     * 
     * @return bool True if the relay is connected, false if not.
     */
    public function isRelayAvailable()
    {
        return $this->relaySocket != null;
    }

    /**
     * Reads and keeps alive the relay Socket.IO connection.
     * 
     * @return void 
     */
    public function keepRelayAlive()
    {
        if($this->relayConnected) {
            try {
                $this->relaySocket->getEngine()->keepAlive();
            } catch(Exception $e) {
                HedgeBot::message("Cannot keep SocketIO alive: $0", $e->getMessage(), E_WARNING);
                $this->relayErrorCount++;

                // On 5 errors, we disconnect from the socket altogether and schedule a reconnect
                if($this->relayErrorCount == self::RELAY_MAX_ERROR_COUNT) {
                    $this->disconnectRelay();
                    $this->relaySocketLastConnect = time() - self::RELAY_RECONNECT_INTERVAL + self::RELAY_RECONNECT_DELAY;
                }
            }
        }

        // Every day, reconnect to the relay
        if($this->relaySocketLastConnect + self::RELAY_RECONNECT_INTERVAL < time()) {
            HedgeBot::message("Reconnecting to Socket.IO relay routinely...", [], E_DEBUG);
            $this->disconnectRelay();
            $this->connectRelay();
        }
    }

    /**
     * Disconnects from the relay.
     * @return void 
     */
    public function disconnectRelay()
    {
        HedgeBot::message("Disconnecting from Socket.IO relay...", [], E_DEBUG);
        try {
            $this->relaySocket->close();
        } catch(Exception $e) {
            HedgeBot::message("Cannot disconnect from SocketIO: $0", $e->getMessage(), E_WARNING);
        } finally {
            $this->relayConnected = false;
        }
    }
}
