<?php

function get_wantlist() {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return [
            'error' => 'Not logged in'
        ];
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/WantlistCacheService.php';
    require_once __DIR__ . '/../Services/LogService.php';
    require_once __DIR__ . '/../Services/WantlistImageService.php';
    $discogsService = new DiscogsService($config);
    $cacheService = new WantlistCacheService($config);
    $logger = LogService::getInstance($config);
    $imageService = new WantlistImageService($config);
    
    // Start timing for cache check
    $cacheCheckStart = microtime(true);
    
    // First check if we have valid cached wantlist data we can use
    $cachedWantlist = $cacheService->getCachedWantlist();
    $isCacheValid = $cacheService->isWantlistCacheValid();
    
    $cacheCheckEnd = microtime(true);
    $cacheCheckTime = round(($cacheCheckEnd - $cacheCheckStart) * 1000, 2); // ms
    $logger->info('Cache check time', ['time_ms' => $cacheCheckTime]);
    
    // If we have valid cached data, use it instead of hitting the API
    if ($cachedWantlist && $isCacheValid) {
        $logger->info('Using valid cached wantlist data instead of API call', [
            'cache_check_time_ms' => $cacheCheckTime
        ]);
        return $cachedWantlist;
    }
    
    $logger->info('No valid wantlist cache found, fetching from API', [
        'has_cache' => $cachedWantlist ? 'yes' : 'no',
        'cache_valid' => $isCacheValid ? 'yes' : 'no',
        'cache_check_time_ms' => $cacheCheckTime
    ]);
    
    try {
        $url = $discogsService->buildUrl("/users/:username/wants", $_SESSION['user_id']);
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        // Set a timeout to avoid hanging requests
        $opts = stream_context_get_options($context);
        if (isset($opts['http'])) {
            $opts['http']['timeout'] = 30; // 30 seconds timeout (increased from 5)
            $context = stream_context_create($opts);
        }
        
        // Start timing API request
        $apiStart = microtime(true);
        $response = @file_get_contents($url, false, $context);
        $apiEnd = microtime(true);
        $apiTime = round(($apiEnd - $apiStart) * 1000, 2); // ms
        
        if ($response === false) {
            $http_response_header_str = isset($http_response_header) ? implode(', ', $http_response_header) : 'No headers';
            $logger->error('Failed to fetch wantlist from Discogs API', [
                'headers' => $http_response_header_str,
                'api_time_ms' => $apiTime
            ]);
            
            // If we have cached data, use it even if it's expired when API fails
            if ($cachedWantlist) {
                $logger->info('Using cached wantlist data due to API fetch failure (might be expired)');
                return $cachedWantlist;
            }
            
            // Check for specific error conditions
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    // Check for rate limiting
                    if (strpos($header, '429 Too Many Requests') !== false) {
                        return [
                            'error' => 'Discogs API rate limit exceeded. Please try again in a few minutes.',
                            'rate_limited' => true
                        ];
                    }
                    
                    // Check for authentication issues
                    if (strpos($header, '401 Unauthorized') !== false) {
                        return [
                            'error' => 'Authentication error. Your Discogs credentials may have expired. Please log out and log in again.',
                            'auth_error' => true
                        ];
                    }
                }
            }
            
            return [
                'error' => 'Failed to fetch wantlist. Please try again later.',
                'cached_available' => false
            ];
        }
        
        $data = json_decode($response, true);
        if ($data === null) {
            $logger->error('Invalid JSON response from Discogs API for wantlist', [
                'api_time_ms' => $apiTime
            ]);
            
            // If we have cached data, use it even if it's expired when API returns invalid data
            if ($cachedWantlist) {
                $logger->info('Using cached wantlist data due to invalid JSON response');
                return $cachedWantlist;
            }
            
            return [
                'error' => 'Invalid response from Discogs. Please try again.'
            ];
        }
        
        // Start timing caching operations
        $cachingStart = microtime(true);
        
        // Cache the entire wantlist response for future fallback
        $cacheService->cacheWantlist($data);
        
        // Cache each wantlist item's basic data
        if (isset($data['wants'])) {
            foreach ($data['wants'] as $want) {
                if (isset($want['basic_information'])) {
                    $releaseId = $want['basic_information']['id'];
                    
                    // Check if we already have detailed data for this item
                    // If we have detailed data (is_basic_data = 0), don't overwrite it with basic data
                    if (!$cacheService->isWantlistCacheValid($releaseId, false)) {
                        // Only cache basic data if we don't have detailed data
                        $cacheService->cacheWantlistItem(
                            $releaseId,
                            $want['basic_information'],
                            true
                        );
                    }
                    
                    // For initial requests, don't download and cache images as it adds substantial overhead
                    // Images will be downloaded and cached on demand when needed
                    // When a specific item is viewed, the image will be downloaded then
                    /*
                    // Always cache the cover image if needed
                    if (isset($want['basic_information']['cover_image']) && !empty($want['basic_information']['cover_image'])) {
                        // This will download and cache the image
                        $imageService->getWantlistCoverImage(
                            $want['basic_information']['cover_image'],
                            $releaseId
                        );
                    }
                    */
                }
            }
        }
        
        $cachingEnd = microtime(true);
        $cachingTime = round(($cachingEnd - $cachingStart) * 1000, 2); // ms
        
        $logger->info('Wantlist fetch and cache complete', [
            'api_time_ms' => $apiTime,
            'caching_time_ms' => $cachingTime,
            'wants_count' => isset($data['wants']) ? count($data['wants']) : 0
        ]);
        
        return $data;
    } catch (Exception $e) {
        $logger->error('Error fetching wantlist: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        // If we have cached data, use it instead of failing
        if ($cachedWantlist) {
            $logger->info('Using cached wantlist data due to exception: ' . $e->getMessage());
            return $cachedWantlist;
        }
        
        return [
            'error' => 'An error occurred while fetching your wantlist: ' . $e->getMessage()
        ];
    }
} 