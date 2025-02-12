<?php

class ImageService {
    private $config;
    private $coversPath;
    private $releasesPath;
    private $cacheService;
    private $logger;

    public function __construct($config) {
        $this->config = $config;
        $this->coversPath = $config['paths']['public'] . '/img/covers/';
        $this->releasesPath = $config['paths']['public'] . '/img/releases/';
        $this->cacheService = new CacheService($config);
        require_once __DIR__ . '/LogService.php';
        $this->logger = LogService::getInstance($config);
    }

    /**
     * Get the URL for a cover image, downloading it if necessary
     */
    public function getCoverImage($imageUrl, $releaseId) {
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($releaseId, 'cover', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($releaseId, 'cover', $imageUrl)) {
            return $this->serveImage($cachedImage['image_data'], $cachedImage['mime_type']);
        }

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $mimeType = $this->getMimeType($imageData);
            
            // Ensure release exists in database before storing image
            $this->ensureReleaseExists($releaseId);
            
            try {
                $this->cacheService->cacheImage($releaseId, 'cover', $imageUrl, $imageData, $mimeType);
                return $this->serveImage($imageData, $mimeType);
            } catch (Exception $e) {
                $this->logger->error("Failed to cache image", [
                    'release_id' => $releaseId,
                    'type' => 'cover',
                    'error' => $e->getMessage()
                ]);
                // Return original URL if caching fails
                return $imageUrl;
            }
        }

        // Fallback to original URL if download fails
        return $imageUrl;
    }

    /**
     * Get the URL for a release image, downloading it if necessary
     */
    public function getReleaseImage($imageUrl, $releaseId, $index = 0) {
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($releaseId, 'release', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($releaseId, 'release', $imageUrl)) {
            return $this->serveImage($cachedImage['image_data'], $cachedImage['mime_type']);
        }

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $mimeType = $this->getMimeType($imageData);
            
            // Ensure release exists in database before storing image
            $this->ensureReleaseExists($releaseId);
            
            try {
                $this->cacheService->cacheImage($releaseId, 'release', $imageUrl, $imageData, $mimeType);
                return $this->serveImage($imageData, $mimeType);
            } catch (Exception $e) {
                $this->logger->error("Failed to cache image", [
                    'release_id' => $releaseId,
                    'type' => 'release',
                    'error' => $e->getMessage()
                ]);
                // Return original URL if caching fails
                return $imageUrl;
            }
        }

        // Fallback to original URL if download fails
        return $imageUrl;
    }

    /**
     * Ensure a release entry exists in the database
     */
    private function ensureReleaseExists($releaseId) {
        if (!$this->cacheService->releaseExists($releaseId)) {
            $this->logger->debug("Creating placeholder release entry", ['release_id' => $releaseId]);
            $this->cacheService->createPlaceholderRelease($releaseId);
        }
    }

    /**
     * Download an image from a URL
     */
    private function downloadImage($url) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $imageData = curl_exec($ch);
            curl_close($ch);
            return $imageData ?: false;
        } catch (Exception $e) {
            $this->logger->error("Failed to download image", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get MIME type from image data
     */
    private function getMimeType($imageData) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($imageData) ?: 'image/jpeg';
    }

    /**
     * Serve image data with proper headers
     */
    private function serveImage($imageData, $mimeType) {
        $base64 = base64_encode($imageData);
        return "data:{$mimeType};base64,{$base64}";
    }
} 