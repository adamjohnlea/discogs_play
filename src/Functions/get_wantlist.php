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
    $discogsService = new DiscogsService($config);
    $cacheService = new WantlistCacheService($config);
    $logger = LogService::getInstance($config);
    
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
                    $cacheService->cacheWantlistItem(
                        $want['basic_information']['id'],
                        $want['basic_information'],
                        true
                    );
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