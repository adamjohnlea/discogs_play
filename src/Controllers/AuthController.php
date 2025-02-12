<?php

class AuthController {
    private $twig;
    private $authService;
    private $config;
    
    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/AuthService.php';
        $this->authService = new AuthService($config);
    }
    
    public function showRegistrationForm() {
        if ($this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        
        echo $this->twig->render('auth/register.html.twig', [
            'error' => null
        ]);
    }
    
    public function register() {
        if ($this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $errors = $this->authService->validateRegistration(
            $username,
            $email,
            $password,
            $confirmPassword
        );
        
        if (!empty($errors)) {
            echo $this->twig->render('auth/register.html.twig', [
                'errors' => $errors,
                'old' => [
                    'username' => $username,
                    'email' => $email
                ]
            ]);
            return;
        }
        
        try {
            $success = $this->authService->register($username, $email, $password);
            
            if ($success) {
                // Log the user in
                $this->authService->login($username, $password);
                header('Location: /settings');
                exit;
            }
            
            echo $this->twig->render('auth/register.html.twig', [
                'errors' => ['general' => ['Registration failed. Please try again.']],
                'old' => [
                    'username' => $username,
                    'email' => $email
                ]
            ]);
        } catch (Exception $e) {
            echo $this->twig->render('auth/register.html.twig', [
                'errors' => ['general' => ['An error occurred. Please try again later.']],
                'old' => [
                    'username' => $username,
                    'email' => $email
                ]
            ]);
        }
    }
    
    public function showLoginForm() {
        if ($this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        
        echo $this->twig->render('auth/login.html.twig', [
            'error' => null
        ]);
    }
    
    public function login() {
        if ($this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($this->authService->login($username, $password)) {
            header('Location: /');
            exit;
        }
        
        echo $this->twig->render('auth/login.html.twig', [
            'error' => 'Invalid username or password',
            'old' => [
                'username' => $username
            ]
        ]);
    }
    
    public function logout() {
        $this->authService->logout();
        header('Location: /');
        exit;
    }

    public function forceLogout() {
        // Clear all cookies
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
            foreach($cookies as $cookie) {
                $parts = explode('=', $cookie);
                $name = trim($parts[0]);
                setcookie($name, '', time()-3600, '/');
            }
        }

        // Destroy session
        session_unset();
        session_destroy();
        
        // Start new session to prevent errors
        session_start();
        
        header('Location: /');
        exit;
    }
} 