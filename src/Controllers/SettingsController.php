<?php

class SettingsController {
    private $twig;
    private $config;
    private $authService;
    private $db;
    
    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/AuthService.php';
        $this->authService = new AuthService($config);
        $this->db = DatabaseService::getInstance($config)->getConnection();
    }
    
    public function show() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        // Get user's current settings
        $settings = $this->getUserSettings();
        
        echo $this->twig->render('settings.html.twig', [
            'settings' => $settings,
            'success' => $_SESSION['settings_success'] ?? null,
            'error' => $_SESSION['settings_error'] ?? null
        ]);
        
        // Clear flash messages
        unset($_SESSION['settings_success'], $_SESSION['settings_error']);
    }
    
    public function update() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        $discogs_username = $_POST['discogs_username'] ?? '';
        $discogs_token = $_POST['discogs_token'] ?? '';
        
        // Validate inputs
        if (empty($discogs_username) || empty($discogs_token)) {
            $_SESSION['settings_error'] = 'Both Discogs username and token are required.';
            header('Location: /settings');
            exit;
        }
        
        // Verify the credentials with Discogs API
        if (!$this->verifyDiscogsCredentials($discogs_username, $discogs_token)) {
            $_SESSION['settings_error'] = 'Invalid Discogs credentials. Please check your username and token.';
            header('Location: /settings');
            exit;
        }
        
        // Save the settings
        if ($this->saveSettings($discogs_username, $discogs_token)) {
            $_SESSION['settings_success'] = 'Settings updated successfully!';
        } else {
            $_SESSION['settings_error'] = 'Failed to save settings. Please try again.';
        }
        
        header('Location: /settings');
        exit;
    }
    
    private function getUserSettings() {
        $stmt = $this->db->prepare("
            SELECT discogs_username, discogs_token 
            FROM user_settings 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function saveSettings($username, $token) {
        try {
            // Check if settings already exist
            $stmt = $this->db->prepare("
                SELECT id FROM user_settings WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing settings
                $stmt = $this->db->prepare("
                    UPDATE user_settings 
                    SET discogs_username = :username,
                        discogs_token = :token,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = :user_id
                ");
            } else {
                // Insert new settings
                $stmt = $this->db->prepare("
                    INSERT INTO user_settings (user_id, discogs_username, discogs_token)
                    VALUES (:user_id, :username, :token)
                ");
            }
            
            return $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':username' => $username,
                ':token' => $token
            ]);
        } catch (Exception $e) {
            error_log("Error saving settings: " . $e->getMessage());
            return false;
        }
    }
    
    private function verifyDiscogsCredentials($username, $token) {
        $url = "https://api.discogs.com/users/{$username}";
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: DiscogsPlay/1.0',
                    "Authorization: Discogs token={$token}"
                ]
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $data = json_decode($response, true);
        return isset($data['username']);
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
            error_log("Error refreshing collection: " . $e->getMessage());
            $_SESSION['settings_error'] = 'Failed to refresh collection. Please try again.';
        }

        header('Location: /settings');
        exit;
    }
} 