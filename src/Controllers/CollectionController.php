<?php

class CollectionController {
    private $twig;
    private $config;
    private $authService;
    private $folderService;
    private $discogsService;
    private $cacheService;
    private $logger;
    private $db;

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
        $this->db = DatabaseService::getInstance($config)->getConnection();

        // Ensure search index is populated
        $this->ensureSearchIndex();
    }

    /**
     * Ensures the search index is populated if we have collection data
     */
    private function ensureSearchIndex() {
        if (!$this->authService->isLoggedIn()) {
            return;
        }

        try {
            // Check if we have any entries in the search index
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM collection_search 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $count = $stmt->fetchColumn();

            if ($count === 0) {
                // Use the full collection cache key for search
                $fullCacheKey = "collection_{$_SESSION['user_id']}_0_added_desc_1_999999";
                $collection = $this->cacheService->getCachedCollection($fullCacheKey);
                
                if (!$collection || !isset($collection['releases'])) {
                    // If full collection is not cached, fetch it from Discogs
                    $url = $this->discogsService->buildUrl(
                        "/users/:username/collection/folders/0/releases",
                        $_SESSION['user_id']
                    );
                    $url .= "?sort=added&sort_order=desc&page=1&per_page=999999";
                    
                    $context = $this->discogsService->getApiContext($_SESSION['user_id']);
                    $response = @file_get_contents($url, false, $context);
                    
                    if ($response === false) {
                        $this->logger->error("Failed to fetch full collection from Discogs");
                        return;
                    }
                    
                    $collection = json_decode($response, true);
                    if (!$collection || !isset($collection['releases'])) {
                        $this->logger->error("Invalid collection data from Discogs");
                        return;
                    }
                    
                    // Add user_id to collection data
                    $collection['user_id'] = $_SESSION['user_id'];
                    
                    // Cache the full collection
                    $this->cacheService->cacheCollection($fullCacheKey, $collection);
                }
                
                if ($collection && isset($collection['releases'])) {
                    $this->updateSearchIndex($_SESSION['user_id'], $collection['releases']);
                    $this->logger->info("Search index initialized", [
                        'releases_indexed' => count($collection['releases'])
                    ]);
                } else {
                    $this->logger->error("Failed to get collection data");
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to ensure search index: " . $e->getMessage());
        }
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
        
        try {
            // Get collection data
            $collection = get_collection();
            $folders = get_folders();
            
            // Get folder ID from slug
            $folderId = $this->folderService->getFolderId($folder);

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

        $query = strtolower($_GET['q'] ?? '');
        $folder = $_GET['folder'] ?? 'all';
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = max(1, intval($_GET['per_page'] ?? 25));

        try {
            $folders = get_folders();
            $folderId = $this->folderService->getFolderId($folder);
            
            // First get ALL matching release IDs from search index
            $stmt = $this->db->prepare("
                SELECT DISTINCT release_id 
                FROM collection_search 
                WHERE user_id = :user_id 
                AND (
                    LOWER(title) LIKE :query 
                    OR LOWER(artist) LIKE :query 
                    OR LOWER(label) LIKE :query
                )
            ");

            $searchQuery = '%' . $query . '%';
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':query' => $searchQuery
            ]);

            $allMatchingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $total = count($allMatchingIds);

            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $pageMatchingIds = array_slice($allMatchingIds, $offset, $perPage);

            // Get the FULL collection data using CacheService
            $cacheKey = "collection_{$_SESSION['user_id']}_0_added_desc_1_999999";
            $fullCollection = $this->cacheService->getCachedCollection($cacheKey);
            
            if (!$fullCollection || !isset($fullCollection['releases'])) {
                throw new Exception('Failed to get collection data');
            }

            // Create a lookup array for faster matching
            $matchingReleases = [];
            foreach ($fullCollection['releases'] as $release) {
                if (in_array($release['id'], $pageMatchingIds)) {
                    $matchingReleases[] = $release;
                }
            }

            // Create the collection array with our filtered results
            $collection = [
                'releases' => $matchingReleases,
                'pagination' => [
                    'items' => $total,
                    'page' => $page,
                    'pages' => ceil($total / $perPage),
                    'per_page' => $perPage
                ]
            ];

            $this->logger->info("Search complete", [
                'query' => $query,
                'matches' => $total,
                'page_matches' => count($matchingReleases)
            ]);

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

    /**
     * Updates the search index for a user's collection
     */
    private function updateSearchIndex($userId, $releases) {
        try {
            // Start transaction
            $this->db->beginTransaction();

            // Clear existing entries for this user
            $stmt = $this->db->prepare("
                DELETE FROM collection_search WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);

            // Prepare insert statement
            $stmt = $this->db->prepare("
                INSERT INTO collection_search 
                (release_id, user_id, title, artist, label)
                VALUES (:release_id, :user_id, :title, :artist, :label)
            ");

            foreach ($releases as $release) {
                $basic = $release['basic_information'];
                $artists = array_map(function($a) { 
                    return $a['name']; 
                }, $basic['artists']);
                
                $labels = array_map(function($l) { 
                    return $l['name']; 
                }, $basic['labels'] ?? []);

                $stmt->execute([
                    ':release_id' => $release['id'],
                    ':user_id' => $userId,
                    ':title' => strtolower($basic['title']),
                    ':artist' => strtolower(implode(', ', $artists)),
                    ':label' => strtolower(implode(', ', $labels))
                ]);
            }

            // Commit transaction
            $this->db->commit();
            
            $this->logger->info("Search index updated", [
                'user_id' => $userId,
                'releases_indexed' => count($releases)
            ]);

        } catch (Exception $e) {
            // Rollback on error
            $this->db->rollBack();
            $this->logger->error("Failed to update search index: " . $e->getMessage());
            throw $e;
        }
    }
} 