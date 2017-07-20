<?php
namespace HedgeBot\Plugins\Quotes;

use HedgeBot\Plugins\TestManager\TestCase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\HedgeBot;
use stdClass;

class QuotesTest
{
    private $testData;
    
    const QUOTE_CHARS = "abcdefghijklmnopqrstuvwxyz0123456789 -_*$%,;:!/.?&\"'()[]{}=~#|";
    
    public function __construct()
    {
        $this->testData = new stdClass();
        
        $randomQuote = "";
        $rand = rand(32, 64);
        for($i = 0; $i < $rand; $i++)
            $randomQuote .= self::QUOTE_CHARS[rand(0, strlen(self::QUOTE_CHARS) - 1)];
        
        $this->testData->randomQuote = $randomQuote;
    }
    
    public function testAddQuote(TestCase $test)
    {
        $testData = $this->testData;
        
        $test
            ->send('!addquote '. $this->testData->randomQuote)
            ->getReply()
            ->match('#Quote #([0-9]+) added.#')
            
            // Save quote index for later use
            ->execute(
                function() use ($test, $testData)
                {
                    $testData->quoteIndex = $test->lastMatch[1];
                    return true;
                }
            );
    }
    
    public function testQuote(TestCase $test)
    {
        $test
            ->send('!quote '. $this->testData->quoteIndex);
    }
}