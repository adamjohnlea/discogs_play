<?php
function get_collection() {
    global $DISCOGS_API_URL, $DISCOGS_USERNAME, $folder_id, $sort_by, $order, $page;
    global $per_page, $DISCOGS_TOKEN, $context;
    $pagejson = $DISCOGS_API_URL
        . "/users/"
        . $DISCOGS_USERNAME
        . "/collection/folders/"
        . $folder_id
        . "/releases?sort="
        . $sort_by
        . "&sort_order="
        . $order
        . "&page="
        . $page
        . "&per_page="
        . $per_page
        . "&token="
        . $DISCOGS_TOKEN;

    try {
        // put the contents of the JSON into a variable
        $pagedata = @file_get_contents($pagejson, false, $context);
        
        if ($pagedata === false) {
            $error = error_get_last();
            error_log("Discogs API Error: " . ($error['message'] ?? 'Unknown error'));
            
            // Check if we got rate limited
            $headers = get_headers($pagejson, 1);
            if (isset($headers[0]) && strpos($headers[0], '429') !== false) {
                error_log("Rate limited by Discogs API");
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
            error_log("Failed to parse JSON response from Discogs API");
            return [
                'error' => 'Invalid response from Discogs. Please try again.',
                'releases' => [],
                'pagination' => ['pages' => 1]
            ];
        }

        return $data;
    } catch (Exception $e) {
        error_log("Exception in get_collection: " . $e->getMessage());
        return [
            'error' => 'An error occurred. Please try again.',
            'releases' => [],
            'pagination' => ['pages' => 1]
        ];
    }
}