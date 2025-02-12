<?php

class ImageService {
    private $config;
    private $coversPath;
    private $releasesPath;
    private $cacheService;

    public function __construct($config) {
        $this->config = $config;
        $this->coversPath = $config['paths']['public'] . '/img/covers/';
        $this->releasesPath = $config['paths']['public'] . '/img/releases/';
        $this->cacheService = new CacheService($config);
    }

    /**
     * Get the URL for a cover image, downloading it if necessary
     */
    public function getCoverImage($imageUrl, $releaseId) {
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($releaseId, 'cover', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($releaseId, 'cover', $imageUrl)) {
            return '/' . $cachedImage['file_path'];
        }

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $mimeType = $this->getMimeType($imageData);
            
            // Ensure release exists in database before storing image
            $this->ensureReleaseExists($releaseId);
            
            try {
                // Store image and get path
                $filePath = $this->storeImage($releaseId, 'cover', $imageData, $mimeType);
                
                // Cache the image info
                $this->cacheService->cacheImage(
                    $releaseId, 
                    'cover', 
                    $imageUrl, 
                    $filePath,
                    $mimeType
                );
                
                return '/' . $filePath;
            } catch (Exception $e) {
                return $imageUrl;
            }
        }

        return $imageUrl;
    }

    /**
     * Get the URL for a release image, downloading it if necessary
     */
    public function getReleaseImage($imageUrl, $releaseId, $index = 0) {
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($releaseId, 'release', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($releaseId, 'release', $imageUrl)) {
            return '/' . $cachedImage['file_path'];
        }

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $mimeType = $this->getMimeType($imageData);
            
            // Ensure release exists in database before storing image
            $this->ensureReleaseExists($releaseId);
            
            try {
                // Store image and get path
                $filePath = $this->storeImage($releaseId, 'release', $imageData, $mimeType);
                
                // Cache the image info
                $this->cacheService->cacheImage(
                    $releaseId, 
                    'release', 
                    $imageUrl, 
                    $filePath,
                    $mimeType
                );
                
                return '/' . $filePath;
            } catch (Exception $e) {
                return $imageUrl;
            }
        }

        return $imageUrl;
    }

    /**
     * Ensure a release entry exists in the database
     */
    private function ensureReleaseExists($releaseId) {
        if (!$this->cacheService->releaseExists($releaseId)) {
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
            curl_setopt($ch, CURLOPT_USERAGENT, 'DiscogsPlayer/1.0');
            
            $imageData = curl_exec($ch);
            
            if ($imageData === false) {
                curl_close($ch);
                return false;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return false;
            }
            
            return $imageData ?: false;
        } catch (Exception $e) {
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

    private function storeImage($releaseId, $type, $imageData, $mimeType) {
        // Determine file path and extension
        $ext = $this->getExtensionFromMimeType($mimeType);
        $directory = $type === 'cover' ? $this->coversPath : $this->releasesPath;
        $filename = $type === 'cover' ? $releaseId : $releaseId . '-' . uniqid();
        $filePath = $directory . $filename . '.' . $ext;
        
        // Ensure directory exists
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Store file
        if (!file_put_contents($filePath, $imageData)) {
            throw new RuntimeException("Failed to write image file: " . $filePath);
        }
        
        return str_replace($this->config['paths']['public'] . '/', '', $filePath);
    }

    private function getExtensionFromMimeType($mimeType) {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        return $map[$mimeType] ?? 'jpg';
    }
} 