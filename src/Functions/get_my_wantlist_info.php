<?php

function get_my_wantlist_info($release_id) {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/LogService.php';
    $discogsService = new DiscogsService($config);
    $logger = LogService::getInstance($config);
    
    try {
        $url = $discogsService->buildUrl(
            "/users/:username/wants/{$release_id}", 
            $_SESSION['user_id']
        );
        
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $logger->error("Failed to fetch user's wantlist item information", [
                'release_id' => $release_id
            ]);
            return null;
        }
        
        return json_decode($response, true);
    } catch (Exception $e) {
        $logger->error("Error getting user's wantlist info: " . $e->getMessage());
        return null;
    }
} 