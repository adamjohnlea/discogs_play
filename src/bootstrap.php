<?php

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Include core files
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/TwigConfig.php';
require_once __DIR__ . '/Controllers/ReleaseController.php';

// Include utility functions
require_once __DIR__ . '/Utils/utils.php';
require_once __DIR__ . '/Functions/funcs.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 