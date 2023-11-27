<?php
function get_my_release_information($release_id) {
    global $DISCOGS_API_URL, $DISCOGS_USERNAME,$DISCOGS_TOKEN, $context;
    // build the API request URL
    $myreleasejson = $DISCOGS_API_URL
        . "/users/"
        . $DISCOGS_USERNAME
        . "/collection/releases/"
        . $release_id
        . "?token="
        . $DISCOGS_TOKEN;
    // put the contents of the JSON into a variable
    $myreleasedata = file_get_contents($myreleasejson, false, $context);
    // decode the JSON feed
    return json_decode($myreleasedata,true);
}