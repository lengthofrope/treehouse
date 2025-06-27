<?php

use App\Controllers\HomeController;

// Define your routes here
$router->get('/', [HomeController::class, 'index']);
$router->get('/about', [HomeController::class, 'about']);