#!/usr/bin/env php
<?php

use HedgeBot\Core\HedgeBot;

include('vendor/autoload.php');
include('Core/HedgeBot.php');

spl_autoload_register("HedgeBot\Core\HedgeBot::autoload");

const ENV = "main";

$hedgebot = new HedgeBot();

HedgeBot::$verbose = 1;
HedgeBot::message("Starting HedgeBot through this file is deprecated and will be removed", [], E_USER_WARNING);
HedgeBot::message("in a future version. Use `php console bot:start` instead.", [], E_USER_WARNING);

$options = $hedgebot->parseCLIOptions();

if (isset($options['verbose']) || isset($options['v'])) {
    HedgeBot::$verbose = 2;
}

$configDir = HedgeBot::DEFAULT_CONFIG_DIR;
if (isset($options['config']) || isset($options['c'])) {
    $configDir = !empty($options['config']) ? $options['config'] : $options['c'];
}

$initialized = $hedgebot->init();

if ($initialized) {
    $hedgebot->run();
}
