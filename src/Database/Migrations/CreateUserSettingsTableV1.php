<?php

class CreateUserSettingsTableV1 {
    public function up($db) {
        // Create user settings table
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discogs_username TEXT UNIQUE,
                discogs_token TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Create index for faster lookups
        $db->exec("CREATE INDEX IF NOT EXISTS idx_user_settings_user_id ON user_settings(user_id)");
    }

    public function down($db) {
        $db->exec("DROP INDEX IF EXISTS idx_user_settings_user_id");
        $db->exec("DROP TABLE IF EXISTS user_settings");
    }
} 