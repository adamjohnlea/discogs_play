<?php

function get_my_release_information($release_id) {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    $discogsService = new DiscogsService($config);
    
    try {
        $url = $discogsService->buildUrl(
            "/users/:username/collection/releases/{$release_id}", 
            $_SESSION['user_id']
        );
        
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("Failed to fetch user's release information for ID: " . $release_id);
            return null;
        }
        
        return json_decode($response, true);
    } catch (Exception $e) {
        error_log("Error getting user's release info: " . $e->getMessage());
        return null;
    }
}