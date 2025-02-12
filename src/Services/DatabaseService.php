<?php

class DatabaseService {
    private static $instance = null;
    private $db;
    
    private function __construct($config) {
        $dbPath = $config['database']['path'] ?? __DIR__ . '/../../database/discogs.sqlite';
        $this->ensureDatabaseDirectory($dbPath);
        
        $this->db = new PDO("sqlite:{$dbPath}");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');
    }
    
    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new RuntimeException('Config must be provided for first instantiation');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    private function ensureDatabaseDirectory($dbPath) {
        $directory = dirname($dbPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    public function commit() {
        return $this->db->commit();
    }
    
    public function rollback() {
        return $this->db->rollback();
    }
    
    public function prepare($sql) {
        return $this->db->prepare($sql);
    }
    
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }
} 