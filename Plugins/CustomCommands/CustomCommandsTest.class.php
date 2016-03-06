<?php
namespace HedgeBot\Plugins\CustomCommands;

use HedgeBot\Plugins\TestManager\TestCase;
use stdClass;

class CustomCommandsTest
{
    private $testData;

    const COMMAND_CHARS = "abcdefghijklmnopqrstuvwxyz0123456789";
    const MESSAGE_CHARS = "abcdefghijklmnopqrstuvwxyz0123456789 -_*$%,;:!/.?&\"'()[]{}=~#|";

    public function __construct()
    {
        $this->testData = new stdClass();

        $randomCommand = "!";
        $rand = rand(8, 16);
        for($i = 0; $i < $rand; $i++)
            $randomCommand .= self::COMMAND_CHARS[rand(0, strlen(self::COMMAND_CHARS) - 1)];

        $randomMessage = "";
        $rand = rand(32, 64);
        for($i = 0; $i < $rand; $i++)
            $randomMessage .= self::MESSAGE_CHARS[rand(0, strlen(self::MESSAGE_CHARS) - 1)];

        $this->testData->randomCommand = $randomCommand;
        $this->testData->randomMessage = $randomMessage;
    }

    public function testCreateCommand(TestCase $test)
    {
        $test->send('!addcommand '. $this->testData->randomCommand. ' '. $this->testData->randomMessage)
             ->getReply()
             ->match('#New message for command '. $this->testData->randomCommand. ' registered\.#')
             ->send($this->testData->randomCommand)
             ->getReply()
             ->match('#'. preg_quote($this->testData->randomMessage, "#"). '#');
    }

    // Will always succeed, check visually
    public function testRemoveCommand(TestCase $test)
    {
        $test->send('!rmcommand '. $this->testData->randomCommand)
             ->send($this->testData->randomCommand)
             ->getReply();
    }
}
