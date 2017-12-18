#!/usr/bin/env php
<?php

use HedgeBot\Core\HedgeBot;

include('Core/HedgeBot.php');

spl_autoload_register("HedgeBot\Core\HedgeBot::autoload");

$hedgebot = new HedgeBot();
$initialized = $hedgebot->init();

if($initialized)
    $hedgebot->run();
