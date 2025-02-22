<?php

class RemoveOAuthConsumerFieldsV1 {
    public function up($db) {
        // Create a new temporary table without consumer fields
        $db->exec("
            CREATE TABLE user_settings_temp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discogs_username TEXT UNIQUE,
                discogs_token TEXT,
                oauth_access_token TEXT,
                oauth_access_token_secret TEXT,
                oauth_token_expiry DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Copy data to temporary table
        $db->exec("
            INSERT INTO user_settings_temp 
            SELECT id, user_id, discogs_username, discogs_token, 
                   oauth_access_token, oauth_access_token_secret, oauth_token_expiry,
                   created_at, updated_at 
            FROM user_settings
        ");

        // Drop original table
        $db->exec("DROP TABLE user_settings");

        // Rename temp table to original
        $db->exec("ALTER TABLE user_settings_temp RENAME TO user_settings");

        // Recreate the index
        $db->exec("CREATE INDEX IF NOT EXISTS idx_user_settings_user_id ON user_settings(user_id)");
    }

    public function down($db) {
        // If we need to roll back, add the columns back
        $db->exec("ALTER TABLE user_settings ADD COLUMN oauth_consumer_key TEXT");
        $db->exec("ALTER TABLE user_settings ADD COLUMN oauth_consumer_secret TEXT");
    }
} 