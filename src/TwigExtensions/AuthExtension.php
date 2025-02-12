<?php

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AuthExtension extends AbstractExtension {
    private $authService;
    
    public function __construct($config) {
        require_once __DIR__ . '/../Services/AuthService.php';
        $this->authService = new AuthService($config);
    }
    
    public function getFunctions() {
        return [
            new TwigFunction('is_granted', [$this, 'isGranted'])
        ];
    }
    
    public function isGranted($role) {
        if ($role === 'ROLE_USER') {
            return $this->authService->isLoggedIn();
        }
        return false;
    }
} 