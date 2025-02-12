<?php

class ReleaseController {
    private $config;
    private $twig;
    private $urlService;
    private $cacheService;

    public function __construct($config) {
        $this->config = $config;
        $this->twig = TwigConfig::getInstance($config);
        require_once __DIR__ . '/../Services/UrlService.php';
        $this->urlService = new UrlService($config);
        $this->cacheService = new CacheService($config);
    }

    public function handleRequest() {
        // Check if we have a release ID in the query parameters
        $release_id = $_GET['releaseid'] ?? null;
        
        if ($release_id) {
            return $this->showRelease($release_id);
        }
        
        return $this->showCollection();
    }

    public function showRelease($release_id = null) {
        if (!$release_id) {
            header("Location: /");
            exit;
        }

        $releaseInfo = null;
        $myReleaseInfo = null;
        $cacheStatus = [];

        // Check cache first
        $cachedData = $this->cacheService->getCachedRelease($release_id);
        if ($cachedData && $this->cacheService->isReleaseCacheValid($release_id)) {
            $releaseInfo = $cachedData['data'];
            $myReleaseInfo = $cachedData['my_data'];
            $cacheStatus['release'] = 'Using stored data';
            $cacheStatus['cache_time'] = $cachedData['last_updated'];
        } else {
            // If not in cache or cache is invalid, fetch from API
            $cacheStatus['release'] = 'Fetching and storing new data';
            $releaseInfo = get_release_information($release_id);
            if (!$releaseInfo) {
                return $this->twig->render('error.html.twig', [
                    'error' => '404 Not Found'
                ]);
            }
            
            $myReleaseInfo = get_my_release_information($release_id);
            
            // Store the data permanently
            $this->cacheService->cacheRelease($release_id, $releaseInfo, $myReleaseInfo);
        }

        // Get global variables from utils.php
        global $DISCOGS_USERNAME;

        // Check image cache status
        if (isset($releaseInfo['images'][0])) {
            $firstImage = $releaseInfo['images'][0];
            $cachedImage = $this->cacheService->getCachedImage($release_id, 'cover', $firstImage['resource_url']);
            if ($cachedImage) {
                $cacheStatus['cover_image'] = 'Using stored image';
                $cacheStatus['image_cache_time'] = $cachedImage['last_updated'];
            } else {
                $cacheStatus['cover_image'] = 'Storing new image';
            }
        }
        
        return $this->twig->render('release.html.twig', [
            'releaseInfo' => $releaseInfo,
            'myReleaseInfo' => $myReleaseInfo,
            'release_id' => $release_id,
            'discogs_username' => $DISCOGS_USERNAME,
            'current_folder_name' => null,
            'current_folder_count' => null,
            'sort_by' => null,
            'order' => null
        ]);
    }

    public function showCollection() {
        // Get global variables from utils.php
        global $DISCOGS_USERNAME, $folder_id, $sort_by, $order, $page, $per_page, $folders, 
               $current_folder_name, $current_folder_count;
        
        $collection = get_collection();
        if (!$collection) {
            return $this->twig->render('error.html.twig', [
                'error' => 'Failed to load collection'
            ]);
        }

        // Update folder mappings if we have folders
        if (isset($folders['folders'])) {
            $this->urlService->updateFolderMappings($folders['folders']);
        }
        
        return $this->twig->render('release_gallery.html.twig', [
            'collection' => $collection,
            'discogs_username' => $DISCOGS_USERNAME,
            'folder_id' => $folder_id,
            'sort_by' => $sort_by,
            'order' => $order,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'folders' => $folders,
            'current_folder_name' => $current_folder_name,
            'current_folder_count' => $current_folder_count,
            'release_id' => null // This is needed for the banner template
        ]);
    }
} 