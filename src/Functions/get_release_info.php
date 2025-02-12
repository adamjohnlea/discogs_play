<?php

require_once __DIR__ . '/../Services/LogService.php';

function get_release_information($release_id) {
    global $config;
    $logger = LogService::getInstance($config);
    
    if (!$release_id) {
        $logger->error("No release ID provided");
        return null;
    }

    try {
        // Build the API URL
        $url = $config['discogs']['api_url'] . "/releases/" . $release_id;
        $logger->info("Fetching release from Discogs API", ['url' => $url]);
        
        // Set up the request context with authentication and user agent
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . $config['discogs']['user_agent'],
                    'Authorization: Discogs token=' . $config['discogs']['token']
                ]
            ]
        ];
        
        $context = stream_context_create($opts);
        
        // Make the API request
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $logger->error("Failed to fetch release from Discogs API", [
                'release_id' => $release_id,
                'url' => $url,
                'context' => $opts
            ]);
            return null;
        }
        
        // Log the raw response for debugging
        $logger->debug("Received API response", [
            'release_id' => $release_id,
            'response_preview' => substr($response, 0, 1000) . '...'
        ]);
        
        // Parse the JSON response
        $data = json_decode($response, true);
        
        if (!$data) {
            $logger->error("Failed to parse JSON response", [
                'release_id' => $release_id,
                'json_error' => json_last_error_msg()
            ]);
            return null;
        }
        
        // Log the parsed data structure
        $logger->debug("Parsed release data structure", [
            'release_id' => $release_id,
            'keys' => array_keys($data)
        ]);
        
        return $data;
    } catch (Exception $e) {
        $logger->error("Exception while fetching release", [
            'release_id' => $release_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return null;
    }
}