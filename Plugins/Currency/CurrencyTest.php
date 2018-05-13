<?php

namespace HedgeBot\Plugins\Currency;

use HedgeBot\Plugins\TestManager\TestCase;
use HedgeBot\Core\Traits\PropertyConfigMapping;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Server;
use stdClass;

/**
 * Class CurrencyTest
 * @package HedgeBot\Plugins\Currency
 */
class CurrencyTest
{
    use PropertyConfigMapping;

    private $pluginConfig;
    private $channelConfig;
    private $testManager;

    /**
     * CurrencyTest constructor.
     */
    public function __construct()
    {
        $this->testManager = Plugin::getManager()->getPlugin('TestManager');
        $botConfig = $this->testManager->getTestedBotConfig();

        $this->pluginConfig = $botConfig->get('plugin.Currency');
        $this->channelConfig = $this->getChannelConfig();
    }

    /**
     * @param TestCase $test
     */
    public function testCurrencyGive(TestCase $test)
    {
        $testData = new stdClass();
        $testData->addedAmount = rand(0, 100);

        // Get current user amount
        $test->send($this->channelConfig->statusCommand)
            ->getReply()
            ->match('#' . $this->channelConfig->statusRegexp . '#');

        // Store the amount into a storage object
        $test->execute(
            function () use ($test, $testData) {
                $testData->currentAmount = $test->lastMatch[1];
                return true;
            }
        );

        // Add the money, then get the new total
        $test->send('!give ' . Server::getNick() . ' ' . $testData->addedAmount)
            ->send($this->channelConfig->statusCommand)
            ->getReply()
            ->match('#' . $this->channelConfig->statusRegexp . '#');

        // Check that the bot added the correct money amount
        $test->execute(
            function () use ($test, $testData) {
                return $testData->currentAmount + $testData->addedAmount == $test->lastMatch[1];
            }
        );
    }

    /**
     * @param TestCase $test
     */
    public function testCurrencyTake(TestCase $test)
    {
        $testData = new stdClass();
        $testData->removedAmount = rand(0, 100);

        // Get current user amount
        $test
            ->send($this->channelConfig->statusCommand)
            ->getReply()
            ->match('#' . $this->channelConfig->statusRegexp . '#')
            // Store the amount into a storage object
            ->execute(
                function () use ($test, $testData) {
                    $testData->currentAmount = $test->lastMatch[1];
                    return true;
                }
            )
            // Add the money, then get the new total
            ->send('!take ' . Server::getNick() . ' ' . $testData->removedAmount)
            ->send($this->channelConfig->statusCommand)
            ->getReply()
            ->match('#' . $this->channelConfig->statusRegexp . '#')
            // Check that the bot added the correct money amount
            ->execute(
                function () use ($test, $testData) {
                    return $testData->currentAmount - $testData->removedAmount == $test->lastMatch[1];
                }
            );
    }

    /**
     * @return stdClass
     */
    private function getChannelConfig()
    {
        $currentChannel = $this->testManager->getChannel();
        $config = new stdClass();

        // Getting settings from the configuration
        $config->statusCommand = '!' . $this->getParameterFromData(
            $this->pluginConfig,
            $currentChannel,
                'statusCommand',
            Currency::DEFAULT_STATUS_COMMAND
        );
        $config->currencyName = $this->getParameterFromData(
            $this->pluginConfig,
            $currentChannel,
            'currencyName',
            Currency::DEFAULT_CURRENCY_NAME
        );
        $config->currencyNamePlural = $this->getParameterFromData(
            $this->pluginConfig,
            $currentChannel,
            'currencyNamePlural',
            Currency::DEFAULT_CURRENCY_NAME_PLURAL
        );
        $msgRegexp = $this->getParameterFromData(
            $this->pluginConfig,
            $currentChannel,
            'statusMessage',
            Currency::DEFAULT_STATUS_MESSAGE
        );

        // Setting status message regexp
        $msgRegexp = str_replace(
            array('@name', '@total', '@currency'),
            array(Server::getNick(), '([0-9]+)', join('|', [$config->currencyName, $config->currencyNamePlural])),
            preg_quote($msgRegexp, '#')
        );

        $config->statusRegexp = $msgRegexp;
        $config->currencyName = $currency[0];
        $config->currencyNamePlural = $currency[1];
        $config->statusCommand = '!' . $statusCommand;

        return $config;
    }
}
