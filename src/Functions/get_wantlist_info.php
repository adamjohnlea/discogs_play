<?php

function get_wantlist_info($release_id) {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/WantlistCacheService.php';
    require_once __DIR__ . '/../Services/LogService.php';
    $discogsService = new DiscogsService($config);
    $cacheService = new WantlistCacheService($config);
    $logger = LogService::getInstance($config);
    
    try {
        // Check cache first
        $cachedData = $cacheService->getCachedWantlistItem($release_id);
        if ($cachedData && $cacheService->isWantlistCacheValid($release_id)) {
            $logger->info('Returning cached wantlist item', [
                'id' => $release_id,
                'data' => json_encode($cachedData['data'])
            ]);
            return $cachedData['data'];
        }
        
        $logger->info('Fetching wantlist item from Discogs', ['id' => $release_id]);
        
        // If not in cache or cache is invalid, fetch from Discogs
        $url = $discogsService->buildUrl("/releases/{$release_id}");
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        $pagedata = @file_get_contents($url, false, $context);
        
        if ($pagedata === false) {
            $headers = get_headers($url, 1);
            $logger->error('Failed to fetch wantlist item from Discogs API', [
                'headers' => $headers
            ]);
            
            if (isset($headers[0]) && strpos($headers[0], '429') !== false) {
                return [
                    'error' => 'Rate limit exceeded. Please try again in a moment.'
                ];
            }
            
            return [
                'error' => 'Failed to fetch wantlist item information. Please try again.'
            ];
        }
        
        $data = json_decode($pagedata, true);
        if ($data === null) {
            $logger->error('Invalid JSON response from Discogs API for wantlist item');
            return [
                'error' => 'Invalid response from Discogs. Please try again.'
            ];
        }
        
        // Cache the wantlist item data
        $cacheService->cacheWantlistItem($release_id, $data);
        
        return $data;
    } catch (Exception $e) {
        $logger->error('Error in get_wantlist_info: ' . $e->getMessage());
        return [
            'error' => 'An error occurred. Please try again.'
        ];
    }
} 