<?php

class DiscogsService {
    private $db;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->db = DatabaseService::getInstance($config)->getConnection();
    }
    
    public function getUserCredentials($userId) {
        $stmt = $this->db->prepare("
            SELECT discogs_username, discogs_token 
            FROM user_settings 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings || empty($settings['discogs_username']) || empty($settings['discogs_token'])) {
            throw new Exception('Discogs credentials not found');
        }
        
        return $settings;
    }
    
    public function getApiContext($userId) {
        $credentials = $this->getUserCredentials($userId);
        
        return stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . $this->config['discogs']['user_agent'],
                    'Authorization: Discogs token=' . $credentials['discogs_token']
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