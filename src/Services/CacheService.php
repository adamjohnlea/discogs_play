<?php

require_once __DIR__ . '/LogService.php';

class CacheService {
    private $db;
    private $imageTypes = ['cover', 'release'];
    private $logger;
    private $collectionCacheDuration = 86400; // 24 hours in seconds
    
    public function __construct($config) {
        $this->db = DatabaseService::getInstance($config)->getConnection();
        $this->logger = LogService::getInstance($config);
        
        // Allow override of cache duration for testing
        if (isset($config['cache']['collection_duration'])) {
            $this->collectionCacheDuration = $config['cache']['collection_duration'];
        }
    }
    
    public function clearCache() {
        $this->logger->info("Clearing cache");
        $this->db->exec("DELETE FROM releases");
        $this->db->exec("DELETE FROM images");
        return true;
    }
    
    public function cacheRelease($releaseId, $releaseData, $myReleaseData = null, $isBasicData = false) {
        $this->logger->info("Caching release", [
            'release_id' => $releaseId,
            'is_basic' => $isBasicData
        ]);
        
        $this->logger->debug("Release data structure", [
            'release_id' => $releaseId,
            'keys' => array_keys($releaseData),
            'is_basic' => $isBasicData
        ]);
        
        if ($myReleaseData) {
            $this->logger->debug("My release data structure", [
                'release_id' => $releaseId,
                'keys' => array_keys($myReleaseData)
            ]);
        }
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO releases (id, data, my_data, is_basic_data, last_updated)
            VALUES (:id, :data, :my_data, :is_basic_data, CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([
            ':id' => $releaseId,
            ':data' => json_encode($releaseData),
            ':my_data' => $myReleaseData ? json_encode($myReleaseData) : null,
            ':is_basic_data' => $isBasicData ? 1 : 0
        ]);
        
        if (!$result) {
            $this->logger->error("Failed to cache release", [
                'release_id' => $releaseId,
                'sql_error' => $stmt->errorInfo()
            ]);
        }
        
        return $result;
    }
    
    public function getCachedRelease($releaseId) {
        $this->logger->info("Fetching cached release", ['release_id' => $releaseId]);
        
        $stmt = $this->db->prepare("
            SELECT data, my_data, is_basic_data, last_updated 
            FROM releases 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => $releaseId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->logger->debug("No cached data found", ['release_id' => $releaseId]);
            return null;
        }
        
        $data = json_decode($result['data'], true);
        $myData = $result['my_data'] ? json_decode($result['my_data'], true) : null;
        
        $this->logger->debug("Retrieved cached release", [
            'release_id' => $releaseId,
            'data_keys' => array_keys($data),
            'my_data_keys' => $myData ? array_keys($myData) : null,
            'is_basic_data' => (bool)$result['is_basic_data'],
            'cached_at' => $result['last_updated']
        ]);
        
        return [
            'data' => $data,
            'my_data' => $myData,
            'is_basic_data' => (bool)$result['is_basic_data'],
            'last_updated' => $result['last_updated']
        ];
    }
    
    public function cacheImage($releaseId, $type, $imageUrl, $filePath, $mimeType) {
        if (!in_array($type, $this->imageTypes)) {
            throw new InvalidArgumentException("Invalid image type: {$type}");
        }

        // Begin transaction
        $this->db->beginTransaction();

        try {
            // Cache the image
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO images 
                (release_id, type, mime_type, original_url, file_path, last_updated)
                VALUES (:release_id, :type, :mime_type, :original_url, :file_path, CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([
                ':release_id' => $releaseId,
                ':type' => $type,
                ':mime_type' => $mimeType,
                ':original_url' => $imageUrl,
                ':file_path' => $filePath
            ]);

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Rollback on error
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getCachedImage($releaseId, $type, $imageUrl) {
        if (!in_array($type, $this->imageTypes)) {
            throw new InvalidArgumentException("Invalid image type: {$type}");
        }
        
        $stmt = $this->db->prepare("
            SELECT file_path, mime_type, last_updated 
            FROM images 
            WHERE release_id = :release_id 
            AND type = :type 
            AND original_url = :original_url
        ");
        
        $stmt->execute([
            ':release_id' => $releaseId,
            ':type' => $type,
            ':original_url' => $imageUrl
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function isReleaseCacheValid($releaseId, $allowBasicData = false) {
        $stmt = $this->db->prepare("
            SELECT data, is_basic_data 
            FROM releases 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => $releaseId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->logger->debug("No cache entry found", ['release_id' => $releaseId]);
            return false;
        }
        
        $data = json_decode($result['data'], true);
        if (empty($data)) {
            $this->logger->debug("Cache entry exists but is empty", ['release_id' => $releaseId]);
            return false;
        }

        // For collection views, basic data is considered valid
        if ($result['is_basic_data'] && !$allowBasicData) {
            $this->logger->debug("Cache entry contains only basic data", [
                'release_id' => $releaseId,
                'data_keys' => array_keys($data)
            ]);
            return false;
        }
        
        return true;
    }
    
    public function isImageCacheValid($releaseId, $type, $imageUrl) {
        // Always return true if the image exists in cache
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM images 
            WHERE release_id = :release_id 
            AND type = :type 
            AND original_url = :original_url
        ");
        
        $stmt->execute([
            ':release_id' => $releaseId,
            ':type' => $type,
            ':original_url' => $imageUrl
        ]);
        
        return (bool)$stmt->fetch(PDO::FETCH_COLUMN);
    }
    
    public function releaseExists($releaseId) {
        $stmt = $this->db->prepare("
            SELECT 1 FROM releases WHERE id = :id
        ");
        
        $stmt->execute([':id' => $releaseId]);
        return (bool)$stmt->fetch(PDO::FETCH_COLUMN);
    }
    
    public function createPlaceholderRelease($releaseId) {
        $this->logger->debug("Creating placeholder release", ['release_id' => $releaseId]);
        
        $stmt = $this->db->prepare("
            INSERT OR IGNORE INTO releases (id, data, last_updated)
            VALUES (:id, '{}', CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([':id' => $releaseId]);
    }
    
    public function cacheCollection($cacheKey, $data) {
        $this->logger->info("Caching collection list", [
            'cache_key' => $cacheKey
        ]);
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO releases (id, data, is_basic_data, last_updated)
            VALUES (:id, :data, 0, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([
            ':id' => 'collection_' . $cacheKey,
            ':data' => json_encode($data)
        ]);
    }
    
    public function getCachedCollection($cacheKey) {
        $stmt = $this->db->prepare("
            SELECT data, last_updated 
            FROM releases 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => 'collection_' . $cacheKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        return json_decode($result['data'], true);
    }
    
    public function isCollectionCacheValid($cacheKey) {
        $stmt = $this->db->prepare("
            SELECT last_updated 
            FROM releases 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => 'collection_' . $cacheKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        // Use configurable cache duration
        $cacheAge = time() - strtotime($result['last_updated']);
        return $cacheAge < $this->collectionCacheDuration;
    }
    
    public function cacheFolders($userId, $folderData) {
        $this->logger->info("Caching folders for user", [
            'user_id' => $userId
        ]);
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO folders (user_id, data, last_updated)
            VALUES (:user_id, :data, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':data' => json_encode($folderData)
        ]);
    }
    
    public function getCachedFolders($userId) {
        $stmt = $this->db->prepare("
            SELECT data, last_updated 
            FROM folders 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        return [
            'data' => json_decode($result['data'], true),
            'last_updated' => $result['last_updated']
        ];
    }
    
    public function isFoldersCacheValid($userId) {
        $stmt = $this->db->prepare("
            SELECT last_updated 
            FROM folders 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        // Cache folders for 24 hours
        $cacheAge = time() - strtotime($result['last_updated']);
        return $cacheAge < 86400;
    }
} 