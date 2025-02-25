<?php

require_once __DIR__ . '/../Functions/logging.php';

class WantlistController {
    private $twig;
    private $config;
    private $urlService;
    private $cacheService;
    private $authService;
    private $discogsService;
    private $logger;

    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/UrlService.php';
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/DiscogsService.php';
        require_once __DIR__ . '/../Services/WantlistCacheService.php';
        require_once __DIR__ . '/../Services/LogService.php';
        $this->urlService = new UrlService($config);
        $this->cacheService = new WantlistCacheService($config);
        $this->authService = new AuthService($config);
        $this->discogsService = new DiscogsService($config);
        $this->logger = LogService::getInstance($config);
    }

    /**
     * Show the wantlist gallery view
     */
    public function index() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        try {
            require_once __DIR__ . '/../Functions/get_wantlist.php';
            $wantlist = get_wantlist();
            
            if (isset($wantlist['error'])) {
                echo $this->twig->render('error.html.twig', [
                    'error' => $wantlist['error']
                ]);
                return;
            }

            $this->logger->info('Showing wantlist');
            
            echo $this->twig->render('wantlist_gallery.html.twig', [
                'wantlist' => $wantlist
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error showing wantlist: ' . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while loading your wantlist.'
            ]);
        }
    }

    /**
     * Show a single wantlist item
     * @param string $id Release ID
     * @param string $artist Artist name (optional)
     * @param string $title Release title (optional)
     */
    public function show($id, $artist = null, $title = null) {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        try {
            require_once __DIR__ . '/../Functions/get_wantlist_info.php';
            require_once __DIR__ . '/../Functions/get_my_wantlist_info.php';
            
            $wantlistInfo = get_wantlist_info($id);
            if (!$wantlistInfo || isset($wantlistInfo['error'])) {
                $this->logger->error('Wantlist item not found', ['id' => $id]);
                echo $this->twig->render('error.html.twig', [
                    'error' => $wantlistInfo['error'] ?? '404 Not Found'
                ]);
                return;
            }
            
            // If artist and title don't match, redirect to the correct URL
            $expectedArtist = '';
            $expectedTitle = '';
            
            if (isset($wantlistInfo['artists']) && is_array($wantlistInfo['artists']) && 
                !empty($wantlistInfo['artists']) && isset($wantlistInfo['artists'][0]['name'])) {
                $expectedArtist = $this->urlService->slugify($wantlistInfo['artists'][0]['name']);
            }
            
            if (isset($wantlistInfo['title'])) {
                $expectedTitle = $this->urlService->slugify($wantlistInfo['title']);
            }
            
            if ($expectedArtist && $expectedTitle && 
                (($artist !== null && $artist !== $expectedArtist) || 
                ($title !== null && $title !== $expectedTitle))) {
                header("Location: /wantlist/{$id}/{$expectedArtist}/{$expectedTitle}");
                exit;
            }
            
            $myWantlistInfo = get_my_wantlist_info($id);
            
            $this->logger->info('Showing wantlist item', [
                'id' => $id,
                'title' => $wantlistInfo['title'] ?? 'Unknown'
            ]);
            
            echo $this->twig->render('wantlist_item.html.twig', [
                'wantlistInfo' => $wantlistInfo,
                'myWantlistInfo' => $myWantlistInfo,
                'release_id' => $id
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error showing wantlist item: ' . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while loading the wantlist item.'
            ]);
        }
    }

    /**
     * Refreshes the user's wantlist from Discogs
     */
    public function refreshWantlist() {
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        $logger->info('User manually refreshed wantlist');
        
        // Force refresh by setting last updated timestamp to the past
        $_SESSION['wantlist_last_updated'] = 0;
        
        // Redirect to wantlist page which will trigger a fresh load
        header('Location: /wantlist');
        exit();
    }
} 