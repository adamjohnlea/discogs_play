<?php

require_once __DIR__ . '/../Functions/logging.php';

class ReleaseController {
    private $twig;
    private $config;
    private $urlService;
    private $cacheService;
    private $authService;
    private $discogsService;
    private $folderService;

    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/UrlService.php';
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/DiscogsService.php';
        require_once __DIR__ . '/../Services/FolderService.php';
        $this->urlService = new UrlService($config);
        $this->cacheService = new CacheService($config);
        $this->authService = new AuthService($config);
        $this->discogsService = new DiscogsService($config);
        $this->folderService = new FolderService($config);
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
        if (!$this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }

        if (!$release_id) {
            header("Location: /");
            exit;
        }

        $releaseInfo = null;
        $myReleaseInfo = null;

        try {
            // Get user's Discogs username
            $userSettings = $this->discogsService->getUserCredentials($_SESSION['user_id']);
            $discogs_username = $userSettings['discogs_username'];

            require_once __DIR__ . '/../Functions/get_release_info.php';
            require_once __DIR__ . '/../Functions/get_my_release_info.php';
            
            $releaseInfo = get_release_info($release_id);
            if (!$releaseInfo || isset($releaseInfo['error'])) {
                echo $this->twig->render('error.html.twig', [
                    'error' => $releaseInfo['error'] ?? '404 Not Found'
                ]);
                return;
            }
            
            $myReleaseInfo = get_my_release_information($release_id);
            
            echo $this->twig->render('release.html.twig', [
                'releaseInfo' => $releaseInfo,
                'myReleaseInfo' => $myReleaseInfo,
                'release_id' => $release_id,
                'discogs_username' => $discogs_username,
                'current_folder_name' => null,
                'current_folder_count' => null,
                'sort_by' => null,
                'order' => null
            ]);
        } catch (Exception $e) {
            error_log("Error showing release: " . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while loading the release.'
            ]);
        }
    }

    public function showCollection() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }

        $collection = get_collection();
        $folders = get_folders();
        
        // Get the folder slug from the URL parameters or default to 'all'
        $folder = $GLOBALS['folder'] ?? 'all';
        
        echo $this->twig->render('release_gallery.html.twig', [
            'collection' => $collection,
            'folders' => $folders,
            'page' => $GLOBALS['page'] ?? 1,
            'folder_id' => $GLOBALS['folder_id'] ?? '0',
            'folder' => $folder,
            'sort_by' => $GLOBALS['sort_by'] ?? 'added',
            'order' => $GLOBALS['order'] ?? 'desc',
            'per_page' => $GLOBALS['per_page'] ?? 25
        ]);
    }

    public function searchCollection($folder = 'all', $query = '', $page = 1) {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /');
            exit;
        }

        // Override pagination for initial collection fetch to get all items
        $original_per_page = $GLOBALS['per_page'] ?? 25;
        $original_page = $GLOBALS['page'] ?? 1;
        $GLOBALS['per_page'] = 999999; // Large number to get all items
        $GLOBALS['page'] = 1;

        $collection = get_collection();
        $folders = get_folders();

        // Restore original pagination values
        $GLOBALS['per_page'] = $original_per_page;
        $GLOBALS['page'] = $original_page;
        
        // Get folder ID from slug
        $folder_id = $this->folderService->getFolderId($folder);
        
        // Filter the collection based on search query
        if ($collection && isset($collection['releases'])) {
            $query = strtolower($query); // Convert query to lowercase once
            log_message("Starting search for query: " . $query, 'info');
            log_message("Total releases to search through: " . count($collection['releases']), 'info');
            
            $filtered = array_filter($collection['releases'], function($release) use ($query) {
                $basic = $release['basic_information'];
                log_message("Checking release: " . ($basic['title'] ?? 'Unknown Title'), 'debug');
                
                // Search in title
                if (isset($basic['title'])) {
                    if (stripos($basic['title'], $query) !== false) {
                        log_message("Match found in title: " . $basic['title'], 'info');
                        return true;
                    }
                }
                
                // Search in all artist names
                if (isset($basic['artists']) && is_array($basic['artists'])) {
                    foreach ($basic['artists'] as $artist) {
                        if (isset($artist['name'])) {
                            if (stripos($artist['name'], $query) !== false) {
                                log_message("Match found in artist: " . $artist['name'], 'info');
                                return true;
                            }
                        }
                    }
                }
                
                // Search in all label names
                if (isset($basic['labels']) && is_array($basic['labels'])) {
                    foreach ($basic['labels'] as $label) {
                        if (isset($label['name'])) {
                            if (stripos($label['name'], $query) !== false) {
                                log_message("Match found in label: " . $label['name'], 'info');
                                return true;
                            }
                        }
                    }
                }
                
                return false;
            });
            
            // Update pagination
            $per_page = $GLOBALS['per_page'] ?? 25;
            $total = count($filtered);
            log_message("Search complete. Found " . $total . " matches for '" . $query . "'", 'info');
            
            $total_pages = ceil($total / $per_page);
            $offset = ($page - 1) * $per_page;
            
            $collection['releases'] = array_slice(array_values($filtered), $offset, $per_page);
            $collection['pagination'] = [
                'items' => $total,
                'page' => $page,
                'pages' => $total_pages,
                'per_page' => $per_page
            ];
        }

        // Use exact same template and parameters as showCollection
        echo $this->twig->render('release_gallery.html.twig', [
            'collection' => $collection,
            'folders' => $folders,
            'page' => $page,
            'folder_id' => $folder_id,
            'folder' => $folder,
            'sort_by' => $GLOBALS['sort_by'] ?? 'added',
            'order' => $GLOBALS['order'] ?? 'desc',
            'per_page' => $GLOBALS['per_page'] ?? 25,
            'search_query' => $query // Only new parameter added
        ]);
    }
} 