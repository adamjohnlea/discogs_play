<?php

class CreateImagePathsV1 {
    public function up($db) {
        // Drop any existing tables or indexes
        $db->exec("DROP TABLE IF EXISTS images");
        $db->exec("DROP TABLE IF EXISTS images_new");  // Ensure no temporary table exists
        $db->exec("DROP INDEX IF EXISTS idx_images_release_type");
        $db->exec("DROP INDEX IF EXISTS idx_images_file_path");

        // Create new images table
        $db->exec("CREATE TABLE IF NOT EXISTS images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            release_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            original_url TEXT NOT NULL,
            file_path TEXT NOT NULL,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE
        )");

        // Create indexes for faster lookups
        $db->exec("CREATE INDEX IF NOT EXISTS idx_images_release_type ON images(release_id, type)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_images_file_path ON images(file_path)");
    }

    public function down($db) {
        $db->exec("DROP INDEX IF EXISTS idx_images_release_type");
        $db->exec("DROP INDEX IF EXISTS idx_images_file_path");
        $db->exec("DROP TABLE IF EXISTS images");
        $db->exec("DROP TABLE IF EXISTS images_new");  // Clean up temporary table in down migration too
    }
} 