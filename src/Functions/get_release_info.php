<?php

function get_release_information($release_id) {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/CacheService.php';
    $discogsService = new DiscogsService($config);
    $cacheService = new CacheService($config);
    
    try {
        // Check cache first
        $cachedData = $cacheService->getCachedRelease($release_id);
        if ($cachedData && !$cachedData['is_basic_data'] && $cacheService->isReleaseCacheValid($release_id)) {
            return $cachedData['data'];
        }
        
        // Fetch full data from API
        $url = $discogsService->buildUrl("/releases/{$release_id}");
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            return null;
        }
        
        // Cache the full data with is_basic_data set to false
        $cacheService->cacheRelease($release_id, $data, null, false);
        
        return $data;
    } catch (Exception $e) {
        error_log("Error getting release info: " . $e->getMessage());
        return null;
    }
}