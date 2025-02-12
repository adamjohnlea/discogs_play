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
    'discogs' => [
        'api_url' => 'https://api.discogs.com',
        'username' => 'YOUR_DISCOGS_USERNAME',
        'token' => 'YOUR_DISCOGS_API_TOKEN', // Get this from https://www.discogs.com/settings/developers
    ],
]; 