<?php

class ReleaseController {
    private $twig;
    private $config;
    private $urlService;
    private $cacheService;
    private $authService;
    private $discogsService;

    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/UrlService.php';
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/DiscogsService.php';
        $this->urlService = new UrlService($config);
        $this->cacheService = new CacheService($config);
        $this->authService = new AuthService($config);
        $this->discogsService = new DiscogsService($config);
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
        $cacheStatus = [];

        try {
            // Get user's Discogs username
            $userSettings = $this->discogsService->getUserCredentials($_SESSION['user_id']);
            $discogs_username = $userSettings['discogs_username'];

            // Check cache first
            $cachedData = $this->cacheService->getCachedRelease($release_id);
            if ($cachedData && !$cachedData['is_basic_data'] && $this->cacheService->isReleaseCacheValid($release_id)) {
                $releaseInfo = $cachedData['data'];
                $myReleaseInfo = $cachedData['my_data'];
                $cacheStatus['release'] = 'Using stored data';
                $cacheStatus['cache_time'] = $cachedData['last_updated'];
            } else {
                // If not in cache or cache is invalid, fetch from API
                $cacheStatus['release'] = 'Fetching and storing new data';
                if ($cachedData && $cachedData['is_basic_data']) {
                    $cacheStatus['reason'] = 'Basic data found, fetching full data';
                } else if (!$cachedData) {
                    $cacheStatus['reason'] = 'No cache entry found';
                } else {
                    $cacheStatus['reason'] = 'Invalid cache';
                }
                
                $releaseInfo = get_release_information($release_id);
                if (!$releaseInfo) {
                    echo $this->twig->render('error.html.twig', [
                        'error' => '404 Not Found'
                    ]);
                    return;
                }
                
                $myReleaseInfo = get_my_release_information($release_id);
                
                // Store the data permanently
                $this->cacheService->cacheRelease($release_id, $releaseInfo, $myReleaseInfo, false);
            }

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
} 