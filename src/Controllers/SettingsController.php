<?php

class SettingsController {
    private $twig;
    private $config;
    private $authService;
    private $db;
    private $oauthService;
    private $logger;
    
    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/OAuthService.php';
        require_once __DIR__ . '/../Services/LogService.php';
        $this->authService = new AuthService($config);
        $this->oauthService = new OAuthService($config);
        $this->db = DatabaseService::getInstance($config)->getConnection();
        $this->logger = LogService::getInstance($config);
    }
    
    public function show() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        // Get user settings including OAuth status
        $settings = $this->getUserSettings();
        
        // Get any flash messages
        $success = $_SESSION['settings_success'] ?? null;
        $error = $_SESSION['settings_error'] ?? null;
        
        // Clear flash messages
        unset($_SESSION['settings_success'], $_SESSION['settings_error']);
        
        echo $this->twig->render('settings.html.twig', [
            'settings' => $settings,
            'success' => $success,
            'error' => $error
        ]);
    }
    
    private function getUserSettings() {
        $stmt = $this->db->prepare("
            SELECT discogs_username, oauth_access_token, oauth_access_token_secret, oauth_token_expiry
            FROM user_settings 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function refreshCollection() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../Services/CacheService.php';
        $cacheService = new CacheService($this->config);

        try {
            // Clear all collection cache entries
            $stmt = $this->db->prepare("
                DELETE FROM releases 
                WHERE id LIKE 'collection_%'
            ");
            $stmt->execute();

            $_SESSION['settings_success'] = 'Collection cache cleared. Your collection will refresh on next view.';
        } catch (Exception $e) {
            $this->logger->error('Failed to refresh collection');
            $_SESSION['settings_error'] = 'Failed to refresh collection. Please try again.';
        }

        header('Location: /settings');
        exit;
    }
} 