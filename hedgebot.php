#!/usr/bin/env php
<?php

use HedgeBot\Core\HedgeBot;

include('vendor/autoload.php');
include('Core/HedgeBot.php');

spl_autoload_register("HedgeBot\Core\HedgeBot::autoload");

const ENV = "main";

$hedgebot = new HedgeBot();
$initialized = $hedgebot->init();

if ($initialized) {
    $hedgebot->run();
}
