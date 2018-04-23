<?php

namespace HedgeBot\Plugins\TestManager;

use ReflectionMethod;
use HedgeBot\Core\API\IRC;

class TestCase
{
    private $steps = [];
    private $currentStep = 0;
    private $messageStack = [];
    private $testInfo = [];
    private $manager = null;

    public $lastActionTime = null;
    public $lastMatch = null;
    public $status = self::STATUS_IDLE;
    public $testName = '';

    const ALLOWED_STEPS = ['send', 'execute', 'wait', 'getReply', 'match'];

    const STATUS_IDLE = 0;
    const STATUS_WAITREPLY = 1;
    const STATUS_WAIT = 2;
    const STATUS_FAILED = 3;
    const STATUS_SUCCESS = 4;

    public function __construct(TestManager $manager)
    {
        $this->manager = $manager;
    }

    /** Initializes the test case.
     * This method initializes the test case, giving it the reflection method for it to get various info.
     *
     * \param $method The ReflectionMethod instance needed to get info for the test case.
     */
    public function init(ReflectionMethod $method)
    {
        $this->testInfo = [];
        $this->testName = $method->name;
        $this->lastActionTime = time();
    }

    public function addStep($stepName, $parameters)
    {
        if (in_array($stepName, self::ALLOWED_STEPS)) {
            $this->steps[] = [
                "name" => $stepName,
                "params" => $parameters
            ];
        }
    }

    /**
     * Pushes a message from the tested bot on the stack.
     *
     * \param $message The message to push.
     */
    public function pushMessage($message)
    {
        $this->messageStack[] = $message;

        if ($this->status == self::STATUS_WAITREPLY) {
            $this->status = self::STATUS_IDLE;
            $this->currentStep++;
        }
    }

    public function executeStep()
    {
        if (!empty($this->steps[$this->currentStep])) {
            $step = $this->steps[$this->currentStep];

            switch ($step['name']) {
                case 'send':
                    IRC::message($this->manager->getChannel(), $step['params'][0]);
                    break;

                case 'execute':
                    $res = $step['params'][0]();
                    if ($res === false) {
                        $this->status = self::STATUS_FAILED;
                    }
                    break;

                case 'getReply':
                    $this->status = self::STATUS_WAITREPLY;

                    if ($this->lastActionTime + 15 <= time()) // Handle timeout for message wait
                    {
                        $this->status = self::STATUS_FAILED;
                    } else {
                        if (!empty($this->messageStack)) // We received a message ?
                        {
                            $this->status = self::STATUS_IDLE;
                        }
                    }
                    break;

                case 'wait':
                    if ($this->lastActionTime + $step['params'][0] <= time()) {
                        $this->status = self::STATUS_IDLE;
                    } else {
                        $this->status = self::STATUS_WAIT;
                    }
                    break;

                case 'match':
                    $regexp = $step['params'][0];

                    $found = false;
                    $i = 0;
                    $matches = null;
                    foreach ($this->messageStack as $i => $message) {
                        if (preg_match($regexp, $message, $matches)) {
                            $found = true;
                            $this->lastMatch = $matches;
                            break;
                        }
                    }

                    if ($found) {
                        array_splice($this->messageStack, $i, 1);
                    } else {
                        $this->status = self::STATUS_FAILED;
                    }
                    break;
            }
        }

        if (!in_array($this->status, array(self::STATUS_WAITREPLY, self::STATUS_WAIT))) {
            $this->currentStep++;
            $this->lastActionTime = time();
        }

        if ($this->currentStep >= count($this->steps) && $this->status != self::STATUS_FAILED) {
            $this->status = self::STATUS_SUCCESS;
        }
    }

    /**
     * Implements various test method calls.
     *
     * \param $name The function that is called.
     * \param $args Array of arguments passed to said function.
     *
     * \return self, to allow chaining.
     */
    public function __call($name, $args)
    {
        $this->addStep($name, $args);

        return $this;
    }
}
