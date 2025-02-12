<?php
function get_release_information($release_id) {
    global $DISCOGS_API_URL, $DISCOGS_USERNAME, $DISCOGS_TOKEN, $context;
    
    if (!$release_id) {
        return null;
    }

    try {
        // PULL DISCOGS REGARDING THE RELEASE IN MY COLLECTION
        $releasejson = $DISCOGS_API_URL . "/releases/" . $release_id;
        
        // put the contents of the JSON into a variable
        $releasedata = @file_get_contents($releasejson, false, $context);
        
        if ($releasedata === false) {
            return null;
        }
        
        // decode the JSON feed
        $data = json_decode($releasedata, true);
        return $data ?: null;
    } catch (Exception $e) {
        return null;
    }
}