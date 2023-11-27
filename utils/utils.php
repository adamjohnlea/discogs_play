<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// DISCOGS API SETTINGS
$DISCOGS_API_URL="https://api.discogs.com";
$DISCOGS_USERNAME="whiskyandvinylclub";
$DISCOGS_TOKEN="EYWYczmLePEsIFYcsdxulyOcuWyvdrtmkthabADj";

// DEFAULT VALUES FOR ATTRIBUTES
$folder_id = "0";
$sort_by = "added";
$order = "desc";
//$artistid = "";
$page = "1";
$per_page = "20";
$release_id = "";

// GET ATTRIBUTES FROM URL

if( isset($_GET['folder_id']) )
    $folder_id = $_GET['folder_id'];

if( isset($_GET['sort_by']) )
    $sort_by = $_GET['sort_by'];

if( isset($_GET['order']) )
    $order = $_GET['order'];

if( isset($_GET['page']) )
    $page = $_GET['page'];

//if(isset($_GET['artistid']))
//$artistid = $_GET['artistid'];

if ( isset($_GET['per_page']) )
    $per_page = $_GET['per_page'];

if ( isset($_GET['releaseid']) )
    $release_id = $_GET['releaseid'];

$options  = array('http' => array('user_agent' => 'DiscogsCollectionPage'));
$context  = stream_context_create($options);

// GET FOLDER DATA FOR NAVIGATION BAR
$folderjson = $DISCOGS_API_URL
    . "/users/"
    . $DISCOGS_USERNAME
    . "/collection/folders?token="
    . $DISCOGS_TOKEN;
// put the contents of the file into a variable
$folderdata = file_get_contents($folderjson, false, $context);
$folders = json_decode($folderdata,true); // decode the JSON feed

// Get name, ID and number of items of current folder.
foreach ($folders['folders'] as $folder) {
    if ($folder['id'] == $folder_id) {
        $current_folder_name = $folder['name'];
        $current_folder_count = $folder['count'];
    }
}