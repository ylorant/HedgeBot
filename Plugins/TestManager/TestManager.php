<?php

namespace HedgeBot\Plugins\TestManager;

use SplFileInfo;
use ReflectionClass;
use ReflectionMethod;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\API\Server;
use HedgeBot\Core\Data\FileProvider;
use HedgeBot\Core\Plugins\PluginManager;
use HedgeBot\Core\Events\ServerEvent;
use HedgeBot\Core\Events\CommandEvent;

/**
 * Class TestManager
 * @package HedgeBot\Plugins\TestManager
 */
class TestManager extends PluginBase
{
    private $testedPlugins;
    private $logfile;
    private $botName;
    private $manager; //< Plugin manager reference.
    private $currentTest;
    private $testQueue;
    private $currentTestObject;
    private $testChannel;
    private $startTime;
    private $testStats;
    private $testedBotConfig;

    /**
     * @return bool
     */
    public function init()
    {
        $this->testedPlugins = [];
        $this->manager = Plugin::getManager();

        // Opening log if necessary
        if (!empty($this->config['logfile'])) {
            $fileInfo = new SplFileInfo($this->config['logfile']);
            $this->logfile = $fileInfo->openFile('a+');
        }

        // Getting tested bot name
        if (!empty($this->config['botName'])) {
            $this->botName = strtolower($this->config['botName']);
        } else {
            HedgeBot::message("There isn't any bot name defined for tests.", null, E_ERROR);
            return false;
        }

        // Loading tested bot config
        if (!empty($this->config['botConfig'])) {
            $this->botConfig = HedgeBot::getInstance()->loadStorage(
                $this->testedBotConfig,
                (object)$this->config['botConfig']
            );
            if ($this->botConfig === false) {
                HedgeBot::message("Cannot load tested bot configuration.", null, E_ERROR);
                return false;
            }
        }

        Plugin::getManager()->addRoutine($this, 'RoutineProcessQueue');
    }

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->testChannel;
    }

    /**
     * This routine checks the status of the current test to unlock it when it's waiting for too long or on purpose.
     */
    public function RoutineProcessQueue()
    {
        if (!empty($this->currentTest)) {
            $this->processTestQueue();
        }
    }

    /**
     * @param ServerEvent $ev
     */
    public function ServerPrivmsg(ServerEvent $ev)
    {
        if ($ev->channel == $this->testChannel && $ev->nick == $this->botName && !empty($this->currentTest)) {
            $this->currentTest->pushMessage($ev->message);
        }
    }

    /**
     * @param ServerEvent $ev
     * @throws \ReflectionException
     */
    public function ServerJoin(ServerEvent $ev)
    {
        // Autostart when first joining the channel if the config option is set
        if ($ev->nick == Server::getNick()) {
            if (!empty($this->config['autostart']) && HedgeBot::parseRBool($this->config['autostart'])) {
                $this->CommandExecTests($ev);
            }
        }
    }

    /**
     * @param CommandEvent $ev
     * @throws \ReflectionException
     */
    public function CommandExecTests(CommandEvent $ev)
    {
        $this->testChannel = $ev->channel;
        $this->testStats = ["successes" => 0, "failures" => 0];
        $this->buildTests();
        $this->execTests();
    }

    /**
     *
     */
    public function TimeoutTestProcess()
    {
        $this->processTestQueue();
    }

    /**
     * @return mixed
     */
    public function getTestedBotConfig()
    {
        return $this->testedBotConfig;
    }

    /**
     * @throws \ReflectionException
     */
    protected function buildTests()
    {
        $this->testQueue = [];
        $this->currentTestObject = null;

        $testedPlugins = explode(',', $this->config['testedPlugins']);

        foreach ($testedPlugins as $pluginName) {
            $pluginName = trim($pluginName);

            // Load plugin config with a FileProvider
            $config = $this->manager->getPluginDefinition($pluginName);

            if (!empty($config->pluginDefinition->testClass)) {
                $testClassName = PluginManager::PLUGINS_NAMESPACE . $pluginName . "\\"
                    . $config->pluginDefinition->testClass;
                $this->currentTestObject = new $testClassName();

                $reflectionClass = new ReflectionClass($this->currentTestObject);
                $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

                foreach ($methods as $method) {
                    // Valid test method names are those who begin by "test"
                    if (strpos($method->name, 'test') === 0) {
                        $testCase = $this->testQueue[$testClassName . '::' . $method->name] = new TestCase($this);
                        $testCase->init($method);
                        $this->currentTestObject->{$method->name}($testCase);
                    }
                }
            }
        }
    }

    /**
     *
     */
    protected function execTests()
    {
        $this->startTime = microtime(true);

        $this->log('');
        $this->log('--------------------------');
        $this->log('Starting executing tests at: ' . date('r'));
        $this->log('Tests to process: ' . count($this->testQueue));
        $this->log('');

        $this->processTestQueue();
    }

    /**
     *
     */
    protected function processTestQueue()
    {
        if (empty($this->currentTest) && !empty($this->testQueue)) {
            $this->currentTest = array_shift($this->testQueue);
            $this->log($this->currentTest->testName . ': ', false);
        } elseif (empty($this->currentTest)) {
            return $this->finishTests();
        }

        do {
            $this->currentTest->executeStep();
        } while ($this->currentTest->status == TestCase::STATUS_IDLE);

        if (in_array($this->currentTest->status, array(TestCase::STATUS_FAILED, TestCase::STATUS_SUCCESS))) {
            if ($this->currentTest->status == TestCase::STATUS_FAILED) {
                $this->testStats['failures']++;
                $this->log('FAIL');
            } elseif ($this->currentTest->status == TestCase::STATUS_SUCCESS) {
                $this->testStats['successes']++;
                $this->log('OK');
            }

            // Now that the test is finished, time to discard it.
            $this->currentTest = null;

            // Check for tests end and report if necessary
            if (empty($this->testQueue)) {
                $this->finishTests();
            } else {
                Plugin::getManager()->setTimeout(1, 'testprocess', "processTestQueue");
            }
        }
    }

    /**
     *
     */
    protected function finishTests()
    {
        $successRate = 0;
        if ($this->testStats['failures'] > 0) {
            $successRate = round(($this->testStats['successes'] / $this->testStats['failures'] * 100), 2);
        } else {
            $successRate = 100;
        }

        $this->log('');
        $this->log('Tests completed.') .
        $this->log('Successes: ' . $this->testStats['successes']. '. Failures: ' . $this->testStats['failures'] . '.');
        $this->log('Success rate: ' . $successRate . '%');
        $this->log('Total test time: ' . round(microtime(true) - $this->startTime, 4) . 's');

        if (!empty($this->config['autostop']) && HedgeBot::parseRBool($this->config['autostop'])) {
            HedgeBot::getInstance()->stop();
        }
    }

    /**
     * @param $message
     * @param bool $crlf
     */
    protected function log($message, $crlf = true)
    {
        if (!empty($this->logfile)) {
            $this->logfile->fwrite($message . ($crlf ? PHP_EOL : ""));
        }
    }
}
