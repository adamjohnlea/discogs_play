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
    'discogs' => [
        'api_url' => 'https://api.discogs.com',
        // Add your Discogs API configuration here
    ],
]; 