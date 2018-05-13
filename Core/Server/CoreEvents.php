<?php

namespace HedgeBot\Core\Server;

use HedgeBot\Core\API\Server;
use HedgeBot\Core\API\Security;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\ServerList;
use HedgeBot\Core\API\Data;
use HedgeBot\Core\API\Config;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\HedgeBot;

/**
 * Core Events handler class.
 * This class handles basic server events, and as such, it handles the basic workflow
 * for the bot connection lifecycle (authentication against the server and things).
 */
class CoreEvents
{
    private $names;
    private $lastMessages = [];

    public function __construct()
    {
        $this->names = [];

        $events = Plugin::getManager();

        // Server commands the bot is supposed to answer to
        // TODO: Use autoloading question mark ?
        $events->addEvent(ServerEvent::getType(), 'coreevents', '001', [$this, 'ServerConnected']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'kick', [$this, 'ServerKick']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'ping', [$this, 'ServerPing']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', '353', [$this, 'ServerNamesReply']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', '366', [$this, 'ServerEndOfNames']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'mode', [$this, 'ServerMode']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'join', [$this, 'ServerJoin']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'part', [$this, 'ServerPart']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'privmsg', [$this, 'ServerMessage']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'notice', [$this, 'ServerMessage']);
        $events->addEvent(ServerEvent::getType(), 'coreevents', 'whisper', [$this, 'ServerMessage']);

        // Core events
        $events->addEvent(CoreEvent::getType(), 'coreevents', 'ServerMessage', [$this, 'CoreEventServerMessage']);
        $events->addEvent(CoreEvent::getType(), 'coreevents', 'DataUpdate', [$this, 'CoreEventDataUpdate']);

        // Routines
        $events->addRoutine($this, 'RoutinePingServer', 60);
        $events->addRoutine($this, 'RoutineCheckStorages', 2);
        $events->addRoutine($this, 'RoutineReconnect', 5);
    }

    public function RoutineCheckStorages()
    {
        $events = Plugin::getManager();

        $updated = Config::checkUpdate();
        if ($updated) {
            HedgeBot::message("Configuration has been updated externally", [], E_DEBUG);
            $events->callEvent(new CoreEvent('ConfigUpdate'));
        }

        $updated = Data::checkUpdate();
        if ($updated) {
            HedgeBot::message("Data has been updated externally", [], E_DEBUG);
            $events->callEvent(new CoreEvent('DataUpdate'));
        }
    }

    public function RoutinePingServer()
    {
        $time = time();

        foreach (ServerList::get() as $server) {
            $srv = ServerList::getServer($server);
            IRC::setObject($srv->getIRC());
            Server::setObject($srv);

            // Before pinging, check if last message from server isn't older than 90 secs (timeout)
            if (!empty($this->lastMessages[$server]) && $this->lastMessages[$server] < ($time - 90)) {
                HedgeBot::message('Connection to server $0 lost, reconnecting.', array(Server::getName()), E_WARNING);
                Server::reconnect();

                continue;
            }

            IRC::ping();
        }
    }

    public function RoutineReconnect()
    {
        foreach (ServerList::get() as $server) {
            $srv = ServerList::getServer($server);

            if (!$srv->isConnected()) {
                $srv->connect();
            }
        }
    }

    /**
     * @param CoreEvent $ev
     */
    public function CoreEventServerMessage(CoreEvent $ev)
    {
        $serverName = Server::getName();
        $this->lastMessages[$serverName] = time();
    }

    /**
     * @param CoreEvent $ev
     */
    public function CoreEventDataUpdate(CoreEvent $ev)
    {
        HedgeBot::message("Reloading access control lists...");
        Security::refreshFromStorage();
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerConnected(ServerEvent $ev)
    {
        $config = Server::getConfig();

        // Autoperform commands
        if (!empty($config['autoperform'])) {
            foreach ($config['autoperform'] as $action) {
                IRC::send($action);
            }
        }

        IRC::joinChannels($config['channels']);
        HedgeBot::getInstance()->initialized = true;
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerKick(ServerEvent $ev)
    {
        if ($ev->additionnal[0] == Server::getNick()) {
            IRC::joinChannel($ev->channel);
        }
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerPing(ServerEvent $ev)
    {
        IRC::send('PONG :' . $ev->additionnal);
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerJoin(ServerEvent $ev)
    {
        if (strtolower($ev->nick) != strtolower(Server::getNick())) {
            if ($ev->message && !$ev->channel) {
                $ev->channel = $ev->message;
            }
            IRC::userJoin($ev->channel, $ev->nick);
        }
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerPart(ServerEvent $ev)
    {
        IRC::userPart($ev->channel, $ev->nick);
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerMode(ServerEvent $ev)
    {
        if (preg_match('/(\+|-).*(v|o)/', $ev->additionnal[0], $matches) && isset($ev->additionnal[1])) {
            if ($matches[1] == '+') {
                IRC::userModeAdd($ev->channel, $ev->additionnal[1], $matches[2]);
            } else {
                IRC::userModeRemove($ev->channel, $ev->additionnal[1], $matches[2]);
            }
        }
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerNamesReply(ServerEvent $ev)
    {
        $channel = substr($ev->additionnal[1], 1);

        if (!isset($this->names[$channel])) {
            $this->names[$channel] = array();
        }
        $this->names[$channel] = array_merge($this->names[$channel], explode(' ', $ev->message));
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerEndOfNames(ServerEvent $ev)
    {
        $channel = substr($ev->additionnal[0], 1);
        IRC::setChannelUsers($channel, $this->names[$channel]);
        unset($this->names[$channel]);
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerUserstate(ServerEvent $ev)
    {
        if (!$ev->moderator) {
            HedgeBot::message(
                "HedgeBot isn't currently a moderator. Moderator rights may be needed to perform some operations.",
                null,
                E_WARNING
            );
        }
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerMessage(ServerEvent $ev)
    {
        // Parsing the message in search for a command
        $message = explode(' ', $ev->message);
        if (strlen($message[0])) {
            if ($message[0][0] == ":") {
                $message[0] = substr($message[0], 1);
            }

            if ($message[0][0] == '!') {
                // Generating right name from command and checking it
                $cmdName = substr(array_shift($message), 1);
                $rightName = "command/" . strtolower($cmdName);

                // If the user doesn't have the right to the command, then we stop the propagation of it.
                if (!Security::hasRight($ev->nick, $rightName)) {
                    HedgeBot::message(
                        "Access denied to right 'command/$0' for user '$1'",
                        [strtolower($cmdName), $ev->nick],
                        E_DEBUG
                    );
                    $ev->stopPropagation();
                }
            }
        }
    }
}
