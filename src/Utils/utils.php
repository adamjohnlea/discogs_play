<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// DISCOGS API SETTINGS
$DISCOGS_API_URL = "https://api.discogs.com";
$DISCOGS_USERNAME = "aj_vinyl_and_music";
$DISCOGS_TOKEN = "TMAQAtqQwNUhdEUKmoYBKkakNqKFdqLuMUfZFyAE";

// DEFAULT VALUES FOR ATTRIBUTES
$folder_id = "0";
$sort_by = "added";
$order = "desc";
//$artistid = "";
$page = "1";
$per_page = "24";
$release_id = "";

// GET ATTRIBUTES FROM URL
if (isset($_GET['folder_id'])) {
    $folder_id = $_GET['folder_id'];
}

if (isset($_GET['sort_by'])) {
    $sort_by = $_GET['sort_by'];
}

if (isset($_GET['order'])) {
    $order = $_GET['order'];
}

if (isset($_GET['page'])) {
    $page = $_GET['page'];
}

//if(isset($_GET['artistid']))
//$artistid = $_GET['artistid'];

if (isset($_GET['per_page'])) {
    $per_page = $_GET['per_page'];
}

if (isset($_GET['releaseid'])) {
    $release_id = $_GET['releaseid'];
}

// Set up API request options
$options = array(
    'http' => array(
        'user_agent' => 'DiscogsCollectionPage',
        'header' => array(
            'Authorization: Discogs token=' . $DISCOGS_TOKEN
        )
    )
);
$context = stream_context_create($options);

function get_folders() {
    global $config;
    
    if (!isset($_SESSION['user_id'])) {
        return ['folders' => []];
    }
    
    require_once __DIR__ . '/../Services/DiscogsService.php';
    require_once __DIR__ . '/../Services/CacheService.php';
    $discogsService = new DiscogsService($config);
    $cacheService = new CacheService($config);
    
    try {
        // Check cache first
        $cachedFolders = $cacheService->getCachedFolders($_SESSION['user_id']);
        if ($cachedFolders && $cacheService->isFoldersCacheValid($_SESSION['user_id'])) {
            return $cachedFolders['data'];
        }
        
        // If not in cache or cache invalid, fetch from API
        $url = $discogsService->buildUrl('/users/:username/collection/folders', $_SESSION['user_id']);
        $context = $discogsService->getApiContext($_SESSION['user_id']);
        
        $folderdata = @file_get_contents($url, false, $context);
        if ($folderdata === false) {
            return ['folders' => []];
        }
        
        $data = json_decode($folderdata, true) ?: ['folders' => []];
        
        // Cache the folder data
        $cacheService->cacheFolders($_SESSION['user_id'], $data);
        
        return $data;
    } catch (Exception $e) {
        error_log("Error getting folders: " . $e->getMessage());
        return ['folders' => []];
    }
}

// Get folder data for navigation bar
$folders = get_folders();

// Get name, ID and number of items of current folder
$current_folder_name = '';
$current_folder_count = 0;

if (isset($folders['folders']) && is_array($folders['folders'])) {
    foreach ($folders['folders'] as $folder) {
        if ($folder['id'] == $folder_id) {
            $current_folder_name = $folder['name'];
            $current_folder_count = $folder['count'];
            break;
        }
    }
}