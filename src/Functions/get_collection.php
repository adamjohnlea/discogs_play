<?php
function get_collection() {
    global $folder_id, $sort_by, $order, $page, $per_page, $config;
    
    if (!isset($_SESSION['user_id'])) {
        return [
            'error' => 'Authentication required',
            'releases' => [],
            'pagination' => ['pages' => 1]
        ];
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/CacheService.php';
    require_once __DIR__ . '/../Services/LogService.php';
    $discogsService = new DiscogsService($config);
    $cacheService = new CacheService($config);
    $logger = LogService::getInstance($config);
    
    try {
        // Check if we have a cached collection list for this combination
        $cacheKey = "collection_{$folder_id}_{$sort_by}_{$order}_{$page}_{$per_page}";
        $cachedCollection = $cacheService->getCachedCollection($cacheKey);
        
        if ($cachedCollection && $cacheService->isCollectionCacheValid($cacheKey)) {
            $logger->info("Using cached collection list", [
                'folder_id' => $folder_id,
                'page' => $page,
                'sort_by' => $sort_by,
                'order' => $order,
                'cache_key' => $cacheKey,
                'cached_releases' => count($cachedCollection['releases']),
                'cache_age' => time() - strtotime($cachedCollection['last_updated'] ?? 'now')
            ]);
            return $cachedCollection;
        }
        
        // If not in cache, fetch from Discogs
        $url = $discogsService->buildUrl(
            "/users/:username/collection/folders/{$folder_id}/releases", 
            $_SESSION['user_id']
        );
        
        // Add query parameters
        $url .= "?sort={$sort_by}&sort_order={$order}&page={$page}&per_page={$per_page}";
        
        $logger->info("Fetching collection from Discogs", [
            'folder_id' => $folder_id,
            'page' => $page,
            'sort_by' => $sort_by,
            'order' => $order,
            'url' => $url,
            'reason' => !$cachedCollection ? 'No cache entry' : 'Cache expired'
        ]);
        
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        $pagedata = @file_get_contents($url, false, $context);
        
        if ($pagedata === false) {
            $error = error_get_last();
            $logger->error("Discogs API Error", [
                'error' => $error['message'] ?? 'Unknown error',
                'url' => $url
            ]);
            
            // Check if we got rate limited
            $headers = get_headers($url, 1);
            if (isset($headers[0]) && strpos($headers[0], '429') !== false) {
                $logger->error("Rate limited by Discogs API");
                return [
                    'error' => 'Rate limit exceeded. Please try again in a moment.',
                    'releases' => [],
                    'pagination' => ['pages' => 1]
                ];
            }
            
            // For other errors (like 502 Bad Gateway)
            return [
                'error' => 'Temporarily unable to fetch collection. Please try again.',
                'releases' => [],
                'pagination' => ['pages' => 1]
            ];
        }

        // decode the JSON feed
        $data = json_decode($pagedata, true);
        if ($data === null) {
            $logger->error("Failed to parse JSON response from Discogs API");
            return [
                'error' => 'Invalid response from Discogs. Please try again.',
                'releases' => [],
                'pagination' => ['pages' => 1]
            ];
        }

        $cacheStats = ['cached' => 0, 'uncached' => 0];
        
        // For each release in the collection, check if we have it cached
        foreach ($data['releases'] as &$release) {
            $releaseId = $release['id'];
            $cachedData = $cacheService->getCachedRelease($releaseId);
            
            if ($cachedData && $cacheService->isReleaseCacheValid($releaseId, true)) {
                $cacheStats['cached']++;
                $logger->debug("Using cached data for release", [
                    'release_id' => $releaseId,
                    'title' => $cachedData['data']['title'],
                    'is_basic_data' => $cachedData['is_basic_data'],
                    'cache_age' => time() - strtotime($cachedData['last_updated']),
                    'data_keys' => array_keys($cachedData['data'])
                ]);
                
                // Keep the collection-specific data
                $collectionData = [
                    'instance_id' => $release['instance_id'],
                    'folder_id' => $release['folder_id'],
                    'date_added' => $release['date_added'],
                    'id' => $release['id']
                ];

                // Create basic_information structure from cached data
                $basicInfo = [
                    'id' => $releaseId,
                    'title' => $cachedData['data']['title'],
                    'year' => $cachedData['data']['year'],
                    'artists' => $cachedData['data']['artists'],
                    'cover_image' => isset($cachedData['data']['images'][0]) ? 
                        $cachedData['data']['images'][0]['resource_url'] : 
                        $release['basic_information']['cover_image']
                ];

                // Update the release with the new data
                $release['basic_information'] = $basicInfo;
                
                // Restore collection-specific data
                foreach ($collectionData as $key => $value) {
                    $release[$key] = $value;
                }
            } else {
                $cacheStats['uncached']++;
                $logger->debug("Caching new release data", [
                    'release_id' => $releaseId,
                    'title' => $release['basic_information']['title'],
                    'data_keys' => array_keys($release['basic_information']),
                    'reason' => !$cachedData ? 'No cache entry' : 'Invalid cache'
                ]);
                
                // Cache the basic info with the basic data flag
                $cacheService->cacheRelease($releaseId, $release['basic_information'], null, true);
            }
        }
        
        // Cache the collection list
        $cacheService->cacheCollection($cacheKey, $data);
        
        $logger->info("Collection cache statistics", [
            'cached_releases' => $cacheStats['cached'],
            'uncached_releases' => $cacheStats['uncached'],
            'total_releases' => count($data['releases']),
            'page' => $page,
            'per_page' => $per_page,
            'folder_id' => $folder_id,
            'sort_by' => $sort_by,
            'order' => $order
        ]);

        return $data;
    } catch (Exception $e) {
        $logger->error("Exception in get_collection", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return [
            'error' => 'An error occurred. Please try again.',
            'releases' => [],
            'pagination' => ['pages' => 1]
        ];
    }
}