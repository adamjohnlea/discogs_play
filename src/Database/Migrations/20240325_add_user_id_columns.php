<?php

class AddUserIdColumns {
    public function up($pdo) {
        // Check if user_id column exists in releases table
        $result = $pdo->query("PRAGMA table_info(releases)")->fetchAll(PDO::FETCH_ASSOC);
        $hasUserIdColumn = false;
        
        foreach ($result as $column) {
            if ($column['name'] === 'user_id') {
                $hasUserIdColumn = true;
                break;
            }
        }
        
        // Add user_id column if it doesn't exist
        if (!$hasUserIdColumn) {
            $pdo->exec("
                ALTER TABLE releases 
                ADD COLUMN user_id INTEGER DEFAULT 1
            ");
            
            echo "Added user_id column to releases table\n";
        }
        
        // Check if images table needs user_id column
        $result = $pdo->query("PRAGMA table_info(images)")->fetchAll(PDO::FETCH_ASSOC);
        $hasUserIdColumn = false;
        
        foreach ($result as $column) {
            if ($column['name'] === 'user_id') {
                $hasUserIdColumn = true;
                break;
            }
        }
        
        // Add user_id column if it doesn't exist
        if (!$hasUserIdColumn) {
            $pdo->exec("
                ALTER TABLE images 
                ADD COLUMN user_id INTEGER DEFAULT 1
            ");
            
            echo "Added user_id column to images table\n";
        }
    }
    
    public function down($pdo) {
        // SQLite doesn't support dropping columns without recreating the table
        // For simplicity, we'll just leave the columns in place
        echo "Skipping downgrade: SQLite doesn't support dropping columns\n";
    }
} 