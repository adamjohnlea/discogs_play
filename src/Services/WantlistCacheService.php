<?php

class WantlistCacheService {
    private $config;
    private $db;
    private $imageTypes = ['cover', 'item'];
    
    public function __construct($config) {
        $this->config = $config;
        require_once __DIR__ . '/DatabaseService.php';
        $this->db = DatabaseService::getInstance($config)->getConnection();
    }
    
    public function getCachedWantlistItem($id) {
        $stmt = $this->db->prepare("
            SELECT data, is_basic_data 
            FROM wantlist_items 
            WHERE id = :id 
            AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $_SESSION['user_id']
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        $result['data'] = json_decode($result['data'], true);
        return $result;
    }
    
    public function checkWantlistCacheValidity($id, $allowBasicData = false) {
        $stmt = $this->db->prepare("
            SELECT data, is_basic_data 
            FROM wantlist_items 
            WHERE id = :id
            AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $_SESSION['user_id']
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        $data = json_decode($result['data'], true);
        if (empty($data)) {
            return false;
        }

        if ($result['is_basic_data'] && !$allowBasicData) {
            return false;
        }
        
        return true;
    }
    
    public function cacheWantlistItem($id, $data, $isBasicData = false) {
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        
        // Check if data has changed or if we have detailed data
        $existingData = $this->getCachedWantlistItem($id);
        $newDataJson = json_encode($data);
        $existingDataJson = $existingData ? json_encode($existingData['data']) : null;
        
        // Log current state
        $logger->info('Checking if wantlist item data changed', [
            'wantlist_item_id' => $id,
            'has_existing_data' => $existingData ? 'yes' : 'no',
            'existing_is_basic' => $existingData ? ($existingData['is_basic_data'] ? 'yes' : 'no') : 'n/a',
            'new_is_basic' => $isBasicData ? 'yes' : 'no',
            'data_size' => strlen($newDataJson)
        ]);
        
        // Don't overwrite detailed data with basic data
        if ($existingData && !$existingData['is_basic_data'] && $isBasicData) {
            $logger->info('Skipping update: Not replacing detailed wantlist data with basic data', [
                'wantlist_item_id' => $id
            ]);
            return true; // Keep detailed data
        }
        
        // Only update if data has changed
        if (!$existingData || $existingDataJson !== $newDataJson) {
            $logger->info('Updating wantlist item data', [
                'wantlist_item_id' => $id,
                'reason' => !$existingData ? 'no_existing_data' : 'data_changed',
                'is_basic_data' => $isBasicData ? 'yes' : 'no'
            ]);
            
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO wantlist_items 
                (id, data, is_basic_data, last_updated, user_id) 
                VALUES (:id, :data, :is_basic_data, datetime('now'), :user_id)
            ");
            
            $success = $stmt->execute([
                ':id' => $id,
                ':data' => $newDataJson,
                ':is_basic_data' => $isBasicData ? 1 : 0,
                ':user_id' => $_SESSION['user_id']
            ]);

            $logger->info('Wantlist item update ' . ($success ? 'successful' : 'failed'), [
                'wantlist_item_id' => $id
            ]);
            
            if ($success) {
                // Only update search index if data changed
                $logger->info('Updating wantlist search index', [
                    'wantlist_item_id' => $id
                ]);
                $this->updateSearchIndex($id, $data);
            }

            return $success;
        }
        
        $logger->info('No update needed: Wantlist item data unchanged', [
            'wantlist_item_id' => $id
        ]);
        
        return true; // No update needed
    }
    
    private function updateSearchIndex($id, $data) {
        // Remove old search entries for this release
        $stmt = $this->db->prepare("
            DELETE FROM wantlist_search 
            WHERE release_id = :release_id 
            AND user_id = :user_id
        ");
        $stmt->execute([
            ':release_id' => $id,
            ':user_id' => $_SESSION['user_id']
        ]);

        // Insert new search entry
        $stmt = $this->db->prepare("
            INSERT INTO wantlist_search 
            (release_id, user_id, title, artist, label) 
            VALUES (:release_id, :user_id, :title, :artist, :label)
        ");

        $title = isset($data['title']) ? strtolower($data['title']) : '';
        $artist = '';
        $label = '';

        if (isset($data['artists']) && is_array($data['artists'])) {
            $artist = strtolower(implode(' ', array_map(function($a) {
                return $a['name'] ?? '';
            }, $data['artists'])));
        }

        if (isset($data['labels']) && is_array($data['labels'])) {
            $label = strtolower(implode(' ', array_map(function($l) {
                return $l['name'] ?? '';
            }, $data['labels'])));
        }

        return $stmt->execute([
            ':release_id' => $id,
            ':user_id' => $_SESSION['user_id'],
            ':title' => $title,
            ':artist' => $artist,
            ':label' => $label
        ]);
    }
    
    public function getCachedImage($wantlistItemId, $type, $imageUrl) {
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
        if (!in_array($type, $this->imageTypes)) {
            throw new InvalidArgumentException("Invalid image type: {$type}");
        }
        
        $stmt = $this->db->prepare("
            SELECT file_path, mime_type, last_updated 
            FROM wantlist_images 
            WHERE wantlist_item_id = :wantlist_item_id 
            AND type = :type 
            AND original_url = :original_url
            AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':wantlist_item_id' => $wantlistItemId,
            ':type' => $type,
            ':original_url' => $imageUrl,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function isImageCacheValid($wantlistItemId, $type, $imageUrl) {
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
        if (!in_array($type, $this->imageTypes)) {
            throw new InvalidArgumentException("Invalid image type: {$type}");
        }
        
        $maxAge = 60 * 60 * 24 * 30; // 30 days cache validity
        
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM wantlist_images 
            WHERE wantlist_item_id = :wantlist_item_id 
            AND type = :type 
            AND original_url = :original_url
            AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':wantlist_item_id' => $wantlistItemId,
            ':type' => $type,
            ':original_url' => $imageUrl,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        return (bool)$stmt->fetch(PDO::FETCH_COLUMN);
    }
    
    public function cacheImage($wantlistItemId, $type, $imageUrl, $filePath, $mimeType) {
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        
        $logger->info('Caching wantlist image information', [
            'wantlist_item_id' => $wantlistItemId,
            'type' => $type,
            'image_url' => $imageUrl,
            'file_path' => $filePath
        ]);
        
        if (!in_array($type, $this->imageTypes)) {
            $logger->error('Invalid image type provided', [
                'type' => $type,
                'valid_types' => implode(', ', $this->imageTypes)
            ]);
            throw new InvalidArgumentException("Invalid image type: {$type}");
        }
        
        // Check if the image entry already exists and is unchanged
        $stmt = $this->db->prepare("
            SELECT file_path FROM wantlist_images 
            WHERE wantlist_item_id = :wantlist_item_id 
            AND type = :type 
            AND original_url = :original_url
            AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':wantlist_item_id' => $wantlistItemId,
            ':type' => $type,
            ':original_url' => $imageUrl,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $existingPath = $stmt->fetchColumn();
        
        if ($existingPath && $existingPath === $filePath) {
            $logger->info('Image record unchanged, skipping database update', [
                'wantlist_item_id' => $wantlistItemId,
                'type' => $type
            ]);
            return true;
        }
        
        $logger->info('Updating image record in database', [
            'wantlist_item_id' => $wantlistItemId,
            'type' => $type,
            'reason' => $existingPath ? 'path_changed' : 'new_record'
        ]);
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO wantlist_images 
            (wantlist_item_id, type, original_url, file_path, mime_type, last_updated, user_id) 
            VALUES (:wantlist_item_id, :type, :original_url, :file_path, :mime_type, datetime('now'), :user_id)
        ");
        
        $success = $stmt->execute([
            ':wantlist_item_id' => $wantlistItemId,
            ':type' => $type,
            ':original_url' => $imageUrl,
            ':file_path' => $filePath,
            ':mime_type' => $mimeType,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $logger->info('Image database update ' . ($success ? 'successful' : 'failed'), [
            'wantlist_item_id' => $wantlistItemId,
            'type' => $type
        ]);
        
        return $success;
    }
    
    public function wantlistItemExists($wantlistItemId) {
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
        $stmt = $this->db->prepare("
            SELECT 1 FROM wantlist_items 
            WHERE id = :id
            AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':id' => $wantlistItemId,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        return (bool)$stmt->fetch(PDO::FETCH_COLUMN);
    }
    
    public function createPlaceholderWantlistItem($wantlistItemId) {
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
        // Create a placeholder with minimal data 
        $placeholderData = [
            'id' => $wantlistItemId,
            'placeholder' => true,
            'last_updated' => time()
        ];
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO wantlist_items (
                id, data, is_basic_data, last_updated, user_id
            ) VALUES (
                :id, :data, :is_basic_data, :last_updated, :user_id
            )
        ");
        
        $stmt->execute([
            ':id' => $wantlistItemId,
            ':data' => json_encode($placeholderData),
            ':is_basic_data' => 1,
            ':last_updated' => date('Y-m-d H:i:s'),
            ':user_id' => $_SESSION['user_id']
        ]);
    }
    
    /**
     * Cache the entire wantlist response for fallback
     * 
     * @param array $data The complete wantlist data from Discogs API
     * @return boolean Success or failure
     */
    public function cacheWantlist($data) {
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        
        try {
            $startTime = microtime(true);
            
            // Use an integer ID format to identify this as the complete wantlist
            // 99000000 prefix + user_id ensures uniqueness and keeps it as an integer
            $cacheKey = 99000000 + $_SESSION['user_id'];
            
            // Store or update the wantlist data in the wantlist_items table
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO wantlist_items 
                (id, data, is_basic_data, last_updated, user_id) 
                VALUES (:id, :data, 0, datetime('now'), :user_id)
            ");
            
            // Add user_id to the data to ensure consistency
            if (!isset($data['user_id'])) {
                $data['user_id'] = $_SESSION['user_id'];
            }
            
            $success = $stmt->execute([
                ':id' => $cacheKey,
                ':data' => json_encode($data),
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $endTime = microtime(true);
            $elapsedTime = round(($endTime - $startTime) * 1000, 2); // ms
            
            if ($success) {
                $logger->info('Cached complete wantlist data', [
                    'user_id' => $_SESSION['user_id'],
                    'cache_key' => $cacheKey,
                    'wants_count' => isset($data['wants']) ? count($data['wants']) : 0,
                    'time_ms' => $elapsedTime
                ]);
            } else {
                $logger->error('Failed to cache wantlist data', [
                    'time_ms' => $elapsedTime
                ]);
            }
            
            return $success;
        } catch (Exception $e) {
            $logger->error('Exception caching wantlist data: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cached wantlist data for fallback
     * 
     * @return array|null The cached wantlist data or null if not found
     */
    public function getCachedWantlist() {
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        
        try {
            $startTime = microtime(true);
            
            // Use the same key format as in cacheWantlist
            $cacheKey = 99000000 + $_SESSION['user_id'];
            
            // Remove the INDEXED BY clause which is causing errors
            $stmt = $this->db->prepare("
                SELECT data, last_updated
                FROM wantlist_items
                WHERE id = :id
                AND user_id = :user_id
                LIMIT 1
            ");
            
            $stmt->execute([
                ':id' => $cacheKey,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $endTime = microtime(true);
                $elapsedTime = round(($endTime - $startTime) * 1000, 2); // ms
                
                $logger->info('No cached wantlist data found', [
                    'time_ms' => $elapsedTime
                ]);
                return null;
            }
            
            // Decode data after checking validity
            $decodingStart = microtime(true);
            $data = json_decode($result['data'], true);
            $decodingTime = round((microtime(true) - $decodingStart) * 1000, 2); // ms
            
            $endTime = microtime(true);
            $elapsedTime = round(($endTime - $startTime) * 1000, 2); // ms
            
            if (isset($data['user_id']) && $data['user_id'] != $_SESSION['user_id']) {
                $logger->warning('Cached wantlist belongs to different user', [
                    'cached_user_id' => $data['user_id'],
                    'current_user_id' => $_SESSION['user_id'],
                    'time_ms' => $elapsedTime,
                    'decoding_ms' => $decodingTime
                ]);
                return null;
            }
            
            $logger->info('Retrieved cached wantlist data', [
                'last_updated' => $result['last_updated'],
                'wants_count' => isset($data['wants']) ? count($data['wants']) : 0,
                'time_ms' => $elapsedTime,
                'decoding_ms' => $decodingTime,
                'data_size_kb' => round(strlen($result['data']) / 1024, 2)
            ]);
            
            return $data;
        } catch (Exception $e) {
            $logger->error('Exception retrieving cached wantlist: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if the entire wantlist cache is valid
     *
     * @return boolean True if the cache is valid, false otherwise
     */
    public function isWantlistCacheValid() {
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        
        try {
            $startTime = microtime(true);
            
            // Use the same key format as in cacheWantlist
            $cacheKey = 99000000 + $_SESSION['user_id'];
            
            // Remove the INDEXED BY clause which is causing errors
            $stmt = $this->db->prepare("
                SELECT last_updated
                FROM wantlist_items
                WHERE id = :id
                AND user_id = :user_id
                LIMIT 1
            ");
            
            $stmt->execute([
                ':id' => $cacheKey,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $endTime = microtime(true);
                $elapsedTime = round(($endTime - $startTime) * 1000, 2); // ms
                
                $logger->info('No cached wantlist found to validate', [
                    'time_ms' => $elapsedTime,
                    'cache_key' => $cacheKey,
                    'user_id' => $_SESSION['user_id']
                ]);
                return false;
            }
            
            // Use a configurable cache duration - default to 24 hours if not set
            $cacheDuration = isset($this->config['cache']['wantlist_duration']) ? 
                $this->config['cache']['wantlist_duration'] : 86400; // 24 hours in seconds
            
            $cacheAge = time() - strtotime($result['last_updated']);
            $isValid = $cacheAge < $cacheDuration;
            
            $endTime = microtime(true);
            $elapsedTime = round(($endTime - $startTime) * 1000, 2); // ms
            
            $logger->info('Checked wantlist cache validity', [
                'cache_age_seconds' => $cacheAge,
                'cache_duration' => $cacheDuration,
                'is_valid' => $isValid ? 'yes' : 'no',
                'last_updated' => $result['last_updated'],
                'time_ms' => $elapsedTime,
                'config_has_wantlist_duration' => isset($this->config['cache']['wantlist_duration']) ? 'yes' : 'no'
            ]);
            
            return $isValid;
        } catch (Exception $e) {
            $logger->error('Exception checking wantlist cache validity: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a specific wantlist item's cache is valid
     * 
     * @param integer $id The release ID to check
     * @param boolean $allowBasicData Whether to consider basic data valid (default: false)
     * @return boolean True if the cache is valid, false otherwise
     */
    public function isWantlistItemCacheValid($id, $allowBasicData = false) {
        require_once __DIR__ . '/../Services/LogService.php';
        $logger = LogService::getInstance($this->config);
        
        try {
            // Ensure id is an integer
            $id = (int)$id;
            
            $stmt = $this->db->prepare("
                SELECT data, is_basic_data, last_updated
                FROM wantlist_items
                WHERE id = :id
                AND user_id = :user_id
                LIMIT 1
            ");
            
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $logger->info("No cached wantlist item found for ID: {$id}");
                return false;
            }
            
            // Check if this is just a placeholder record
            $data = json_decode($result['data'], true);
            if (isset($data['placeholder']) && $data['placeholder'] === true) {
                $logger->info("Wantlist item ID: {$id} is just a placeholder record", [
                    'is_basic_data' => $result['is_basic_data'] ? 'yes' : 'no'
                ]);
                return false; // Placeholder records are not valid
            }
            
            // If we don't allow basic data and this is basic data, it's not valid
            if (!$allowBasicData && $result['is_basic_data']) {
                $logger->info("Wantlist item ID: {$id} is basic data, but detailed data is required");
                return false;
            }
            
            // Use a configurable cache duration - default to 30 days if not set
            $cacheDuration = isset($this->config['cache']['wantlist_item_duration']) ? 
                $this->config['cache']['wantlist_item_duration'] : 2592000; // 30 days in seconds
            
            $cacheAge = time() - strtotime($result['last_updated']);
            $isValid = $cacheAge < $cacheDuration;
            
            $logger->info("Checked wantlist item cache validity for ID: {$id}", [
                'cache_age_seconds' => $cacheAge,
                'cache_duration' => $cacheDuration,
                'is_valid' => $isValid ? 'yes' : 'no',
                'last_updated' => $result['last_updated'],
                'is_basic_data' => $result['is_basic_data'] ? 'yes' : 'no'
            ]);
            
            return $isValid;
        } catch (Exception $e) {
            $logger->error("Exception checking wantlist item cache validity: " . $e->getMessage(), [
                'id' => $id
            ]);
            return false;
        }
    }

    private function ensureWantlistItemExists($wantlistItemId) {
        // Ensure wantlistItemId is an integer
        $wantlistItemId = (int)$wantlistItemId;
        
        if (!$this->wantlistItemExists($wantlistItemId)) {
            $this->createPlaceholderWantlistItem($wantlistItemId);
        }
    }
} 