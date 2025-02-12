<?php

class ReleaseController {
    private $config;
    private $twig;

    public function __construct($config) {
        $this->config = $config;
        $this->twig = TwigConfig::getInstance($config);
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
        
        $releaseInfo = get_release_information($release_id);
        if (!$releaseInfo) {
            return $this->twig->render('error.html.twig', [
                'error' => '404 Not Found'
            ]);
        }
        
        $myReleaseInfo = get_my_release_information($release_id);
        
        // Get global variables from utils.php
        global $DISCOGS_USERNAME;
        
        return $this->twig->render('release.html.twig', [
            'releaseInfo' => $releaseInfo,
            'myReleaseInfo' => $myReleaseInfo,
            'release_id' => $release_id,
            'discogs_username' => $DISCOGS_USERNAME,
            'current_folder_name' => null, // These are needed for the banner template
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