<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Http\Request;

$app = new Application(__DIR__ . '/../');
// Configuration and routes are loaded automatically during bootstrap

$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();