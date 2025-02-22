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
        'user_agent' => 'DiscogsPlayer/1.0',
        // OAuth settings - these should be set in environment variables
        'oauth_consumer_key' => getenv('DISCOGS_OAUTH_KEY'),
        'oauth_consumer_secret' => getenv('DISCOGS_OAUTH_SECRET'),
        'oauth_callback_url' => getenv('DISCOGS_OAUTH_CALLBACK') ?: 'https://discogs_play.test/oauth/callback'
    ],
    'cache' => [
        'collection_duration' => 86400, // 24 hours
    ],
]; 