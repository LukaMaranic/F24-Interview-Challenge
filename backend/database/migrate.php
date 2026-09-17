<?php

declare(strict_types=1);

use App\Database;

require dirname(__DIR__).'/bootstrap.php';

$database = Database::connect();
$database->exec('DROP TABLE IF EXISTS items');
$database->exec(
    'CREATE TABLE IF NOT EXISTS folders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id BIGINT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        UNIQUE KEY folders_parent_name_unique (parent_id, name),
        CONSTRAINT folders_parent_foreign
            FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
    )'
);
$database->exec(
    'CREATE TABLE IF NOT EXISTS files (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        folder_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        UNIQUE KEY files_folder_name_unique (folder_id, name),
        INDEX files_name_index (name),
        CONSTRAINT files_folder_foreign
            FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE
    )'
);
$database->exec(
    "INSERT INTO folders (parent_id, name)
     SELECT NULL, 'root'
     WHERE NOT EXISTS (SELECT 1 FROM folders WHERE parent_id IS NULL)"
);

echo "Migrations completed.\n";
