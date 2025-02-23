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
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO wantlist_items 
            (id, data, is_basic_data, last_updated, user_id) 
            VALUES (:id, :data, :is_basic_data, datetime('now'), :user_id)
        ");
        
        $success = $stmt->execute([
            ':id' => $id,
            ':data' => json_encode($data),
            ':is_basic_data' => $isBasicData ? 1 : 0,
            ':user_id' => $_SESSION['user_id']
        ]);

        if ($success) {
            // Update search index
            $this->updateSearchIndex($id, $data);
        }

        return $success;
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
        if (!in_array($type, $this->imageTypes)) {
            throw new InvalidArgumentException("Invalid image type: {$type}");
        }
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO wantlist_images 
            (wantlist_item_id, type, original_url, file_path, mime_type, last_updated, user_id) 
            VALUES (:wantlist_item_id, :type, :original_url, :file_path, :mime_type, datetime('now'), :user_id)
        ");
        
        return $stmt->execute([
            ':wantlist_item_id' => $wantlistItemId,
            ':type' => $type,
            ':original_url' => $imageUrl,
            ':file_path' => $filePath,
            ':mime_type' => $mimeType,
            ':user_id' => $_SESSION['user_id']
        ]);
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