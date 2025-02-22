<?php

class OAuthService {
    private $db;
    private $config;
    private $requestTokenUrl = 'https://api.discogs.com/oauth/request_token';
    private $authorizeUrl = 'https://www.discogs.com/oauth/authorize';
    private $accessTokenUrl = 'https://api.discogs.com/oauth/access_token';
    
    public function __construct($config) {
        $this->config = $config;
        $this->db = DatabaseService::getInstance($config)->getConnection();
    }
    
    /**
     * Start OAuth flow by getting request token
     */
    public function getRequestToken($callbackUrl) {
        $timestamp = time();
        $nonce = $this->generateNonce();
        
        $params = [
            'oauth_consumer_key' => $this->config['discogs']['oauth_consumer_key'],
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_signature' => $this->config['discogs']['oauth_consumer_secret'] . '&',
            'oauth_timestamp' => $timestamp,
            'oauth_callback' => $callbackUrl,
            'oauth_version' => '1.0'
        ];
        
        $authHeader = $this->buildAuthorizationHeader($params);
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: ' . $authHeader,
                    'User-Agent: ' . $this->config['discogs']['user_agent']
                ]
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = file_get_contents($this->requestTokenUrl, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to get request token');
        }
        
        parse_str($response, $responseData);
        
        if (!isset($responseData['oauth_token'], $responseData['oauth_token_secret'])) {
            throw new Exception('Invalid response from request token endpoint');
        }
        
        return $responseData;
    }
    
    /**
     * Get authorization URL for request token
     */
    public function getAuthorizationUrl($requestToken) {
        return $this->authorizeUrl . '?oauth_token=' . urlencode($requestToken);
    }
    
    /**
     * Exchange request token for access token
     */
    public function getAccessToken($requestToken, $requestTokenSecret, $verifier) {
        $timestamp = time();
        $nonce = $this->generateNonce();
        
        $params = [
            'oauth_consumer_key' => $this->config['discogs']['oauth_consumer_key'],
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_signature' => $this->config['discogs']['oauth_consumer_secret'] . '&' . $requestTokenSecret,
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $requestToken,
            'oauth_verifier' => $verifier,
            'oauth_version' => '1.0'
        ];
        
        $authHeader = $this->buildAuthorizationHeader($params);
        
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: ' . $authHeader,
                    'User-Agent: ' . $this->config['discogs']['user_agent']
                ]
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = file_get_contents($this->accessTokenUrl, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to get access token');
        }
        
        parse_str($response, $responseData);
        
        if (!isset($responseData['oauth_token'], $responseData['oauth_token_secret'])) {
            throw new Exception('Invalid response from access token endpoint');
        }
        
        return $responseData;
    }
    
    /**
     * Save OAuth credentials for user
     */
    public function saveOAuthCredentials($userId, $credentials) {
        $stmt = $this->db->prepare("
            UPDATE user_settings 
            SET oauth_access_token = :access_token,
                oauth_access_token_secret = :token_secret,
                oauth_token_expiry = :expiry,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
        ");
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':access_token' => $credentials['oauth_token'],
            ':token_secret' => $credentials['oauth_token_secret'],
            ':expiry' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);
    }
    
    /**
     * Get OAuth credentials for user
     */
    public function getOAuthCredentials($userId) {
        $stmt = $this->db->prepare("
            SELECT oauth_access_token, oauth_access_token_secret, oauth_token_expiry
            FROM user_settings 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate a unique nonce
     */
    private function generateNonce() {
        return md5(uniqid(mt_rand(), true));
    }
    
    /**
     * Build OAuth Authorization header
     */
    private function buildAuthorizationHeader($params) {
        $parts = [];
        ksort($params);
        
        foreach ($params as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, rawurlencode($value));
        }
        
        return 'OAuth ' . implode(', ', $parts);
    }
} 