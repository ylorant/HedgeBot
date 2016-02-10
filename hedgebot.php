<?php

use HedgeBot\Core\HedgeBot;

define('E_DEBUG', 32768);

include('Core/HedgeBot.class.php');

spl_autoload_register("HedgeBot\Core\HedgeBot::autoload");

$hedgebot = new HedgeBot();
$initialized = $hedgebot->init();

if($initialized)
    $hedgebot->run();
