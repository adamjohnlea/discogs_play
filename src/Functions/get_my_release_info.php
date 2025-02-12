<?php
function get_my_release_information($release_id) {
    global $config;
    
    if (!$release_id) {
        return null;
    }

    try {
        // Build the API URL for user's collection
        $url = $config['discogs']['api_url'] . "/users/" . $config['discogs']['username'] . "/collection/releases/" . $release_id;
        
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
            error_log("Failed to fetch user's release $release_id from Discogs API");
            return null;
        }
        
        // Parse the JSON response
        $data = json_decode($response, true);
        
        if (!$data) {
            error_log("Failed to parse JSON response for user's release $release_id");
            return null;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error fetching user's release $release_id: " . $e->getMessage());
        return null;
    }
}