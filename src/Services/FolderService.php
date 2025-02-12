<?php

class FolderService {
    private $config;
    private $discogsService;
    private $folderMap = [
        'all' => '0',
        'uncategorized' => '1'
    ];
    private $slugMap = [];

    public function __construct($config) {
        $this->config = $config;
        require_once __DIR__ . '/DiscogsService.php';
        $this->discogsService = new DiscogsService($config);
        
        // Initialize folder mappings
        $folders = $this->getFolders();
        if ($folders && isset($folders['folders'])) {
            $this->updateFolderMappings($folders['folders']);
        }
    }

    /**
     * Get folder ID from slug
     */
    public function getFolderId($slug) {
        // First check our static map
        if (isset($this->folderMap[$slug])) {
            return $this->folderMap[$slug];
        }

        // Then check our dynamic map
        if (isset($this->slugMap[$slug])) {
            return $this->slugMap[$slug]['id'];
        }

        return '0'; // Default to 'all' folder
    }

    /**
     * Get folder slug from ID
     */
    public function getFolderSlug($id, $name = null) {
        // Check static map first
        $staticSlug = array_search($id, $this->folderMap);
        if ($staticSlug !== false) {
            return $staticSlug;
        }

        // If we have a name, create a slug
        if ($name) {
            $slug = $this->slugify($name);
            $this->slugMap[$slug] = [
                'id' => $id,
                'name' => $name
            ];
            return $slug;
        }

        return 'all'; // Default to 'all' folder
    }

    /**
     * Update folder mappings with current folder data
     */
    public function updateFolderMappings($folders) {
        foreach ($folders as $folder) {
            $slug = $this->slugify($folder['name']);
            $this->slugMap[$slug] = [
                'id' => $folder['id'],
                'name' => $folder['name']
            ];
        }
    }

    /**
     * Convert a string to a URL-friendly slug
     */
    private function slugify($text) {
        // Convert to lowercase
        $text = strtolower($text);
        // Replace spaces with hyphens
        $text = str_replace(' ', '-', $text);
        // Remove any remaining non-alphanumeric characters except hyphens
        $text = preg_replace('/[^a-z0-9-]/', '', $text);
        // Remove multiple consecutive hyphens
        $text = preg_replace('/-+/', '-', $text);
        // Remove leading and trailing hyphens
        $text = trim($text, '-');
        return $text;
    }

    /**
     * Get folders from Discogs API
     */
    private function getFolders() {
        if (!isset($_SESSION['user_id'])) {
            return ['folders' => []];
        }

        try {
            $url = $this->discogsService->buildUrl('/users/:username/collection/folders', $_SESSION['user_id']);
            $context = $this->discogsService->getApiContext($_SESSION['user_id']);
            
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['folders' => []];
            }
            return json_decode($response, true) ?: ['folders' => []];
        } catch (Exception $e) {
            error_log("Error getting folders: " . $e->getMessage());
            return ['folders' => []];
        }
    }
} 