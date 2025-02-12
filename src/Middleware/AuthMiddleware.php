<?php

class AuthMiddleware {
    private $authService;
    private $publicRoutes = [
        '/' => true,
        '/login' => true,
        '/register' => true
    ];
    
    public function __construct($config) {
        require_once __DIR__ . '/../Services/AuthService.php';
        $this->authService = new AuthService($config);
    }
    
    public function handle($path) {
        // Allow public routes
        if (isset($this->publicRoutes[$path])) {
            return true;
        }
        
        // Check if user is authenticated
        if (!$this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        
        return true;
    }
} 