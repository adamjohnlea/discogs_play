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
        
        require_once __DIR__ . '/../Services/LogService.php';
    }

    public function getWantlistCoverImage($imageUrl, $wantlistItemId) {
        $logger = LogService::getInstance($this->config);
        $logger->info('Requesting wantlist cover image', [
            'wantlist_item_id' => $wantlistItemId,
            'image_url' => $imageUrl
        ]);
        
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($wantlistItemId, 'cover', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($wantlistItemId, 'cover', $imageUrl)) {
            $logger->info('Cache hit for wantlist cover image', [
                'wantlist_item_id' => $wantlistItemId,
                'cached_path' => $cachedImage['file_path']
            ]);
            return '/' . $cachedImage['file_path'];
        }
        
        $logger->info('Cache miss for wantlist cover image, downloading', [
            'wantlist_item_id' => $wantlistItemId,
            'image_url' => $imageUrl
        ]);

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $logger->info('Downloaded wantlist cover image successfully', [
                'wantlist_item_id' => $wantlistItemId,
                'size' => strlen($imageData)
            ]);
            
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
                
                $logger->info('Stored and cached wantlist cover image', [
                    'wantlist_item_id' => $wantlistItemId,
                    'file_path' => $filePath
                ]);
                
                return '/' . $filePath;
            } catch (Exception $e) {
                $logger->error('Failed to store wantlist cover image', [
                    'wantlist_item_id' => $wantlistItemId,
                    'error' => $e->getMessage()
                ]);
                return $imageUrl;
            }
        }
        
        $logger->warning('Failed to download wantlist cover image', [
            'wantlist_item_id' => $wantlistItemId,
            'image_url' => $imageUrl
        ]);

        return $imageUrl;
    }

    public function getWantlistImage($imageUrl, $wantlistItemId, $index = 0) {
        $logger = LogService::getInstance($this->config);
        $logger->info('Requesting wantlist item image', [
            'wantlist_item_id' => $wantlistItemId,
            'image_url' => $imageUrl,
            'index' => $index
        ]);
        
        // Check cache first
        $cachedImage = $this->cacheService->getCachedImage($wantlistItemId, 'item', $imageUrl);
        if ($cachedImage && $this->cacheService->isImageCacheValid($wantlistItemId, 'item', $imageUrl)) {
            $logger->info('Cache hit for wantlist item image', [
                'wantlist_item_id' => $wantlistItemId,
                'cached_path' => $cachedImage['file_path']
            ]);
            return '/' . $cachedImage['file_path'];
        }
        
        $logger->info('Cache miss for wantlist item image, downloading', [
            'wantlist_item_id' => $wantlistItemId,
            'image_url' => $imageUrl
        ]);

        // If not in cache, download and cache it
        $imageData = $this->downloadImage($imageUrl);
        if ($imageData) {
            $logger->info('Downloaded wantlist item image successfully', [
                'wantlist_item_id' => $wantlistItemId,
                'size' => strlen($imageData)
            ]);
            
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
                
                $logger->info('Stored and cached wantlist item image', [
                    'wantlist_item_id' => $wantlistItemId,
                    'file_path' => $filePath
                ]);
                
                return '/' . $filePath;
            } catch (Exception $e) {
                $logger->error('Failed to store wantlist item image', [
                    'wantlist_item_id' => $wantlistItemId,
                    'error' => $e->getMessage()
                ]);
                return $imageUrl;
            }
        }
        
        $logger->warning('Failed to download wantlist item image', [
            'wantlist_item_id' => $wantlistItemId,
            'image_url' => $imageUrl
        ]);

        return $imageUrl;
    }

    private function ensureWantlistItemExists($wantlistItemId) {
        if (!$this->cacheService->wantlistItemExists($wantlistItemId)) {
            $this->cacheService->createPlaceholderWantlistItem($wantlistItemId);
        }
    }

    private function downloadImage($url) {
        $logger = LogService::getInstance($this->config);
        $logger->info('Downloading image', [
            'url' => $url
        ]);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'DiscogsPlayer/1.0');
            
            $imageData = curl_exec($ch);
            
            if ($imageData === false) {
                $error = curl_error($ch);
                $logger->error('cURL error during image download', [
                    'url' => $url,
                    'error' => $error
                ]);
                curl_close($ch);
                return false;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $logger->warning('Non-200 HTTP response when downloading image', [
                    'url' => $url,
                    'http_code' => $httpCode
                ]);
                return false;
            }
            
            $logger->info('Successfully downloaded image', [
                'url' => $url,
                'size' => strlen($imageData)
            ]);
            
            return $imageData ?: false;
        } catch (Exception $e) {
            $logger->error('Exception during image download', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function getMimeType($imageData) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($imageData);
    }

    private function storeImage($wantlistItemId, $type, $imageData, $mimeType, $index = 0) {
        $logger = LogService::getInstance($this->config);
        
        $extension = $this->getExtensionFromMimeType($mimeType);
        $basePath = $type === 'cover' ? $this->coversPath : $this->itemsPath;
        $filename = $type === 'cover' 
            ? "{$wantlistItemId}_cover.{$extension}"
            : "{$wantlistItemId}_{$index}.{$extension}";
        
        $fullPath = $basePath . $filename;
        
        $logger->info('Storing image file', [
            'wantlist_item_id' => $wantlistItemId,
            'type' => $type,
            'mime_type' => $mimeType,
            'file_path' => $fullPath
        ]);
        
        $success = file_put_contents($fullPath, $imageData);
        
        if ($success === false) {
            $logger->error('Failed to write image file', [
                'path' => $fullPath
            ]);
        } else {
            $logger->info('Successfully wrote image file', [
                'path' => $fullPath,
                'bytes_written' => $success
            ]);
        }
        
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