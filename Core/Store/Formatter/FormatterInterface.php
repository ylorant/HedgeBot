<?php

namespace HedgeBot\Core\Store\Formatter;

interface FormatterInterface
{
    /**
     * Implementing method should use this method to return the string name of the formatter,
     * to allow implementing classes to fetch it by name.
     */
    public static function getName();
}