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
    private $db;

    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/UrlService.php';
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/DiscogsService.php';
        require_once __DIR__ . '/../Services/WantlistCacheService.php';
        require_once __DIR__ . '/../Services/LogService.php';
        require_once __DIR__ . '/../Services/DatabaseService.php';
        $this->urlService = new UrlService($config);
        $this->cacheService = new WantlistCacheService($config);
        $this->authService = new AuthService($config);
        $this->discogsService = new DiscogsService($config);
        $this->logger = LogService::getInstance($config);
        $this->db = DatabaseService::getInstance($config)->getConnection();
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
            // Check if user has set up their Discogs OAuth credentials
            $stmt = $this->db->prepare("
                SELECT discogs_username, oauth_access_token 
                FROM user_settings 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings || empty($settings['discogs_username']) || empty($settings['oauth_access_token'])) {
                // Redirect to settings if OAuth credentials are not set
                header('Location: /settings');
                exit;
            }
            
            // Set a longer timeout for the API request
            ini_set('default_socket_timeout', 30); // 30 seconds timeout
            
            require_once __DIR__ . '/../Functions/get_wantlist.php';
            
            // Wrap the get_wantlist call in a try/catch to handle any API errors
            try {
                $wantlist = get_wantlist();
                
                if (isset($wantlist['error'])) {
                    $this->logger->error('Error loading wantlist: ' . $wantlist['error']);
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
                $this->logger->error('Error in get_wantlist: ' . $e->getMessage());
                echo $this->twig->render('error.html.twig', [
                    'error' => 'An error occurred while loading your wantlist: ' . $e->getMessage()
                ]);
                return;
            }
        } catch (Exception $e) {
            $this->logger->error('Error showing wantlist: ' . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while loading your wantlist: ' . $e->getMessage()
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
            // Check if user has set up their Discogs OAuth credentials
            $stmt = $this->db->prepare("
                SELECT discogs_username, oauth_access_token 
                FROM user_settings 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings || empty($settings['discogs_username']) || empty($settings['oauth_access_token'])) {
                // Redirect to settings if OAuth credentials are not set
                header('Location: /settings');
                exit;
            }
            
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
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        try {
            // Check if user has set up their Discogs OAuth credentials
            $stmt = $this->db->prepare("
                SELECT discogs_username, oauth_access_token 
                FROM user_settings 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings || empty($settings['discogs_username']) || empty($settings['oauth_access_token'])) {
                // Redirect to settings if OAuth credentials are not set
                header('Location: /settings');
                exit;
            }

            $this->logger->info('User manually refreshed wantlist');
            
            // Force refresh by setting last updated timestamp to the past
            $_SESSION['wantlist_last_updated'] = 0;
            
            // Redirect to wantlist page which will trigger a fresh load
            header('Location: /wantlist');
            exit();
        } catch (Exception $e) {
            $this->logger->error('Error refreshing wantlist: ' . $e->getMessage());
            header('Location: /settings');
            exit;
        }
    }
} 