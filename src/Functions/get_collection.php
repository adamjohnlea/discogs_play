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
        // Include user_id in the cache key to separate collections by user
        $cacheKey = "collection_{$_SESSION['user_id']}_{$folder_id}_{$sort_by}_{$order}_{$page}_{$per_page}";
        $cachedCollection = $cacheService->getCachedCollection($cacheKey);
        
        if ($cachedCollection && $cacheService->isCollectionCacheValid($cacheKey)) {
            // Ensure the cached data belongs to the current user
            if (isset($cachedCollection['user_id']) && $cachedCollection['user_id'] == $_SESSION['user_id']) {
                return $cachedCollection;
            }
        }
        
        // If not in cache, fetch from Discogs
        $url = $discogsService->buildUrl(
            "/users/:username/collection/folders/{$folder_id}/releases", 
            $_SESSION['user_id']
        );
        
        // Add query parameters
        $url .= "?sort={$sort_by}&sort_order={$order}&page={$page}&per_page={$per_page}";
        
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        // Set a timeout to avoid hanging requests
        $opts = stream_context_get_options($context);
        if (isset($opts['http'])) {
            $opts['http']['timeout'] = 30; // 30 seconds timeout
            $context = stream_context_create($opts);
        }
        
        $pagedata = @file_get_contents($url, false, $context);
        
        if ($pagedata === false) {
            // Check if we got rate limited
            $headers = get_headers($url, 1);
            $logger->error('Failed to fetch collection from Discogs API', [
                'headers' => $headers
            ]);
            
            if (isset($headers[0]) && strpos($headers[0], '429') !== false) {
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
            $logger->error('Invalid JSON response from Discogs API');
            return [
                'error' => 'Invalid response from Discogs. Please try again.',
                'releases' => [],
                'pagination' => ['pages' => 1]
            ];
        }

        // Add user_id to the data for validation
        $data['user_id'] = $_SESSION['user_id'];
        
        // For each release in the collection, check if we have it cached
        foreach ($data['releases'] as &$release) {
            $releaseId = $release['id'];
            $cachedData = $cacheService->getCachedRelease($releaseId);
            
            if ($cachedData && $cacheService->isReleaseCacheValid($releaseId, true)) {
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
                // Only cache the basic info if we don't already have detailed data for this release
                $existingData = $cacheService->getCachedRelease($releaseId);
                if (!$existingData || $existingData['is_basic_data']) {
                    // Only cache basic info if no cache exists or if existing cache is also basic
                    $cacheService->cacheRelease($releaseId, $release['basic_information'], true);
                }
            }
        }
        
        // Cache the collection list
        $cacheService->cacheCollection($cacheKey, $data);

        return $data;
    } catch (Exception $e) {
        $logger->error('Error in get_collection: ' . $e->getMessage());
        return [
            'error' => 'An error occurred. Please try again.',
            'releases' => [],
            'pagination' => ['pages' => 1]
        ];
    }
}