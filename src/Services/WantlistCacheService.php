<?php

class WantlistCacheService {
    private $config;
    private $db;
    private $imageTypes = ['cover', 'item'];
    
    public function __construct($config) {
        $this->config = $config;
        $this->db = new PDO('sqlite:' . $config['database']['path']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
    
    public function isWantlistCacheValid($id, $allowBasicData = false) {
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
    
    public function createPlaceholderWantlistItem($id) {
        return $this->cacheWantlistItem($id, ['id' => $id], true);
    }
    
    public function wantlistItemExists($id) {
        $stmt = $this->db->prepare("SELECT 1 FROM wantlist_items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetch(PDO::FETCH_COLUMN);
    }
} 