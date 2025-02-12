<?php

return [
    'app' => [
        'name' => 'Discogs Player',
        'environment' => 'development',
    ],
    'paths' => [
        'templates' => __DIR__ . '/../templates',
        'public' => __DIR__ . '/../public',
    ],
    'database' => [
        'path' => __DIR__ . '/../database/discogs.sqlite',
    ],
    'discogs' => [
        'api_url' => 'https://api.discogs.com',
        'user_agent' => 'DiscogsPlayer/1.0',    // User agent for API requests
    ],
    'cache' => [
        'collection_duration' => 86400, // 24 hours
    ],
]; 