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
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
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
                // Optimize the image (covers can be standardized)
                $optimizedImageData = $this->optimizeImage($imageData, $mimeType, 'cover');
                
                // Store image and get path
                $filePath = $this->storeImage($wantlistItemId, 'cover', $optimizedImageData, $mimeType);
                
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
                    'file_path' => $filePath,
                    'original_size' => strlen($imageData),
                    'optimized_size' => strlen($optimizedImageData),
                    'savings_percent' => strlen($imageData) > 0 ? round((1 - strlen($optimizedImageData) / strlen($imageData)) * 100, 2) : 0
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
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
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
                // Optimize the image (release images maintain aspect ratio)
                $optimizedImageData = $this->optimizeImage($imageData, $mimeType, 'item');
                
                // Store image and get path
                $filePath = $this->storeImage($wantlistItemId, 'item', $optimizedImageData, $mimeType, $index);
                
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
                    'file_path' => $filePath,
                    'original_size' => strlen($imageData),
                    'optimized_size' => strlen($optimizedImageData),
                    'savings_percent' => strlen($imageData) > 0 ? round((1 - strlen($optimizedImageData) / strlen($imageData)) * 100, 2) : 0
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
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 second connection timeout
            
            $imageData = curl_exec($ch);
            
            if ($imageData === false) {
                $error = curl_error($ch);
                $info = curl_getinfo($ch);
                $logger->error('cURL error during image download', [
                    'url' => $url,
                    'error' => $error,
                    'curl_info' => json_encode($info)
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
                
                // If we got rate limited, log more details
                if ($httpCode === 429) {
                    $logger->warning('Rate limit hit when downloading image', [
                        'url' => $url
                    ]);
                }
                
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
                'error' => $e->getMessage(),
                'exception_class' => get_class($e)
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

    /**
     * Optimize an image by resizing and compressing it
     * 
     * @param string $imageData The raw image data
     * @param string $mimeType The MIME type of the image
     * @param string $type Whether this is a 'cover' or 'item' image
     * @return string The optimized image data
     */
    private function optimizeImage($imageData, $mimeType, $type = 'cover') {
        $logger = LogService::getInstance($this->config);
        
        // If GD extension is not available, return original
        if (!extension_loaded('gd')) {
            $logger->warning('GD extension not available for image optimization', [
                'action' => 'using_original_image'
            ]);
            return $imageData;
        }
        
        try {
            // Create image resource from data
            $image = @imagecreatefromstring($imageData);
            if (!$image) {
                $logger->warning('Failed to create image from string', [
                    'action' => 'using_original_image'
                ]);
                return $imageData; // Unable to create image, return original
            }
            
            // Get original dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);
            
            $logger->info('Optimizing image', [
                'original_width' => $origWidth,
                'original_height' => $origHeight,
                'mime_type' => $mimeType,
                'type' => $type
            ]);
            
            // Define target dimensions based on image type
            if ($type === 'cover') {
                // Standard size for covers (maintain aspect ratio but enforce max dimensions)
                $maxWidth = 600;
                $maxHeight = 600;
            } else {
                // For other images, allow larger but still optimize
                $maxWidth = 1200;
                $maxHeight = 1200;
            }
            
            // Only resize if the image is larger than the max dimensions
            if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
                // Calculate new dimensions while maintaining aspect ratio
                if ($origWidth > $origHeight) {
                    $newWidth = $maxWidth;
                    $newHeight = intval($origHeight * ($maxWidth / $origWidth));
                } else {
                    $newHeight = $maxHeight;
                    $newWidth = intval($origWidth * ($maxHeight / $origHeight));
                }
                
                $logger->info('Resizing image', [
                    'from_width' => $origWidth,
                    'from_height' => $origHeight,
                    'to_width' => $newWidth,
                    'to_height' => $newHeight
                ]);
                
                // Create resized image
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                
                // Handle transparency for PNG images
                if ($mimeType === 'image/png') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                }
                
                // Perform the resize
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                
                // Free the original resource
                imagedestroy($image);
                $image = $resized;
            } else {
                $logger->info('Image already within size limits, skipping resize', [
                    'width' => $origWidth,
                    'height' => $origHeight,
                    'max_width' => $maxWidth,
                    'max_height' => $maxHeight
                ]);
            }
            
            // Capture the optimized image data based on mime type
            ob_start();
            switch ($mimeType) {
                case 'image/jpeg':
                    imagejpeg($image, null, 85); // 85% quality is a good balance
                    break;
                    
                case 'image/png':
                    // For PNG, use a compression level of 6 (0-9, where 9 is highest compression)
                    imagepng($image, null, 6);
                    break;
                    
                case 'image/gif':
                    imagegif($image);
                    break;
                    
                case 'image/webp':
                    imagewebp($image, null, 85);
                    break;
                    
                default:
                    // Default to JPEG if unsupported type
                    imagejpeg($image, null, 85);
                    break;
            }
            
            $optimizedImageData = ob_get_contents();
            ob_end_clean();
            
            // Free memory
            imagedestroy($image);
            
            // Only return the optimized data if it's smaller than the original
            // This ensures we don't make images larger or degrade quality unnecessarily
            if (strlen($optimizedImageData) < strlen($imageData)) {
                $logger->info('Image successfully optimized', [
                    'original_size' => strlen($imageData),
                    'optimized_size' => strlen($optimizedImageData),
                    'savings_bytes' => strlen($imageData) - strlen($optimizedImageData),
                    'savings_percent' => round((1 - strlen($optimizedImageData) / strlen($imageData)) * 100, 2)
                ]);
                return $optimizedImageData;
            }
            
            $logger->info('Optimized image not smaller than original, using original', [
                'original_size' => strlen($imageData),
                'optimized_size' => strlen($optimizedImageData)
            ]);
            return $imageData; // Original was already optimal
            
        } catch (Exception $e) {
            // If anything goes wrong, return the original image data
            $logger->error('Exception during image optimization', [
                'error' => $e->getMessage(),
                'action' => 'using_original_image'
            ]);
            return $imageData;
        }
    }
} 