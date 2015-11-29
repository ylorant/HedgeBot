<?php

use HedgeBot\Core\HedgeBot;

define('E_DEBUG', 32768);

// Setting default timezone starting from
date_default_timezone_set(date_default_timezone_get());

include('Core/HedgeBot.class.php');

spl_autoload_register("HedgeBot\Core\HedgeBot::autoload");

$hedgebot = new HedgeBot();
$initialized = $hedgebot->init();

if($initialized)
    $hedgebot->run();
