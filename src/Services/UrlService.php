<?php

class UrlService {
    private $config;
    private $folderService;

    public function __construct($config) {
        $this->config = $config;
        require_once __DIR__ . '/FolderService.php';
        $this->folderService = new FolderService($config);
    }

    /**
     * Generate a URL for a release
     */
    public function release($id, $releaseInfo = null) {
        if (!$releaseInfo) {
            return "/release/{$id}";
        }

        $artist = $this->slugify($releaseInfo['artists'][0]['name']);
        $title = $this->slugify($releaseInfo['title']);
        
        return "/release/{$id}/{$artist}/{$title}";
    }

    /**
     * Generate a URL for a folder
     */
    public function folder($id, $name = null) {
        $slug = $this->folderService->getFolderSlug($id, $name);
        return "/folder/{$slug}";
    }

    /**
     * Generate a URL for collection with sorting
     */
    public function sort($field, $direction, $currentParams = []) {
        global $folders;
        
        $folder = 'all';
        if (isset($currentParams['folder_id']) && $currentParams['folder_id'] !== '0') {
            // Look up the folder name from the global folders data
            if (isset($folders['folders']) && is_array($folders['folders'])) {
                foreach ($folders['folders'] as $f) {
                    if ($f['id'] == $currentParams['folder_id']) {
                        $folder = $this->folderService->getFolderSlug($currentParams['folder_id'], $f['name']);
                        break;
                    }
                }
            }
        }
        
        $page = isset($currentParams['page']) ? $currentParams['page'] : 1;
        
        return "/folder/{$folder}/sort/{$field}/{$direction}/page/{$page}";
    }

    /**
     * Generate a URL for pagination
     */
    public function page($number, $currentParams = []) {
        $folder = isset($currentParams['folder_id']) ? 
            $this->folderService->getFolderSlug($currentParams['folder_id']) : 'all';
        
        $field = $currentParams['sort_by'] ?? 'added';
        $direction = $currentParams['order'] ?? 'desc';
        $perPage = $currentParams['per_page'] ?? '25';
        
        return "/folder/{$folder}/sort/{$field}/{$direction}/page/{$number}?per_page={$perPage}";
    }

    /**
     * Convert a string to a URL-friendly slug
     */
    private function slugify($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        // Trim
        $text = trim($text, '-');
        
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        
        return $text;
    }

    /**
     * Update folder mappings
     */
    public function updateFolderMappings($folders) {
        $this->folderService->updateFolderMappings($folders);
    }
} 