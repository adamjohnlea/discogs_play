<?php

class Migration {
    protected $db;
    protected static $migrations = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public static function register($migration) {
        self::$migrations[] = $migration;
    }
    
    public function createMigrationsTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function getAppliedMigrations() {
        return $this->db->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function migrate() {
        $this->createMigrationsTable();
        $appliedMigrations = $this->getAppliedMigrations();
        
        $newMigrations = [];
        $batch = $this->getNextBatchNumber();
        
        foreach (self::$migrations as $migration) {
            $className = get_class($migration);
            if (!in_array($className, $appliedMigrations)) {
                $migration->up($this->db);
                $this->db->exec("INSERT INTO migrations (migration, batch) VALUES ('$className', $batch)");
                $newMigrations[] = $className;
            }
        }
        
        return $newMigrations;
    }
    
    public function rollback() {
        $lastBatch = $this->getLastBatchNumber();
        if (!$lastBatch) return [];
        
        $migrations = $this->db->query(
            "SELECT migration FROM migrations WHERE batch = $lastBatch ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
        
        $rolledBack = [];
        foreach ($migrations as $migration) {
            $instance = new $migration($this->db);
            $instance->down($this->db);
            $this->db->exec("DELETE FROM migrations WHERE migration = '$migration'");
            $rolledBack[] = $migration;
        }
        
        return $rolledBack;
    }
    
    private function getNextBatchNumber() {
        $lastBatch = $this->getLastBatchNumber();
        return $lastBatch ? $lastBatch + 1 : 1;
    }
    
    private function getLastBatchNumber() {
        return $this->db->query("SELECT MAX(batch) FROM migrations")->fetchColumn() ?: 0;
    }
} 