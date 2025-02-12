<?php

class ProfileController {
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
        
        // Get user data
        $stmt = $this->db->prepare("
            SELECT u.*, us.discogs_username, us.created_at as settings_created_at
            FROM users u
            LEFT JOIN user_settings us ON u.id = us.user_id
            WHERE u.id = :user_id
        ");
        
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get collection statistics
        $collectionStats = $this->getCollectionStats($_SESSION['user_id']);
        
        echo $this->twig->render('profile.html.twig', [
            'user' => $userData,
            'stats' => $collectionStats
        ]);
    }
    
    public function updatePassword() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->authService->verifyPassword($currentPassword, $user['password_hash'])) {
            $_SESSION['profile_error'] = 'Current password is incorrect';
            header('Location: /profile');
            exit;
        }
        
        // Validate new password
        $errors = $this->authService->validatePassword($newPassword, $confirmPassword);
        if (!empty($errors)) {
            $_SESSION['profile_error'] = implode(', ', $errors);
            header('Location: /profile');
            exit;
        }
        
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([
            ':password_hash' => $newHash,
            ':user_id' => $_SESSION['user_id']
        ])) {
            $_SESSION['profile_success'] = 'Password updated successfully';
        } else {
            $_SESSION['profile_error'] = 'Failed to update password';
        }
        
        header('Location: /profile');
        exit;
    }
    
    public function updateEmail() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        $newEmail = $_POST['email'] ?? '';
        
        // Validate email
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_error'] = 'Invalid email format';
            header('Location: /profile');
            exit;
        }
        
        // Check if email is already in use
        $stmt = $this->db->prepare("
            SELECT 1 FROM users 
            WHERE email = :email AND id != :user_id
        ");
        
        $stmt->execute([
            ':email' => $newEmail,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        if ($stmt->fetch()) {
            $_SESSION['profile_error'] = 'Email is already in use';
            header('Location: /profile');
            exit;
        }
        
        // Update email
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email = :email,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ");
        
        if ($stmt->execute([
            ':email' => $newEmail,
            ':user_id' => $_SESSION['user_id']
        ])) {
            $_SESSION['profile_success'] = 'Email updated successfully';
        } else {
            $_SESSION['profile_error'] = 'Failed to update email';
        }
        
        header('Location: /profile');
        exit;
    }
    
    private function getCollectionStats($userId) {
        require_once __DIR__ . '/../Services/DiscogsService.php';
        $discogsService = new DiscogsService($this->config);
        
        try {
            $credentials = $discogsService->getUserCredentials($userId);
            $url = $discogsService->buildUrl('/users/:username/collection/folders', $userId);
            $context = $discogsService->getApiContext($userId);
            
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return null;
            }
            
            $totalItems = 0;
            foreach ($data['folders'] as $folder) {
                $totalItems += $folder['count'];
            }
            
            return [
                'total_items' => $totalItems,
                'folder_count' => count($data['folders']),
                'folders' => $data['folders']
            ];
        } catch (Exception $e) {
            error_log("Error getting collection stats: " . $e->getMessage());
            return null;
        }
    }
} 