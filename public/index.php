<?php

// Bootstrap the application
require_once __DIR__ . '/../src/bootstrap.php';

// Initialize router
$router = new Router($config);

// Load routes
$routes = require __DIR__ . '/../config/routes.php';
foreach ($routes as $path => $callback) {
    $router->add($path, $callback);
}

// Dispatch the request
$requestUri = $_SERVER['REQUEST_URI'];
$result = $router->dispatch($requestUri);

// Handle the result
if (isset($result['error'])) {
    // Handle error (404, etc)
    echo TwigConfig::getInstance($config)->render('error.html.twig', [
        'error' => $result['error']
    ]);
    exit;
}

// If the result is a string (Twig template output), echo it directly
if (is_string($result)) {
    echo $result;
}