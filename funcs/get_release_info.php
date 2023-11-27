<?php
function get_release_information($release_id) {
    global $DISCOGS_API_URL, $DISCOGS_USERNAME,$DISCOGS_TOKEN, $context;
    // PULL DISCOGS REGARDING THE RELEASE IN MY COLLECTION
    $releasejson = $DISCOGS_API_URL
        . "/releases/"
        . $release_id
        . "?token=" .$DISCOGS_TOKEN;
    // put the contents of the JSON into a variable
    $releasedata = file_get_contents($releasejson, false, $context);
    // decode the JSON feed
    return json_decode($releasedata,true);
}