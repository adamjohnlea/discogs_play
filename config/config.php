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
        'cache' => [
            'release_ttl' => 86400,    // 24 hours
            'image_ttl' => 2592000,    // 30 days
        ],
    ],
    'discogs' => [
        'api_url' => 'https://api.discogs.com',
        'username' => 'aj_vinyl_and_music',  // Replace with your Discogs username
        'token' => 'TMAQAtqQwNUhdEUKmoYBKkakNqKFdqLuMUfZFyAE',    // Replace with your Discogs API token
        'user_agent' => 'DiscogsPlayer/1.0',    // User agent for API requests
    ],
]; 