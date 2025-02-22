<?php

class CreateCollectionSearch {
    public function up($db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS collection_search (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                release_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                title TEXT,
                artist TEXT,
                label TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_collection_search_user 
            ON collection_search(user_id);

            CREATE INDEX IF NOT EXISTS idx_collection_search_title 
            ON collection_search(title);

            CREATE INDEX IF NOT EXISTS idx_collection_search_artist 
            ON collection_search(artist);

            CREATE INDEX IF NOT EXISTS idx_collection_search_label 
            ON collection_search(label);

            CREATE INDEX IF NOT EXISTS idx_collection_search_release 
            ON collection_search(release_id);
        ");
    }

    public function down($db) {
        $db->exec("
            DROP TABLE IF EXISTS collection_search;
        ");
    }
} 