<?php
namespace HedgeBot\Plugins\Currency;

use HedgeBot\Plugins\TestManager\TestCase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\HedgeBot;
use stdClass;

class CurrencyTest
{
    private $pluginConfig;
    private $channelConfig;
    private $testManager;

    public function __construct()
    {
        $this->testManager = Plugin::getManager()->getPlugin('TestManager');
        $botConfig = $this->testManager->getTestedBotConfig();

        $this->pluginConfig = $botConfig->get('plugin.Currency');
        $this->channelConfig = $this->getChannelConfig();
    }

    public function testCurrencyGive(TestCase $test)
    {
        $testData = new stdClass();
        $testData->addedAmount = rand(0, 100);

        // Get current user amount
        $test->send($this->channelConfig->statusCommand)
             ->getReply()
             ->match('#'. $this->channelConfig->statusRegexp. '#');

        // Store the amount into a storage object
        $test->execute(
            function() use ($test, $testData)
            {
                $testData->currentAmount = $test->lastMatch[1];
                return true;
            }
        );

        // Add the money, then get the new total
        $test->send('!give '. Server::getNick(). ' '. $testData->addedAmount)
             ->send($this->channelConfig->statusCommand)
             ->getReply()
             ->match('#'. $this->channelConfig->statusRegexp. '#');

        // Check that the bot added the correct money amount
        $test->execute(
            function() use ($test, $testData)
            {
                return $testData->currentAmount + $testData->addedAmount == $test->lastMatch[1];
            }
        );
    }

    public function testCurrencyTake(TestCase $test)
    {
        $testData = new stdClass();
        $testData->removedAmount = rand(0, 100);

        // Get current user amount
        $test
            ->send($this->channelConfig->statusCommand)
            ->getReply()
            ->match('#'. $this->channelConfig->statusRegexp. '#')

            // Store the amount into a storage object
            ->execute(
                function() use ($test, $testData)
                {
                    $testData->currentAmount = $test->lastMatch[1];
                    return true;
                }
            )

            // Add the money, then get the new total
            ->send('!take '. Server::getNick(). ' '. $testData->removedAmount)
            ->send($this->channelConfig->statusCommand)
            ->getReply()
            ->match('#'. $this->channelConfig->statusRegexp. '#')

            // Check that the bot added the correct money amount
            ->execute(
                function() use ($test, $testData)
                {
                    return $testData->currentAmount - $testData->removedAmount == $test->lastMatch[1];
                }
            );
    }

    private function getChannelConfig()
    {
        $currentChannel = $this->testManager->getChannel();
        $config = new stdClass();
        $currency = array(null, null);
        $statusCommand = null;

        // Getting status command from the configuration
        if(isset($this->pluginConfig['channel'][$currentChannel]['statusCommand']))
            $statusCommand = $this->pluginConfig['channel'][$currentChannel]['statusCommand'];
        elseif(isset($this->pluginConfig['statusCommand']))
            $statusCommand = $this->pluginConfig['statusCommand'];
        else
            $statusCommand = Currency::DEFAULT_STATUS_COMMAND;

        // Getting currency name from the configuration
        if(isset($this->pluginConfig['channel'][$currentChannel]['currencyName']))
            $currency[0] = $this->pluginConfig['channel'][$currentChannel]['currencyName'];
        elseif(isset($this->pluginConfig['currencyName']))
            $currency[0] = $this->pluginConfig['currencyName'];
        else
            $currency[0] = Currency::DEFAULT_CURRENCY_NAME;

        // Getting currency plural name from the configuration
        if(isset($this->pluginConfig['channel'][$currentChannel]['currencyNamePlural']))
            $currency[1] = $this->pluginConfig['channel'][$currentChannel]['currencyNamePlural'];
        elseif(isset($this->pluginConfig['currencyNamePlural']))
            $currency[1] = $this->pluginConfig['currencyNamePlural'];
        else
            $currency[1] = Currency::DEFAULT_CURRENCY_NAME_PLURAL;

        // Get status message from the configuration
        if(isset($this->pluginConfig['channel'][$currentChannel]['statusMessage']))
            $msgRegexp = $this->pluginConfig['channel'][$currentChannel]['statusMessage'];
        elseif(isset($this->pluginConfig['statusMessage']))
            $msgRegexp = $this->pluginConfig['statusMessage'];
        else
            $msgRegexp = Currency::DEFAULT_STATUS_MESSAGE;

        // Setting status message regexp
        $msgRegexp = str_replace(array('@name', '@total', '@currency'),
                                 array(Server::getNick(), '([0-9]+)', join('|', $currency)),
                                 preg_quote($msgRegexp, '#'));

        $config->statusRegexp = $msgRegexp;
        $config->currencyName = $currency[0];
        $config->currencyNamePlural = $currency[1];
        $config->statusCommand = '!'. $statusCommand;

        return $config;
    }
}
