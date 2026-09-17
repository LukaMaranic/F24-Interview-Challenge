<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Controller
{
    public function __construct(private PDO $database)
    {
    }

    public function health(): array
    {
        $this->database->query('SELECT 1');

        return ['status' => 'ok', 'database' => 'connected'];
    }

    public function folderEntries(int $folderId): array
    {
        $folders = $this->database->prepare(
            'SELECT id, parent_id, name FROM folders WHERE parent_id = ? ORDER BY name'
        );
        $folders->execute([$folderId]);

        $files = $this->database->prepare(
            'SELECT id, folder_id, name FROM files WHERE folder_id = ? ORDER BY name'
        );
        $files->execute([$folderId]);

        return ['folders' => $folders->fetchAll(), 'files' => $files->fetchAll()];
    }

    public function createFolder(array $data): array
    {
        $statement = $this->database->prepare('INSERT INTO folders (parent_id, name) VALUES (?, ?)');
        $statement->execute([$data['parent_id'], $data['name']]);

        return $this->folder((int) $this->database->lastInsertId());
    }

    public function createFile(array $data): array
    {
        $statement = $this->database->prepare('INSERT INTO files (folder_id, name) VALUES (?, ?)');
        $statement->execute([$data['folder_id'], $data['name']]);

        return $this->file((int) $this->database->lastInsertId());
    }

    public function exactFiles(string $name, ?int $folderId): array
    {
        $sql = 'SELECT id, folder_id, name FROM files WHERE name = ?';
        $parameters = [$name];

        if ($folderId !== null) {
            $sql .= ' AND folder_id = ?';
            $parameters[] = $folderId;
        }

        $statement = $this->database->prepare($sql.' ORDER BY id');
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function suggestedFiles(string $prefix, ?int $folderId): array
    {
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%';
        $sql = "SELECT id, folder_id, name FROM files WHERE name LIKE ? ESCAPE '\\\\'";
        $parameters = [$prefix];

        if ($folderId !== null) {
            $sql .= ' AND folder_id = ?';
            $parameters[] = $folderId;
        }

        $statement = $this->database->prepare($sql.' ORDER BY name, id LIMIT 10');
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function deleteFolder(int $id): bool
    {
        $statement = $this->database->prepare('DELETE FROM folders WHERE id = ? AND parent_id IS NOT NULL');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    public function deleteFile(int $id): bool
    {
        $statement = $this->database->prepare('DELETE FROM files WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    private function folder(int $id): array
    {
        $statement = $this->database->prepare('SELECT id, parent_id, name FROM folders WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch();
    }

    private function file(int $id): array
    {
        $statement = $this->database->prepare('SELECT id, folder_id, name FROM files WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch();
    }
}
