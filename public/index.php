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
} else {
    // For backward compatibility, handle array responses
    if (isset($result['releaseInfo'])) {
        $releaseinfo = $result['releaseInfo'];
        $myreleaseinfo = $result['myReleaseInfo'];
    } else {
        $collection = $result;
    }

    // Include views (legacy support)
    include __DIR__ . '/../views/header.php';
    include __DIR__ . '/../views/top_banner.php';
    include __DIR__ . '/../views/top_nav_filter_bar.php';
    include __DIR__ . '/../views/release_gallery.php';
    include __DIR__ . '/../views/footer.php';
} 