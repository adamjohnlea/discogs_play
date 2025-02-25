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
                // Optimize the image (covers can be standardized)
                $optimizedImageData = $this->optimizeImage($imageData, $mimeType, 'cover');
                
                // Store image and get path
                $filePath = $this->storeImage($releaseId, 'cover', $optimizedImageData, $mimeType);
                
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
                // Optimize the image (release images maintain aspect ratio)
                $optimizedImageData = $this->optimizeImage($imageData, $mimeType, 'release');
                
                // Store image and get path
                $filePath = $this->storeImage($releaseId, 'release', $optimizedImageData, $mimeType);
                
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
     * Optimize an image by resizing and compressing it
     * 
     * @param string $imageData The raw image data
     * @param string $mimeType The MIME type of the image
     * @param string $type Whether this is a 'cover' or 'release' image
     * @return string The optimized image data
     */
    private function optimizeImage($imageData, $mimeType, $type = 'cover') {
        // If GD extension is not available, return original
        if (!extension_loaded('gd')) {
            return $imageData;
        }
        
        try {
            // Create image resource from data
            $image = @imagecreatefromstring($imageData);
            if (!$image) {
                return $imageData; // Unable to create image, return original
            }
            
            // Get original dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);
            
            // Define target dimensions based on image type
            if ($type === 'cover') {
                // Standard size for covers (maintain aspect ratio but enforce max dimensions)
                $maxWidth = 600;
                $maxHeight = 600;
            } else {
                // For release images, allow larger but still optimize
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
                return $optimizedImageData;
            }
            
            return $imageData; // Original was already optimal
            
        } catch (Exception $e) {
            // If anything goes wrong, return the original image data
            return $imageData;
        }
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