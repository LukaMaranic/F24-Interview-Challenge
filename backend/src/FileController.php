<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class FileController
{
    public function __construct(private PDO $database)
    {
    }

    public function create(int $folderId, string $name): array
    {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare('SELECT id FROM folders WHERE id = ? FOR UPDATE');
            $statement->execute([$folderId]);

            if (!$statement->fetchColumn()) {
                throw new HttpException(404, 'Folder not found');
            }

            $this->assertNameAvailable($folderId, $name);

            $statement = $this->database->prepare('INSERT INTO files (folder_id, name) VALUES (?, ?)');
            $statement->execute([$folderId, $name]);
            $id = (int) $this->database->lastInsertId();
            $this->database->commit();

            return $this->file($id);
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function exact(string $name, ?int $folderId): array
    {
        $sql = 'SELECT files.id, files.folder_id, files.name, folders.name AS folder_name
                FROM files
                JOIN folders ON folders.id = files.folder_id
                WHERE files.name = ?';
        $parameters = [$name];

        if ($folderId !== null) {
            $sql .= ' AND files.folder_id = ?';
            $parameters[] = $folderId;
        }

        $statement = $this->database->prepare($sql.' ORDER BY files.id');
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function suggestions(string $prefix, ?int $folderId): array
    {
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%';
        $sql = "SELECT files.id, files.folder_id, files.name, folders.name AS folder_name
                FROM files
                JOIN folders ON folders.id = files.folder_id
                WHERE files.name LIKE ? ESCAPE '\\\\'";
        $parameters = [$prefix];

        if ($folderId !== null) {
            $sql .= ' AND files.folder_id = ?';
            $parameters[] = $folderId;
        }

        $statement = $this->database->prepare($sql.' ORDER BY files.name, files.id LIMIT 10');
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function delete(int $id): void
    {
        $statement = $this->database->prepare('DELETE FROM files WHERE id = ?');
        $statement->execute([$id]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(404, 'File not found');
        }
    }

    private function assertNameAvailable(int $folderId, string $name): void
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM files WHERE folder_id = ? AND name = ?
             UNION ALL
             SELECT 1 FROM folders WHERE parent_id = ? AND name = ?
             LIMIT 1'
        );
        $statement->execute([$folderId, $name, $folderId, $name]);

        if ($statement->fetchColumn()) {
            throw new HttpException(409, 'Name already exists in this folder');
        }
    }

    private function file(int $id): array
    {
        $statement = $this->database->prepare(
            'SELECT files.id, files.folder_id, files.name, folders.name AS folder_name
             FROM files
             JOIN folders ON folders.id = files.folder_id
             WHERE files.id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch();
    }
}
