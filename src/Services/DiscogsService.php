<?php

class DiscogsService {
    private $db;
    private $config;
    private $oauthService;
    private $logger;
    
    public function __construct($config) {
        $this->config = $config;
        $this->db = DatabaseService::getInstance($config)->getConnection();
        require_once __DIR__ . '/OAuthService.php';
        require_once __DIR__ . '/LogService.php';
        $this->oauthService = new OAuthService($config);
        $this->logger = LogService::getInstance($config);
    }
    
    public function getUserCredentials($userId) {
        $stmt = $this->db->prepare("
            SELECT discogs_username, oauth_access_token, oauth_access_token_secret
            FROM user_settings 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings || empty($settings['oauth_access_token']) || empty($settings['oauth_access_token_secret'])) {
            throw new Exception('OAuth credentials not found. Please connect your Discogs account.');
        }

        return [
            'discogs_username' => $settings['discogs_username'],
            'oauth_access_token' => $settings['oauth_access_token'],
            'oauth_access_token_secret' => $settings['oauth_access_token_secret'],
            'using_oauth' => true
        ];
    }
    
    public function getApiContext($userId) {
        $credentials = $this->getUserCredentials($userId);
        
        // Build OAuth authorization header
        $timestamp = time();
        $nonce = md5(uniqid(mt_rand(), true));
        
        $params = [
            'oauth_consumer_key' => $this->config['discogs']['oauth_consumer_key'],
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $credentials['oauth_access_token'],
            'oauth_version' => '1.0'
        ];
        
        // Generate signature
        $signature = $this->config['discogs']['oauth_consumer_secret'] . '&' . $credentials['oauth_access_token_secret'];
        $params['oauth_signature'] = $signature;
        
        // Build authorization header
        $authHeader = 'OAuth ' . implode(', ', array_map(function($k, $v) {
            return sprintf('%s="%s"', $k, rawurlencode($v));
        }, array_keys($params), $params));
        
        return stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . $this->config['discogs']['user_agent'],
                    'Authorization: ' . $authHeader
                ]
            ]
        ]);
    }
    
    public function buildUrl($path, $userId = null) {
        $baseUrl = $this->config['discogs']['api_url'];
        
        if ($userId !== null) {
            $credentials = $this->getUserCredentials($userId);
            $path = str_replace(':username', $credentials['discogs_username'], $path);
        }
        
        return $baseUrl . $path;
    }
} 