<?php

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $envLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        list($name, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($name), trim($value)));
    }
}

// Bootstrap the application
require_once __DIR__ . '/../src/bootstrap.php';

// Initialize router
$router = new Router($config);

// Load routes
$routes = require __DIR__ . '/../config/routes.php';
$routes($router);

// Initialize auth middleware
$authMiddleware = new AuthMiddleware($config);

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];

// Run auth middleware
$authMiddleware->handle(parse_url($requestUri, PHP_URL_PATH));

// Dispatch the request
$router->dispatch($requestUri);