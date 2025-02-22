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
    private $logger;

    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/UrlService.php';
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/DiscogsService.php';
        require_once __DIR__ . '/../Services/FolderService.php';
        require_once __DIR__ . '/../Services/LogService.php';
        $this->urlService = new UrlService($config);
        $this->cacheService = new CacheService($config);
        $this->authService = new AuthService($config);
        $this->discogsService = new DiscogsService($config);
        $this->folderService = new FolderService($config);
        $this->logger = LogService::getInstance($config);
    }

    /**
     * Show a single release
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
            // Get user's Discogs username
            $userSettings = $this->discogsService->getUserCredentials($_SESSION['user_id']);
            $discogs_username = $userSettings['discogs_username'];

            require_once __DIR__ . '/../Functions/get_release_info.php';
            require_once __DIR__ . '/../Functions/get_my_release_info.php';
            
            $releaseInfo = get_release_info($id);
            if (!$releaseInfo || isset($releaseInfo['error'])) {
                $this->logger->error('Release not found', ['id' => $id]);
                echo $this->twig->render('error.html.twig', [
                    'error' => $releaseInfo['error'] ?? '404 Not Found'
                ]);
                return;
            }
            
            // If artist and title don't match, redirect to the correct URL
            $expectedArtist = $this->urlService->slugify($releaseInfo['artists'][0]['name']);
            $expectedTitle = $this->urlService->slugify($releaseInfo['title']);
            
            if (($artist !== null && $artist !== $expectedArtist) || 
                ($title !== null && $title !== $expectedTitle)) {
                header("Location: /release/{$id}/{$expectedArtist}/{$expectedTitle}");
                exit;
            }
            
            $myReleaseInfo = get_my_release_information($id);
            
            $this->logger->info('Showing release', [
                'id' => $id,
                'title' => $releaseInfo['title'] ?? 'Unknown'
            ]);
            
            echo $this->twig->render('release.html.twig', [
                'releaseInfo' => $releaseInfo,
                'myReleaseInfo' => $myReleaseInfo,
                'release_id' => $id,
                'discogs_username' => $discogs_username
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error showing release: ' . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while loading the release.'
            ]);
        }
    }
} 