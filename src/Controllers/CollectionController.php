<?php

class CollectionController {
    private $twig;
    private $config;
    private $authService;
    private $folderService;
    private $discogsService;
    private $cacheService;
    private $logger;

    public function __construct($twig, $config) {
        $this->twig = $twig;
        $this->config = $config;
        require_once __DIR__ . '/../Services/AuthService.php';
        require_once __DIR__ . '/../Services/FolderService.php';
        require_once __DIR__ . '/../Services/DiscogsService.php';
        require_once __DIR__ . '/../Services/CacheService.php';
        require_once __DIR__ . '/../Services/LogService.php';
        
        $this->authService = new AuthService($config);
        $this->folderService = new FolderService($config);
        $this->discogsService = new DiscogsService($config);
        $this->cacheService = new CacheService($config);
        $this->logger = LogService::getInstance($config);
    }

    /**
     * Main collection view with support for filtering, sorting, and pagination
     */
    public function index() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        // Get query parameters with defaults
        $folder = $_GET['folder'] ?? 'all';
        $sort = $_GET['sort'] ?? 'added';
        $order = $_GET['order'] ?? 'desc';
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = max(1, intval($_GET['per_page'] ?? 25));

        // Store current state in globals for backward compatibility
        // @deprecated - Will be removed when legacy routes are removed
        $GLOBALS['folder'] = $folder;
        $GLOBALS['page'] = $page;
        $GLOBALS['sort_by'] = $sort;
        $GLOBALS['order'] = $order;
        $GLOBALS['per_page'] = $perPage;
        
        try {
            // Get collection data
            $collection = get_collection();
            $folders = get_folders();
            
            // Get folder ID from slug
            $folderId = $this->folderService->getFolderId($folder);
            $GLOBALS['folder_id'] = $folderId;

            $this->logger->info('Showing collection', [
                'folder' => $folder,
                'folder_id' => $folderId,
                'page' => $page,
                'sort' => $sort,
                'order' => $order
            ]);

            echo $this->twig->render('release_gallery.html.twig', [
                'collection' => $collection,
                'folders' => $folders,
                'page' => $page,
                'folder_id' => $folderId,
                'folder' => $folder,
                'sort_by' => $sort,
                'order' => $order,
                'per_page' => $perPage
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error showing collection: ' . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while loading the collection.'
            ]);
        }
    }

    /**
     * Search functionality with support for folder filtering and pagination
     */
    public function search() {
        if (!$this->authService->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $query = $_GET['q'] ?? '';
        $folder = $_GET['folder'] ?? 'all';
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = max(1, intval($_GET['per_page'] ?? 25));

        // Store current state in globals for backward compatibility
        // @deprecated - Will be removed when legacy routes are removed
        $GLOBALS['folder'] = $folder;
        $GLOBALS['page'] = $page;
        $GLOBALS['per_page'] = $perPage;

        try {
            // Override pagination for initial collection fetch to get all items
            $GLOBALS['per_page'] = 999999; // Large number to get all items
            $GLOBALS['page'] = 1;

            $collection = get_collection();
            $folders = get_folders();

            // Restore original pagination values
            $GLOBALS['per_page'] = $perPage;
            $GLOBALS['page'] = $page;
            
            // Get folder ID from slug
            $folderId = $this->folderService->getFolderId($folder);
            $GLOBALS['folder_id'] = $folderId;

            // Filter the collection based on search query
            if ($collection && isset($collection['releases'])) {
                $query = strtolower($query);
                $this->logger->info("Starting search", [
                    'query' => $query,
                    'total_releases' => count($collection['releases'])
                ]);

                $filtered = array_filter($collection['releases'], function($release) use ($query) {
                    $basic = $release['basic_information'];
                    
                    // Search in title
                    if (isset($basic['title'])) {
                        if (stripos($basic['title'], $query) !== false) {
                            return true;
                        }
                    }
                    
                    // Search in all artist names
                    if (isset($basic['artists']) && is_array($basic['artists'])) {
                        foreach ($basic['artists'] as $artist) {
                            if (isset($artist['name'])) {
                                if (stripos($artist['name'], $query) !== false) {
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
                                    return true;
                                }
                            }
                        }
                    }
                    
                    return false;
                });

                $total = count($filtered);
                $this->logger->info("Search complete", [
                    'query' => $query,
                    'matches' => $total
                ]);

                $total_pages = ceil($total / $perPage);
                $offset = ($page - 1) * $perPage;

                $collection['releases'] = array_slice(array_values($filtered), $offset, $perPage);
                $collection['pagination'] = [
                    'items' => $total,
                    'page' => $page,
                    'pages' => $total_pages,
                    'per_page' => $perPage
                ];
            }

            echo $this->twig->render('release_gallery.html.twig', [
                'collection' => $collection,
                'folders' => $folders,
                'page' => $page,
                'folder_id' => $folderId,
                'folder' => $folder,
                'sort_by' => 'added',
                'order' => 'desc',
                'per_page' => $perPage,
                'search_query' => $query
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error searching collection: ' . $e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'error' => 'An error occurred while searching the collection.'
            ]);
        }
    }
} 