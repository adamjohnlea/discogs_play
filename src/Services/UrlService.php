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
    public function release($id) {
        return "/release/{$id}";
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
        $folder = isset($currentParams['folder_id']) ? 
            $this->folderService->getFolderSlug($currentParams['folder_id']) : 'all';
        
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
        
        return "/folder/{$folder}/sort/{$field}/{$direction}/page/{$number}";
    }

    /**
     * Update folder mappings
     */
    public function updateFolderMappings($folders) {
        $this->folderService->updateFolderMappings($folders);
    }
} 