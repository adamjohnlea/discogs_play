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
        
        // Check if the cached data is valid and not just a placeholder
        $isPlaceholder = $cachedData && isset($cachedData['data']['placeholder']) && $cachedData['data']['placeholder'] === true;
        $isValid = $cachedData && $cacheService->isWantlistItemCacheValid($release_id) && !$isPlaceholder;
        
        if ($isValid) {
            $logger->info('Returning cached wantlist item', [
                'id' => $release_id,
                'data' => json_encode($cachedData['data']),
                'is_placeholder' => 'no'
            ]);
            return $cachedData['data'];
        }
        
        // Log whether we're skipping placeholder data
        if ($isPlaceholder) {
            $logger->info('Skipping placeholder wantlist item data', [
                'id' => $release_id,
                'fetching_from_api' => 'yes'
            ]);
        } else {
            $logger->info('Fetching wantlist item from Discogs', ['id' => $release_id]);
        }
        
        // If not in cache, cache is invalid, or is just a placeholder, fetch from Discogs
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
        
        // Also cache the images if present
        if (isset($data['images']) && is_array($data['images'])) {
            require_once __DIR__ . '/../Services/WantlistImageService.php';
            $imageService = new WantlistImageService($config);
            
            // Cache the first image as cover image if it exists
            if (!empty($data['images'][0]['resource_url'])) {
                $imageService->getWantlistCoverImage(
                    $data['images'][0]['resource_url'],
                    $release_id
                );
            }
            
            // Cache all images in the list
            foreach ($data['images'] as $index => $image) {
                if (!empty($image['resource_url'])) {
                    $imageService->getWantlistImage(
                        $image['resource_url'],
                        $release_id,
                        $index
                    );
                }
            }
        }
        
        return $data;
    } catch (Exception $e) {
        $logger->error('Error in get_wantlist_info: ' . $e->getMessage());
        return [
            'error' => 'An error occurred. Please try again.'
        ];
    }
} 