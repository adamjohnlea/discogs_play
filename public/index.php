<?php

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