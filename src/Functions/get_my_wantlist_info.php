<?php

function get_my_wantlist_info($release_id) {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/LogService.php';
    $discogsService = new DiscogsService($config);
    $logger = LogService::getInstance($config);
    
    try {
        $logger->info('Fetching user-specific wantlist item information', [
            'release_id' => $release_id,
            'user_id' => $_SESSION['user_id']
        ]);
        
        $url = $discogsService->buildUrl(
            "/users/:username/wants/{$release_id}", 
            $_SESSION['user_id']
        );
        
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $headers = isset($http_response_header) ? implode(', ', $http_response_header) : 'No headers';
            $logger->error("Failed to fetch user's wantlist item information", [
                'release_id' => $release_id,
                'headers' => $headers
            ]);
            
            // Check for rate limiting or authorization issues
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (strpos($header, '429 Too Many Requests') !== false) {
                        return [
                            'error' => 'Rate limit exceeded. Please try again in a moment.'
                        ];
                    }
                    if (strpos($header, '401 Unauthorized') !== false) {
                        return [
                            'error' => 'Authentication error. Your Discogs credentials may have expired.'
                        ];
                    }
                }
            }
            
            return null;
        }
        
        $data = json_decode($response, true);
        if ($data === null) {
            $logger->error("Invalid JSON response for user's wantlist item", [
                'release_id' => $release_id
            ]);
            return null;
        }
        
        $logger->info("Successfully fetched user's wantlist item information", [
            'release_id' => $release_id,
            'response_size' => strlen($response)
        ]);
        
        return $data;
    } catch (Exception $e) {
        $logger->error("Error getting user's wantlist info: " . $e->getMessage(), [
            'release_id' => $release_id,
            'exception' => get_class($e)
        ]);
        return null;
    }
} 