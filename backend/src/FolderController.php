<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class FolderController
{
    public function __construct(private PDO $database)
    {
    }

    public function entries(
        int $folderId,
        int $limit,
        int $folderAfterId,
        int $fileAfterId,
        bool $includeFolders,
        bool $includeFiles,
    ): array {
        $statement = $this->database->prepare('SELECT 1 FROM folders WHERE id = ?');
        $statement->execute([$folderId]);

        if (!$statement->fetchColumn()) {
            throw new HttpException(404, 'Folder not found');
        }

        [$folders, $nextFolderId] = $includeFolders
            ? $this->folderPage($folderId, $folderAfterId, $limit)
            : [[], null];
        [$files, $nextFileId] = $includeFiles
            ? $this->filePage($folderId, $fileAfterId, $limit)
            : [[], null];

        return [
            'folders' => $folders,
            'files' => $files,
            'next_folder_after_id' => $nextFolderId,
            'next_file_after_id' => $nextFileId,
        ];
    }

    public function create(int $parentId, string $name): array
    {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare('SELECT id FROM folders WHERE id = ? FOR UPDATE');
            $statement->execute([$parentId]);

            if (!$statement->fetchColumn()) {
                throw new HttpException(404, 'Parent folder not found');
            }

            $this->assertNameAvailable($parentId, $name);

            $statement = $this->database->prepare('INSERT INTO folders (parent_id, name) VALUES (?, ?)');
            $statement->execute([$parentId, $name]);
            $id = (int) $this->database->lastInsertId();
            $this->database->commit();

            return $this->folder($id);
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->database->prepare('SELECT parent_id FROM folders WHERE id = ?');
        $statement->execute([$id]);
        $folder = $statement->fetch();

        if (!$folder) {
            throw new HttpException(404, 'Folder not found');
        }

        if ($folder['parent_id'] === null) {
            throw new HttpException(400, 'Root folder cannot be deleted');
        }

        $statement = $this->database->prepare('DELETE FROM folders WHERE id = ?');
        $statement->execute([$id]);
    }

    private function folderPage(int $folderId, int $afterId, int $limit): array
    {
        $statement = $this->database->prepare(
            'SELECT id, parent_id, name
             FROM folders
             WHERE parent_id = :folder_id AND id > :after_id
             ORDER BY id
             LIMIT :page_size'
        );
        $statement->bindValue('folder_id', $folderId, PDO::PARAM_INT);
        $statement->bindValue('after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue('page_size', $limit + 1, PDO::PARAM_INT);
        $statement->execute();

        return $this->page($statement->fetchAll(), $limit);
    }

    private function filePage(int $folderId, int $afterId, int $limit): array
    {
        $statement = $this->database->prepare(
            'SELECT id, folder_id, name
             FROM files
             WHERE folder_id = :folder_id AND id > :after_id
             ORDER BY id
             LIMIT :page_size'
        );
        $statement->bindValue('folder_id', $folderId, PDO::PARAM_INT);
        $statement->bindValue('after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue('page_size', $limit + 1, PDO::PARAM_INT);
        $statement->execute();

        return $this->page($statement->fetchAll(), $limit);
    }

    private function page(array $rows, int $limit): array
    {
        if (count($rows) <= $limit) {
            return [$rows, null];
        }

        array_pop($rows);

        return [$rows, (int) $rows[array_key_last($rows)]['id']];
    }

    private function assertNameAvailable(int $parentId, string $name): void
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM folders WHERE parent_id = ? AND name = ?
             UNION ALL
             SELECT 1 FROM files WHERE folder_id = ? AND name = ?
             LIMIT 1'
        );
        $statement->execute([$parentId, $name, $parentId, $name]);

        if ($statement->fetchColumn()) {
            throw new HttpException(409, 'Name already exists in this folder');
        }
    }

    private function folder(int $id): array
    {
        $statement = $this->database->prepare('SELECT id, parent_id, name FROM folders WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch();
    }
}
