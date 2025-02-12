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
        $this->logger->debug("Getting cover image", [
            'release_id' => $releaseId,
            'url' => $imageUrl
        ]);

        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($releaseId, 'cover', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($releaseId, 'cover', $imageUrl)) {
            $this->logger->debug("Using cached cover image", [
                'release_id' => $releaseId,
                'file_path' => $cachedImage['file_path']
            ]);
            return '/' . $cachedImage['file_path'];
        }

        $this->logger->info("Downloading cover image from Discogs", [
            'release_id' => $releaseId,
            'url' => $imageUrl
        ]);

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $this->logger->debug("Successfully downloaded image data", [
                'release_id' => $releaseId,
                'size' => strlen($imageData)
            ]);

            $mimeType = $this->getMimeType($imageData);
            
            // Ensure release exists in database before storing image
            $this->ensureReleaseExists($releaseId);
            
            try {
                // Store image and get path
                $filePath = $this->storeImage($releaseId, 'cover', $imageData, $mimeType);
                
                $this->logger->info("Stored cover image", [
                    'release_id' => $releaseId,
                    'file_path' => $filePath,
                    'mime_type' => $mimeType
                ]);

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
                $this->logger->error("Failed to cache image", [
                    'release_id' => $releaseId,
                    'type' => 'cover',
                    'error' => $e->getMessage()
                ]);
                return $imageUrl;
            }
        } else {
            $this->logger->error("Failed to download cover image", [
                'release_id' => $releaseId,
                'url' => $imageUrl
            ]);
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
                $this->logger->error("Failed to cache image", [
                    'release_id' => $releaseId,
                    'type' => 'release',
                    'error' => $e->getMessage()
                ]);
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
            $this->logger->debug("Creating placeholder release entry", ['release_id' => $releaseId]);
            $this->cacheService->createPlaceholderRelease($releaseId);
        }
    }

    /**
     * Download an image from a URL
     */
    private function downloadImage($url) {
        try {
            $this->logger->debug("Initiating image download", ['url' => $url]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'DiscogsPlayer/1.0');
            
            $imageData = curl_exec($ch);
            
            if ($imageData === false) {
                $this->logger->error("Curl error during image download", [
                    'url' => $url,
                    'error' => curl_error($ch),
                    'error_code' => curl_errno($ch)
                ]);
                curl_close($ch);
                return false;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $this->logger->error("HTTP error during image download", [
                    'url' => $url,
                    'http_code' => $httpCode
                ]);
                return false;
            }
            
            $this->logger->debug("Image download completed", [
                'url' => $url,
                'size' => strlen($imageData),
                'http_code' => $httpCode
            ]);
            
            return $imageData ?: false;
        } catch (Exception $e) {
            $this->logger->error("Exception during image download", [
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

    private function storeImage($releaseId, $type, $imageData, $mimeType) {
        // Determine file path and extension
        $ext = $this->getExtensionFromMimeType($mimeType);
        $directory = $type === 'cover' ? $this->coversPath : $this->releasesPath;
        $filename = $type === 'cover' ? $releaseId : $releaseId . '-' . uniqid();
        $filePath = $directory . $filename . '.' . $ext;
        
        $this->logger->debug("Preparing to store image", [
            'release_id' => $releaseId,
            'type' => $type,
            'directory' => $directory,
            'file_path' => $filePath
        ]);
        
        // Ensure directory exists
        if (!file_exists($directory)) {
            $this->logger->debug("Creating directory", ['directory' => $directory]);
            mkdir($directory, 0755, true);
        }
        
        // Store file
        if (!file_put_contents($filePath, $imageData)) {
            $this->logger->error("Failed to write image file", [
                'file_path' => $filePath,
                'directory_writable' => is_writable($directory)
            ]);
            throw new RuntimeException("Failed to write image file: " . $filePath);
        }
        
        $this->logger->debug("Successfully stored image", [
            'file_path' => $filePath,
            'size' => strlen($imageData)
        ]);
        
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