<?php

return [
    'app' => [
        'name' => 'Discogs Player',
        'environment' => 'development', // or 'production'
    ],
    'paths' => [
        'templates' => __DIR__ . '/../templates',
        'public' => __DIR__ . '/../public',
    ],
    'database' => [
        'path' => __DIR__ . '/../database/discogs.sqlite',
        'cache' => [
            'release_ttl' => 86400,    // 24 hours
            'image_ttl' => 2592000,    // 30 days
        ],
    ],
    'discogs' => [
        'api_url' => 'https://api.discogs.com',
        'username' => 'YOUR_DISCOGS_USERNAME',
        'token' => 'YOUR_DISCOGS_API_TOKEN', // Get this from https://www.discogs.com/settings/developers
    ],
]; 