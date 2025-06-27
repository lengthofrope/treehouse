<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Http\Request;

$app = new Application(__DIR__ . '/../');

$app->loadConfiguration(__DIR__ . '/../config');
// Routes are loaded automatically during bootstrap

$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();