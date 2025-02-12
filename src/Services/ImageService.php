<?php

class ImageService {
    private $config;
    private $coversPath;
    private $releasesPath;

    public function __construct($config) {
        $this->config = $config;
        $this->coversPath = $config['paths']['public'] . '/img/covers/';
        $this->releasesPath = $config['paths']['public'] . '/img/releases/';
    }

    /**
     * Get the URL for a cover image, downloading it if necessary
     */
    public function getCoverImage($imageUrl, $releaseId) {
        $filename = $this->getImageFilename($imageUrl, $releaseId);
        $localPath = $this->coversPath . $filename;
        
        // Check if image exists locally
        if (!file_exists($localPath)) {
            $this->downloadImage($imageUrl, $localPath);
        }
        
        // Return local path if file exists, otherwise return original URL
        return file_exists($localPath) ? "/img/covers/{$filename}" : $imageUrl;
    }

    /**
     * Get the URL for a release image, downloading it if necessary
     */
    public function getReleaseImage($imageUrl, $releaseId, $index = 0) {
        $filename = $this->getImageFilename($imageUrl, $releaseId, $index);
        $localPath = $this->releasesPath . $filename;
        
        // Check if image exists locally
        if (!file_exists($localPath)) {
            $this->downloadImage($imageUrl, $localPath);
        }
        
        // Return local path if file exists, otherwise return original URL
        return file_exists($localPath) ? "/img/releases/{$filename}" : $imageUrl;
    }

    /**
     * Generate a filename for an image based on its URL and release ID
     */
    private function getImageFilename($url, $releaseId, $index = 0) {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        return $releaseId . ($index ? "_{$index}" : "") . '.' . $extension;
    }

    /**
     * Download an image from a URL and save it locally
     */
    private function downloadImage($url, $localPath) {
        try {
            // Get image data
            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                return false;
            }

            // Ensure directory exists
            $directory = dirname($localPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save image
            return file_put_contents($localPath, $imageData) !== false;
        } catch (Exception $e) {
            // Log error in the future
            return false;
        }
    }
} 