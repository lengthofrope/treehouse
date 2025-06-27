<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| This file is used to bootstrap the testing environment for the TreeHouse
| framework. It sets up autoloading and any necessary configuration.
|
*/

// Require the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set timezone for consistent testing
date_default_timezone_set('UTC');

// Set error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define testing constants
if (!defined('TESTING')) {
    define('TESTING', true);
}

if (!defined('TEST_ROOT')) {
    define('TEST_ROOT', __DIR__);
}

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}