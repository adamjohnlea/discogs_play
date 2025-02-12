<?php

return function($router) {
    // Authentication routes
    $router->add('/register', ['AuthController', 'showRegistrationForm']);
    $router->add('/login', ['AuthController', 'showLoginForm']);
    $router->add('/logout', ['AuthController', 'logout']);
    $router->add('/force-logout', ['AuthController', 'forceLogout']); // Development only route
    
    // POST routes for form submissions
    $router->add('/register', ['AuthController', 'register'], 'POST');
    $router->add('/login', ['AuthController', 'login'], 'POST');
    
    // Settings routes
    $router->add('/settings', ['SettingsController', 'show']);
    $router->add('/settings', ['SettingsController', 'update'], 'POST');
    $router->add('/refresh-collection', ['SettingsController', 'refreshCollection'], 'POST');
    
    // Release view - support both formats
    $router->add('/release/:id/:artist/:title', ['ReleaseController', 'showRelease']);
    $router->add('/release/:id', ['ReleaseController', 'showRelease']);
    
    // Collection view with clean URLs for folder, sorting, and pagination
    $router->add('/folder/:folder/sort/:field/:direction/page/:page', ['ReleaseController', 'showCollection']);
    
    // Simpler variations of collection view
    $router->add('/folder/:folder/page/:page', ['ReleaseController', 'showCollection']);
    $router->add('/folder/:folder/sort/:field/:direction', ['ReleaseController', 'showCollection']);
    $router->add('/folder/:folder', ['ReleaseController', 'showCollection']);
    
    // Root path - now uses HomeController
    $router->add('/', ['HomeController', 'index']);
}; 