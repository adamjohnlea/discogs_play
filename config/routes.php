<?php

return [
    // Home page (collection view)
    '/' => ['ReleaseController', 'showCollection'],
    
    // Single release view - support both URL formats
    '/release/:release_id' => ['ReleaseController', 'showRelease'],
    '/' => ['ReleaseController', 'handleRequest'], // This will handle both collection and release views based on query parameters
    
    // Add more routes here as needed
]; 