<?php

class WantlistImageService {
    private $config;
    private $coversPath;
    private $itemsPath;
    private $cacheService;

    public function __construct($config) {
        $this->config = $config;
        $this->coversPath = $config['paths']['public'] . '/img/wantlist/covers/';
        $this->itemsPath = $config['paths']['public'] . '/img/wantlist/releases/';
        require_once __DIR__ . '/WantlistCacheService.php';
        $this->cacheService = new WantlistCacheService($config);
        
        // Ensure directories exist
        if (!file_exists($this->coversPath)) {
            mkdir($this->coversPath, 0755, true);
        }
        if (!file_exists($this->itemsPath)) {
            mkdir($this->itemsPath, 0755, true);
        }
    }

    public function getWantlistCoverImage($imageUrl, $wantlistItemId) {
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($wantlistItemId, 'cover', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($wantlistItemId, 'cover', $imageUrl)) {
            return '/' . $cachedImage['file_path'];
        }

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $mimeType = $this->getMimeType($imageData);
            
            // Ensure wantlist item exists in database before storing image
            $this->ensureWantlistItemExists($wantlistItemId);
            
            try {
                // Store image and get path
                $filePath = $this->storeImage($wantlistItemId, 'cover', $imageData, $mimeType);
                
                // Cache the image info
                $this->cacheService->cacheImage(
                    $wantlistItemId, 
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

    public function getWantlistImage($imageUrl, $wantlistItemId, $index = 0) {
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($wantlistItemId, 'item', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($wantlistItemId, 'item', $imageUrl)) {
            return '/' . $cachedImage['file_path'];
        }

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $mimeType = $this->getMimeType($imageData);
            
            // Ensure wantlist item exists in database before storing image
            $this->ensureWantlistItemExists($wantlistItemId);
            
            try {
                // Store image and get path
                $filePath = $this->storeImage($wantlistItemId, 'item', $imageData, $mimeType, $index);
                
                // Cache the image info
                $this->cacheService->cacheImage(
                    $wantlistItemId, 
                    'item', 
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

    private function ensureWantlistItemExists($wantlistItemId) {
        if (!$this->cacheService->wantlistItemExists($wantlistItemId)) {
            $this->cacheService->createPlaceholderWantlistItem($wantlistItemId);
        }
    }

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

    private function getMimeType($imageData) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($imageData);
    }

    private function storeImage($wantlistItemId, $type, $imageData, $mimeType, $index = 0) {
        $extension = $this->getExtensionFromMimeType($mimeType);
        $basePath = $type === 'cover' ? $this->coversPath : $this->itemsPath;
        $filename = $type === 'cover' 
            ? "{$wantlistItemId}_cover.{$extension}"
            : "{$wantlistItemId}_{$index}.{$extension}";
        
        $fullPath = $basePath . $filename;
        file_put_contents($fullPath, $imageData);
        
        return 'img/wantlist/' . ($type === 'cover' ? 'covers/' : 'releases/') . $filename;
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