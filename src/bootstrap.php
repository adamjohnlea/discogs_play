<?php

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Include database services
require_once __DIR__ . '/Database/Migration.php';
require_once __DIR__ . '/Services/DatabaseService.php';
require_once __DIR__ . '/Services/CacheService.php';

// Include middleware
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

// Include core files
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/TwigConfig.php';

// Include controllers
require_once __DIR__ . '/Controllers/ReleaseController.php';
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/HomeController.php';
require_once __DIR__ . '/Controllers/SettingsController.php';
require_once __DIR__ . '/Controllers/ProfileController.php';
require_once __DIR__ . '/Controllers/OAuthController.php';
require_once __DIR__ . '/Controllers/WantlistController.php';

// Include utility functions
require_once __DIR__ . '/Utils/utils.php';
require_once __DIR__ . '/Functions/funcs.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 