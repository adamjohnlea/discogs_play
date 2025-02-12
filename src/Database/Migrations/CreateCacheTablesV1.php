<?php

class CreateCacheTablesV1 {
    public function up($db) {
        // Create releases table
        $db->exec("
            CREATE TABLE IF NOT EXISTS releases (
                id INTEGER PRIMARY KEY,
                data TEXT NOT NULL,
                my_data TEXT,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create images table
        $db->exec("
            CREATE TABLE IF NOT EXISTS images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                release_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                image_data BLOB NOT NULL,
                mime_type TEXT NOT NULL,
                original_url TEXT NOT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE
            )
        ");

        // Create index for faster lookups
        $db->exec("CREATE INDEX IF NOT EXISTS idx_images_release_type ON images(release_id, type)");
    }

    public function down($db) {
        $db->exec("DROP TABLE IF EXISTS images");
        $db->exec("DROP TABLE IF EXISTS releases");
    }
} 