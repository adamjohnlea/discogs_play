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
    // put the contents of the JSON into a variable
    $pagedata = file_get_contents($pagejson, false, $context);
    // decode the JSON feed
    return json_decode($pagedata,true);
}