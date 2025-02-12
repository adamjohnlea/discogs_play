<?php

class FolderService {
    private $config;
    private $folderMap = [
        'all' => '0',
        'uncategorized' => '1'
    ];
    private $slugMap = [];

    public function __construct($config) {
        $this->config = $config;
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
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Trim
        $text = trim($text, '-');
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // Lowercase
        $text = strtolower($text);
        return $text ?: 'n-a';
    }
} 