<?php

declare(strict_types=1);

use App\Database;

require dirname(__DIR__).'/bootstrap.php';

$database = Database::connect();
$rootId = $database->query('SELECT id FROM folders WHERE parent_id IS NULL LIMIT 1')->fetchColumn();

$statement = $database->prepare('INSERT IGNORE INTO folders (parent_id, name) VALUES (?, ?)');
$statement->execute([$rootId, 'Documents']);

$statement = $database->prepare('SELECT id FROM folders WHERE parent_id = ? AND name = ?');
$statement->execute([$rootId, 'Documents']);
$documentsId = $statement->fetchColumn();

$statement = $database->prepare('INSERT IGNORE INTO folders (parent_id, name) VALUES (?, ?)');
$statement->execute([$documentsId, 'Work']);

$statement = $database->prepare('INSERT IGNORE INTO files (folder_id, name) VALUES (?, ?), (?, ?)');
$statement->execute([$rootId, 'notes.txt', $documentsId, 'report.pdf']);

echo "Database seeded.\n";
