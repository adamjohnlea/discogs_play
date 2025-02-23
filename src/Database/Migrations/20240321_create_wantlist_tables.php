<?php

class CreateWantlistTables {
    public function up($pdo) {
        // Create wantlist_items table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wantlist_items (
                id INTEGER PRIMARY KEY,
                data TEXT,
                is_basic_data INTEGER DEFAULT 1,
                last_updated DATETIME,
                user_id INTEGER,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Create wantlist_images table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wantlist_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                wantlist_item_id INTEGER,
                user_id INTEGER NOT NULL,
                type TEXT,
                original_url TEXT,
                file_path TEXT,
                mime_type TEXT,
                last_updated DATETIME,
                FOREIGN KEY (wantlist_item_id) REFERENCES wantlist_items(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Create wantlist_search table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wantlist_search (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                release_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                title TEXT,
                artist TEXT,
                label TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE INDEX IF NOT EXISTS idx_wantlist_search_user 
            ON wantlist_search(user_id);

            CREATE INDEX IF NOT EXISTS idx_wantlist_search_title 
            ON wantlist_search(title);

            CREATE INDEX IF NOT EXISTS idx_wantlist_search_artist 
            ON wantlist_search(artist);

            CREATE INDEX IF NOT EXISTS idx_wantlist_search_label 
            ON wantlist_search(label);

            CREATE INDEX IF NOT EXISTS idx_wantlist_search_release 
            ON wantlist_search(release_id);
        ");

        // Create indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wantlist_items_user ON wantlist_items(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wantlist_images_item ON wantlist_images(wantlist_item_id)");
    }

    public function down($pdo) {
        // Drop indexes first
        $pdo->exec("DROP INDEX IF EXISTS idx_wantlist_search_user");
        $pdo->exec("DROP INDEX IF EXISTS idx_wantlist_search_title");
        $pdo->exec("DROP INDEX IF EXISTS idx_wantlist_search_artist");
        $pdo->exec("DROP INDEX IF EXISTS idx_wantlist_search_label");
        $pdo->exec("DROP INDEX IF EXISTS idx_wantlist_search_release");

        // Drop tables in reverse order of creation (due to foreign key constraints)
        $pdo->exec("DROP TABLE IF EXISTS wantlist_search");
        $pdo->exec("DROP TABLE IF EXISTS wantlist_images");
        $pdo->exec("DROP TABLE IF EXISTS wantlist_items");
    }
} 