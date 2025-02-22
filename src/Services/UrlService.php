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
     * Convert text to a URL-friendly slug
     */
    public function slugify($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace spaces and special characters with hyphens
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        
        // Remove multiple consecutive hyphens
        $text = preg_replace('/-+/', '-', $text);
        
        // Remove leading and trailing hyphens
        $text = trim($text, '-');
        
        return $text;
    }
}