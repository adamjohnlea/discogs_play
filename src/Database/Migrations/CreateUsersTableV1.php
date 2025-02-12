<?php

class CreateUsersTableV1 {
    public function up($db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE COLLATE NOCASE,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                remember_token TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create indexes for performance
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username COLLATE NOCASE)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email COLLATE NOCASE)");
    }

    public function down($db) {
        $db->exec("DROP INDEX IF EXISTS idx_users_email");
        $db->exec("DROP INDEX IF EXISTS idx_users_username");
        $db->exec("DROP TABLE IF EXISTS users");
    }
} 