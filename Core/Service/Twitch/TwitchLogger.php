<?php
namespace HedgeBot\Core\Service\Twitch;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use HedgeBot\Core\HedgeBot;


class TwitchLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        $logLevels = [
            LogLevel::EMERGENCY => E_ERROR, 
            LogLevel::ERROR => E_ERROR,
            LogLevel::ALERT => E_ERROR,
            LogLevel::CRITICAL => E_ERROR,
            LogLevel::WARNING => E_WARNING,
            LogLevel::INFO => E_NOTICE,
            LogLevel::NOTICE => E_NOTICE,
            LogLevel::DEBUG => E_DEBUG
        ];

        $level = str_replace(array_keys($logLevels), array_values($logLevels), $level);
        HedgeBot::message($message, $context, $level);
    }
}