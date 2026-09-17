<?php

declare(strict_types=1);

use App\Database;
use App\FileController;
use App\FolderController;
use App\HttpException;

require dirname(__DIR__).'/bootstrap.php';

header('Content-Type: application/json');

function body(): array
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new HttpException(400, 'Invalid JSON body');
    }

    return $data;
}

function nameFrom(array $data): string
{
    $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';

    if ($name === '') {
        throw new HttpException(400, 'Name is required');
    }

    return $name;
}

function idFrom(array $data, string $key): int
{
    $id = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        throw new HttpException(400, "$key must be a positive integer");
    }

    return $id;
}

function queryId(string $key): ?int
{
    if (!isset($_GET[$key])) {
        return null;
    }

    $id = filter_var($_GET[$key], FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        throw new HttpException(400, "$key must be a positive integer");
    }

    return $id;
}

function queryText(string $key): string
{
    $value = isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';

    if ($value === '') {
        throw new HttpException(400, "$key is required");
    }

    return $value;
}

function paginationValue(string $key, int $default): int
{
    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);

    if ($value === false || $value < 0) {
        throw new HttpException(400, "$key must be a non-negative integer");
    }

    return $value;
}

function pageLimit(): int
{
    $limit = paginationValue('limit', 50);

    if ($limit < 1) {
        throw new HttpException(400, 'limit must be between 1 and 100');
    }

    return min($limit, 100);
}

function deleteFolder(FolderController $controller, int $id): array
{
    $controller->delete($id);

    return [204, null];
}

function deleteFile(FileController $controller, int $id): array
{
    $controller->delete($id);

    return [204, null];
}

try {
    $database = Database::connect();
    $folders = new FolderController($database);
    $files = new FileController($database);
    $method = $_SERVER['REQUEST_METHOD'];
    $path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $data = $method === 'POST' ? body() : [];
    $folderEntriesMatch = [];
    $folderDeleteMatch = [];
    $fileDeleteMatch = [];

    [$status, $response] = match (true) {
        $method === 'GET' && $path === '/api/health'
            => [200, ['status' => 'ok', 'database' => $database->query('SELECT 1') ? 'connected' : 'unavailable']],
        $method === 'GET' && preg_match('#^/api/folders/(\\d+)/entries$#', $path, $folderEntriesMatch) === 1
            => [200, $folders->entries(
                (int) $folderEntriesMatch[1],
                pageLimit(),
                paginationValue('folder_after_id', 0),
                paginationValue('file_after_id', 0),
                ($_GET['folders_done'] ?? '0') !== '1',
                ($_GET['files_done'] ?? '0') !== '1',
            )],
        $method === 'POST' && $path === '/api/folders'
            => [201, $folders->create(idFrom($data, 'parent_id'), nameFrom($data))],
        $method === 'DELETE' && preg_match('#^/api/folders/(\\d+)$#', $path, $folderDeleteMatch) === 1
            => deleteFolder($folders, (int) $folderDeleteMatch[1]),
        $method === 'GET' && $path === '/api/files/suggestions'
            => [200, $files->suggestions(queryText('prefix'), queryId('folder_id'))],
        $method === 'GET' && $path === '/api/files'
            => [200, $files->exact(queryText('name'), queryId('folder_id'))],
        $method === 'POST' && $path === '/api/files'
            => [201, $files->create(idFrom($data, 'folder_id'), nameFrom($data))],
        $method === 'DELETE' && preg_match('#^/api/files/(\\d+)$#', $path, $fileDeleteMatch) === 1
            => deleteFile($files, (int) $fileDeleteMatch[1]),
        default => [404, ['error' => 'Not found']],
    };
} catch (HttpException $exception) {
    [$status, $response] = [$exception->status, ['error' => $exception->getMessage()]];
} catch (Throwable $exception) {
    error_log((string) $exception);
    [$status, $response] = [500, ['error' => 'Internal server error']];
}

http_response_code($status);

if ($status !== 204) {
    echo json_encode($response);
}
