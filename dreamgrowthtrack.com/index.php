<?php
declare(strict_types=1);

$lp = is_dir(dirname(__DIR__) . '/launchpad') ? dirname(__DIR__) . '/launchpad' : __DIR__ . '/../launchpad';
require $lp . '/src/bootstrap.php';
require LP_SRC . '/controllers.php';
app_main();
