<?php

function get_release_info($release_id) {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/CacheService.php';
    require_once __DIR__ . '/../Services/LogService.php';
    $discogsService = new DiscogsService($config);
    $cacheService = new CacheService($config);
    $logger = LogService::getInstance($config);
    
    try {
        // Check cache first
        $cachedData = $cacheService->getCachedRelease($release_id);
        if ($cachedData && $cacheService->isReleaseCacheValid($release_id)) {
            return $cachedData['data'];
        }
        
        // If not in cache or cache is invalid, fetch from Discogs
        $url = $discogsService->buildUrl("/releases/{$release_id}");
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        // Implement retry logic with exponential backoff
        $maxRetries = 3;
        $retryCount = 0;
        $retryDelay = 1; // Start with 1 second delay
        
        while ($retryCount < $maxRetries) {
            $pagedata = @file_get_contents($url, false, $context);
            
            if ($pagedata !== false) {
                break; // Success, exit the retry loop
            }
            
            $headers = get_headers($url, 1);
            
            // Check for rate limiting
            if (isset($headers[0]) && strpos($headers[0], '429') !== false) {
                $logger->warning('Discogs API rate limit hit, retrying...', [
                    'release_id' => $release_id,
                    'retry_count' => $retryCount + 1,
                    'retry_delay' => $retryDelay
                ]);
                
                // If we have a Retry-After header, use that value
                if (isset($headers['Retry-After'])) {
                    $retryDelay = intval($headers['Retry-After']);
                }
                
                // Sleep for the delay period
                sleep($retryDelay);
                
                // Exponential backoff for next retry
                $retryDelay *= 2;
                $retryCount++;
                continue;
            }
            
            // If it's not a rate limit issue, break out of the retry loop
            break;
        }
        
        if ($pagedata === false) {
            $headers = get_headers($url, 1);
            $logger->error('Failed to fetch release from Discogs API after retries', [
                'release_id' => $release_id,
                'headers' => $headers,
                'retry_count' => $retryCount
            ]);
            
            if (isset($headers[0]) && strpos($headers[0], '429') !== false) {
                return [
                    'error' => 'Rate limit exceeded. Please try again in a moment.'
                ];
            }
            
            return [
                'error' => 'Failed to fetch release information. Please try again.'
            ];
        }
        
        $data = json_decode($pagedata, true);
        if ($data === null) {
            $logger->error('Invalid JSON response from Discogs API');
            return [
                'error' => 'Invalid response from Discogs. Please try again.'
            ];
        }
        
        // Check if the cached release exists and its type
        $existingData = $cacheService->getCachedRelease($release_id);
        
        // Log details about the data being cached
        $logger->info('Checking if release data changed', [
            'release_id' => $release_id,
            'has_existing_data' => $existingData ? 'yes' : 'no',
            'existing_is_basic' => $existingData ? ($existingData['is_basic_data'] ? 'yes' : 'no') : 'n/a',
            'new_is_basic' => 'no', // Always detailed data from single release API
            'data_size' => strlen($pagedata)
        ]);
        
        // Log the reason for update
        $reason = $existingData ? ($existingData['is_basic_data'] ? 'upgrading_from_basic' : 'data_changed') : 'no_existing_data';
        $logger->info('Updating release data', [
            'release_id' => $release_id,
            'reason' => $reason,
            'is_basic_data' => 'no'
        ]);
        
        // Cache the release data - explicitly set is_basic_data to false for detailed data
        $cacheService->cacheRelease($release_id, $data, false);
        
        $logger->info('Release update successful', [
            'release_id' => $release_id
        ]);
        
        return $data;
    } catch (Exception $e) {
        $logger->error('Error in get_release_info: ' . $e->getMessage());
        return [
            'error' => 'An error occurred. Please try again.'
        ];
    }
}