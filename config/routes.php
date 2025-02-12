<?php

return function($router) {
    // Release view - support both formats
    $router->add('/release/:id/:artist/:title', ['ReleaseController', 'showRelease']);
    $router->add('/release/:id', ['ReleaseController', 'showRelease']);
    
    // Collection view with clean URLs for folder, sorting, and pagination
    $router->add('/folder/:folder/sort/:field/:direction/page/:page', ['ReleaseController', 'showCollection']);
    
    // Simpler variations of collection view
    $router->add('/folder/:folder/page/:page', ['ReleaseController', 'showCollection']);
    $router->add('/folder/:folder/sort/:field/:direction', ['ReleaseController', 'showCollection']);
    $router->add('/folder/:folder', ['ReleaseController', 'showCollection']);
    
    // Root path - defaults to collection view
    $router->add('/', ['ReleaseController', 'showCollection']);
}; 