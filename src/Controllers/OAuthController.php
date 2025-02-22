<?php

class OAuthController {
    private $twig;
    private $config;
    private $authService;
    private $oauthService;
    
    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/OAuthService.php';
        $this->authService = new AuthService($config);
        $this->oauthService = new OAuthService($config);
    }
    
    /**
     * Start OAuth flow
     */
    public function start() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        try {
            // Get request token
            $requestToken = $this->oauthService->getRequestToken($this->config['discogs']['oauth_callback_url']);
            
            // Store token secret in session for callback
            $_SESSION['oauth_token_secret'] = $requestToken['oauth_token_secret'];
            
            // Redirect to Discogs authorization page
            $authUrl = $this->oauthService->getAuthorizationUrl($requestToken['oauth_token']);
            header('Location: ' . $authUrl);
            exit;
        } catch (Exception $e) {
            $_SESSION['settings_error'] = 'Failed to start OAuth process: ' . $e->getMessage();
            header('Location: /settings');
            exit;
        }
    }
    
    /**
     * Handle OAuth callback
     */
    public function callback() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        $oauthToken = $_GET['oauth_token'] ?? null;
        $oauthVerifier = $_GET['oauth_verifier'] ?? null;
        
        if (!$oauthToken || !$oauthVerifier || !isset($_SESSION['oauth_token_secret'])) {
            $_SESSION['settings_error'] = 'Invalid OAuth callback';
            header('Location: /settings');
            exit;
        }
        
        try {
            // Exchange request token for access token
            $credentials = $this->oauthService->getAccessToken(
                $oauthToken,
                $_SESSION['oauth_token_secret'],
                $oauthVerifier
            );
            
            // Save credentials
            $this->oauthService->saveOAuthCredentials($_SESSION['user_id'], $credentials);
            
            // Clear session token secret
            unset($_SESSION['oauth_token_secret']);
            
            $_SESSION['settings_success'] = 'Successfully connected to Discogs via OAuth!';
        } catch (Exception $e) {
            $_SESSION['settings_error'] = 'Failed to complete OAuth process: ' . $e->getMessage();
        }
        
        header('Location: /settings');
        exit;
    }
} 