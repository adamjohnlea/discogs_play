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
    
    // OAuth routes
    $router->add('/oauth/start', ['OAuthController', 'start']);
    $router->add('/oauth/callback', ['OAuthController', 'callback']);
    
    // Profile routes
    $router->add('/profile', ['ProfileController', 'show']);
    $router->add('/profile/password', ['ProfileController', 'updatePassword'], 'POST');
    $router->add('/profile/email', ['ProfileController', 'updateEmail'], 'POST');
    $router->add('/profile/delete', ['ProfileController', 'deleteAccount'], 'POST');
    
    // Settings routes
    $router->add('/settings', ['SettingsController', 'show']);
    $router->add('/refresh-collection', ['SettingsController', 'refreshCollection'], 'POST');

    // Collection routes
    $router->add('/collection', ['CollectionController', 'index']);
    $router->add('/collection/search', ['CollectionController', 'search']);
    
    // Release routes
    $router->add('/release/:id/:artist/:title', ['ReleaseController', 'show']);
    
    // Wantlist routes
    $router->add('/wantlist', ['WantlistController', 'index']);
    $router->add('/wantlist/:id/:artist/:title', ['WantlistController', 'show']);
    
    // Root path
    $router->add('/', ['HomeController', 'index']);
}; 