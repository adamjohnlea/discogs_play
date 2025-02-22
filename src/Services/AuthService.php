<?php

class AuthService {
    private $db;
    private $user;
    private $logger;
    
    public function __construct($config) {
        $this->db = DatabaseService::getInstance($config)->getConnection();
        require_once __DIR__ . '/../Models/User.php';
        require_once __DIR__ . '/LogService.php';
        $this->user = new User($this->db);
        $this->logger = LogService::getInstance($config);
    }
    
    public function validateRegistration($username, $email, $password, $confirmPassword) {
        $errors = [];
        
        // Username validation
        if (empty($username)) {
            $errors['username'][] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors['username'][] = 'Username must be between 3-20 characters and can only contain letters, numbers, and underscores';
        } elseif ($this->user->findByUsername($username)) {
            $errors['username'][] = 'Username is already taken';
        }
        
        // Email validation
        if (empty($email)) {
            $errors['email'][] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Invalid email format';
        } elseif ($this->user->findByEmail($email)) {
            $errors['email'][] = 'Email is already registered';
        }
        
        // Password validation
        if (empty($password)) {
            $errors['password'][] = 'Password is required';
        } else {
            if (strlen($password) < 8) {
                $errors['password'][] = 'Password must be at least 8 characters';
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors['password'][] = 'Password must contain at least one uppercase letter';
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors['password'][] = 'Password must contain at least one lowercase letter';
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors['password'][] = 'Password must contain at least one number';
            }
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                $errors['password'][] = 'Password must contain at least one symbol';
            }
        }
        
        // Confirm password validation
        if ($password !== $confirmPassword) {
            $errors['confirm_password'][] = 'Passwords do not match';
        }
        
        return $errors;
    }
    
    public function verifyPassword($password, $hash) {
        return $this->user->verifyPassword($password, $hash);
    }
    
    public function validatePassword($newPassword, $confirmPassword) {
        $errors = [];
        
        if (empty($newPassword)) {
            $errors[] = 'Password is required';
        } else {
            if (strlen($newPassword) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            }
            if (!preg_match('/[A-Z]/', $newPassword)) {
                $errors[] = 'Password must contain at least one uppercase letter';
            }
            if (!preg_match('/[a-z]/', $newPassword)) {
                $errors[] = 'Password must contain at least one lowercase letter';
            }
            if (!preg_match('/[0-9]/', $newPassword)) {
                $errors[] = 'Password must contain at least one number';
            }
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
                $errors[] = 'Password must contain at least one symbol';
            }
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        return $errors;
    }
    
    public function register($username, $email, $password) {
        try {
            $this->db->beginTransaction();
            
            // Create the user
            $success = $this->user->create($username, $email, $password);
            
            if ($success) {
                // Get the newly created user's ID
                $userId = $this->db->lastInsertId();
                
                $this->logger->info('User created successfully', [
                    'user_id' => $userId,
                    'username' => $username
                ]);
                
                // Create initial user settings row
                $stmt = $this->db->prepare("
                    INSERT INTO user_settings (user_id, created_at, updated_at)
                    VALUES (:user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                
                $settingsSuccess = $stmt->execute([':user_id' => $userId]);
                
                if (!$settingsSuccess) {
                    $error = $stmt->errorInfo();
                    $this->logger->error('Failed to create user_settings row', [
                        'user_id' => $userId,
                        'error' => $error
                    ]);
                    $this->db->rollback();
                    return false;
                }
                
                $this->logger->info('User settings row created successfully', [
                    'user_id' => $userId
                ]);
                
                $this->db->commit();
                return true;
            }
            
            $this->logger->error('Failed to create user', [
                'username' => $username
            ]);
            $this->db->rollback();
            return false;
        } catch (Exception $e) {
            $this->logger->error('Exception during registration', [
                'error' => $e->getMessage(),
                'username' => $username
            ]);
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function login($username, $password) {
        $user = $this->user->findByUsername($username);
        if (!$user) {
            return false;
        }
        
        if ($this->verifyCredentials($username, $password)) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Generate and store remember token if needed
            if (isset($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(32));
                $this->user->updateRememberToken($user['id'], $token);
                setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
            }
            
            return true;
        }
        
        return false;
    }
    
    public function verifyCredentials($username, $password) {
        $user = $this->user->findByUsername($username);
        if (!$user) {
            return false;
        }
        
        return $this->user->verifyPassword($password, $user['password_hash']);
    }
    
    public function logout() {
        // Clear session
        session_unset();
        session_destroy();
        
        // Clear remember token cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    public function isLoggedIn() {
        // First check session
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        
        // Then check remember token
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $user = $this->user->findByRememberToken($token);
            
            if ($user) {
                // Refresh session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return true;
            }
            
            // Invalid token, clear it
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        return false;
    }
} 