<?php

require_once __DIR__ . '/../Services/LogService.php';

function get_release_information($release_id) {
    global $config;
    $logger = LogService::getInstance($config);
    
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
            $logger->info("Using cached full release data", [
                'release_id' => $release_id,
                'title' => $cachedData['data']['title'],
                'cached_at' => $cachedData['last_updated'],
                'cache_age' => time() - strtotime($cachedData['last_updated']),
                'data_keys' => array_keys($cachedData['data']),
                'has_tracklist' => isset($cachedData['data']['tracklist']),
                'has_images' => isset($cachedData['data']['images'])
            ]);
            return $cachedData['data'];
        }
        
        // If we have basic data or invalid cache, fetch full data
        if ($cachedData && $cachedData['is_basic_data']) {
            $logger->info("Found basic data in cache, fetching full data", [
                'release_id' => $release_id,
                'basic_data_cached_at' => $cachedData['last_updated'],
                'is_basic_data' => $cachedData['is_basic_data'],
                'available_keys' => array_keys($cachedData['data'])
            ]);
        } else {
            $logger->info("No valid cache found, fetching from API", [
                'release_id' => $release_id,
                'reason' => !$cachedData ? 'No cache entry' : 'Invalid cache'
            ]);
        }
        
        // Fetch full data from API
        $logger->info("Fetching full release data from Discogs API", [
            'release_id' => $release_id
        ]);
        
        $url = $discogsService->buildUrl("/releases/{$release_id}");
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $logger->error("Failed to fetch release from Discogs API", [
                'release_id' => $release_id,
                'error' => error_get_last()['message'] ?? 'Unknown error'
            ]);
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            $logger->error("Failed to parse release JSON", [
                'release_id' => $release_id,
                'json_error' => json_last_error_msg()
            ]);
            return null;
        }
        
        $logger->info("Caching new full release data", [
            'release_id' => $release_id,
            'title' => $data['title'],
            'data_keys' => array_keys($data),
            'has_tracklist' => isset($data['tracklist']),
            'has_images' => isset($data['images'])
        ]);
        
        // Cache the full data with is_basic_data set to false
        $cacheService->cacheRelease($release_id, $data, null, false);
        
        return $data;
    } catch (Exception $e) {
        $logger->error("Error getting release info", [
            'release_id' => $release_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return null;
    }
}