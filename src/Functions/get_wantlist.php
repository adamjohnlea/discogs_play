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
    
    try {
        $url = $discogsService->buildUrl("/users/:username/wants", $_SESSION['user_id']);
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $logger->error('Failed to fetch wantlist from Discogs API');
            return [
                'error' => 'Failed to fetch wantlist. Please try again.'
            ];
        }
        
        $data = json_decode($response, true);
        if ($data === null) {
            $logger->error('Invalid JSON response from Discogs API for wantlist');
            return [
                'error' => 'Invalid response from Discogs. Please try again.'
            ];
        }
        
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
                    
                    // Always cache the cover image if needed
                    if (isset($want['basic_information']['cover_image']) && !empty($want['basic_information']['cover_image'])) {
                        // This will download and cache the image
                        $imageService->getWantlistCoverImage(
                            $want['basic_information']['cover_image'],
                            $releaseId
                        );
                    }
                }
            }
        }
        
        return $data;
    } catch (Exception $e) {
        $logger->error('Error fetching wantlist: ' . $e->getMessage());
        return [
            'error' => 'An error occurred while fetching your wantlist.'
        ];
    }
} 