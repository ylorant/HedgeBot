#!/usr/bin/env php
<?php

use HedgeBot\Documentor\Documentor;
use HedgeBot\Core\HedgeBot;

define('E_DEBUG', 32768);
include('Core/HedgeBot.class.php');
spl_autoload_register("HedgeBot\Core\HedgeBot::autoload");

$options = getopt("v", ["verbose"]);

if(isset($options['v']) || isset($options['verbose']))
	HedgeBot::$verbose = 2;

$documentor = new Documentor();
$documentor->generate();