<?php

namespace HedgeBot\Core\Server;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;

class ServerInstance
{
    private $IRC;
    private $pluginManager;
    private $config;
    private $name;
    private $connected;

    public function __construct()
    {
        $this->IRC = new IRCConnection(); // Init IRC connection handler
        $this->pluginManager = Plugin::getManager();
    }

    public function connect()
    {
        $this->connected = $this->IRC->connect($this->config['address'], $this->config['port']);

        if (!$this->connected) {
            return false;
        }

        if (!empty($this->config['password'])) {
            $this->IRC->setPassword($this->config['password']);
        }

        $this->IRC->setNick($this->config['name'], $this->config['name']);

        // Send Twitch specific commands
        $this->IRC->capabilityRequest("twitch.tv/commands");
        $this->IRC->capabilityRequest("twitch.tv/tags");
        $this->IRC->capabilityRequest("twitch.tv/membership");

        if (isset($this->config['floodLimit']) && HedgeBot::parseRBool($this->config['floodLimit'])) {
            $this->IRC->setFloodLimit($this->config['floodLimit']);
        }
    }

    public function disconnect()
    {
        $this->IRC->send("QUIT: Quitting...");
        $this->IRC->disconnect();
    }

    public function reconnect()
    {
        $this->disconnect();
        $this->connect();
    }

    public function isConnected()
    {
        return (bool)$this->connected;
    }

    public function load($config)
    {
        $this->config = $config;
        $this->name = HedgeBot::getServerName($this);

        $this->connect();

        if (!$this->connected) {
            return false;
        }

        $this->config['channels'] = trim($this->config['channels']);

        return true;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getNick()
    {
        return $this->config['name'];
    }

    public function getIRC()
    {
        return $this->IRC;
    }

    public function step($data = null)
    {
        if ($data == null) {
            $data = $this->IRC->read();
        }

        foreach ($data as $command) {
            if (HedgeBot::$verbose >= 2) {
                echo '<-[' . Server::getName() . '] ' . $command . "\n";
            }

            $command = $this->IRC->parseMsg($command);

            // Force channel for whispers if there is only one configured for this server
            if ($command['command'] == 'WHISPER' && strpos(',', $this->config['channels']) === false) {
                $command['channel'] = $this->config['channels'];
            }

            $this->pluginManager->callEvent(new CoreEvent('serverMessage', ['command' => $command]));

            // Calling the server event, and keeping a reference to allow propagation stopping
            $serverEvent = new ServerEvent($command);
            $this->pluginManager->callEvent($serverEvent);

            // Command handling, only if event is still being propagated
            if ($serverEvent->propagation && in_array($command['command'], array('PRIVMSG', 'NOTICE', 'WHISPER'))) {
                $message = explode(' ', $command['message']);
                if (strlen($message[0])) {
                    if ($message[0][0] == ":") {
                        $message[0] = substr($message[0], 1);
                    }

                    $this->pluginManager->callRegexEvents($command, $command['message']);

                    switch ($message[0][0]) {
                        case '!': //Command
                            $cmdName = substr(array_shift($message), 1);
                            $cmdName = strtolower($cmdName);
                            HedgeBot::message('Command catched: !$0', array($cmdName), E_DEBUG);
                            $this->pluginManager->callEvent(new CommandEvent($cmdName, $message, $command));
                            break;
                        case "\x01": //CTCP
                            $cmdName = substr(array_shift($message), 1);
                            HedgeBot::message('CTCP Command catched: CTCP$0', array($cmdName), E_DEBUG);
                            $this->pluginManager->callEvent(new CommandEvent('CTCP' . $cmdName, $message, $command));
                            break;
                    }
                }
            }
        }
        $this->IRC->processBuffer();
    }
}
