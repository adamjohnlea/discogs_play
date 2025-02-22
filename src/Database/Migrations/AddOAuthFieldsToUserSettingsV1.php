<?php

class AddOAuthFieldsToUserSettingsV1 {
    public function up($db) {
        // Add OAuth fields to user_settings table
        $db->exec("
            ALTER TABLE user_settings 
            ADD COLUMN oauth_consumer_key TEXT,
            ADD COLUMN oauth_consumer_secret TEXT,
            ADD COLUMN oauth_access_token TEXT,
            ADD COLUMN oauth_access_token_secret TEXT,
            ADD COLUMN oauth_token_expiry DATETIME
        ");
    }

    public function down($db) {
        // Create a new temporary table without OAuth columns
        $db->exec("
            CREATE TABLE user_settings_temp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discogs_username TEXT UNIQUE,
                discogs_token TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Copy data to temporary table
        $db->exec("
            INSERT INTO user_settings_temp 
            SELECT id, user_id, discogs_username, discogs_token, created_at, updated_at 
            FROM user_settings
        ");

        // Drop original table
        $db->exec("DROP TABLE user_settings");

        // Rename temp table to original
        $db->exec("ALTER TABLE user_settings_temp RENAME TO user_settings");

        // Recreate the index
        $db->exec("CREATE INDEX IF NOT EXISTS idx_user_settings_user_id ON user_settings(user_id)");
    }
} 