<?php
function get_my_release_information($release_id) {
    global $DISCOGS_API_URL, $DISCOGS_USERNAME, $DISCOGS_TOKEN, $context;
    
    if (!$release_id) {
        return null;
    }

    try {
        // build the API request URL
        $myreleasejson = $DISCOGS_API_URL . "/users/" . $DISCOGS_USERNAME . "/collection/releases/" . $release_id;
        
        // put the contents of the JSON into a variable
        $myreleasedata = @file_get_contents($myreleasejson, false, $context);
        
        if ($myreleasedata === false) {
            return null;
        }
        
        // decode the JSON feed
        $data = json_decode($myreleasedata, true);
        return $data ?: null;
    } catch (Exception $e) {
        return null;
    }
}