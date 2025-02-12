<?php

// Set the document root to the public directory
$documentRoot = __DIR__ . '/public';
$port = 8000;

echo "Starting development server on http://localhost:{$port}" . PHP_EOL;
echo "Document root: {$documentRoot}" . PHP_EOL;
echo "Press Ctrl+C to stop" . PHP_EOL;

// Start the built-in PHP development server
$command = sprintf(
    'php -S localhost:%d -t %s %s/index.php',
    $port,
    escapeshellarg($documentRoot),
    escapeshellarg($documentRoot)
);

system($command);