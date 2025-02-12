<?php

class CreateCacheTablesV1 {
    public function up($db) {
        // Create releases table
        $db->exec("
            CREATE TABLE IF NOT EXISTS releases (
                id TEXT PRIMARY KEY,
                data TEXT NOT NULL,
                my_data TEXT,
                is_basic_data INTEGER DEFAULT 0,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create folders table for caching folder data
        $db->exec("
            CREATE TABLE IF NOT EXISTS folders (
                user_id INTEGER NOT NULL,
                data TEXT NOT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id)
            )
        ");
    }

    public function down($db) {
        $db->exec("DROP TABLE IF EXISTS releases");
        $db->exec("DROP TABLE IF EXISTS folders");
    }
} 