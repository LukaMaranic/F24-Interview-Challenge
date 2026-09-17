<?php

declare(strict_types=1);

use App\Controller;
use App\Database;

require dirname(__DIR__).'/bootstrap.php';

header('Content-Type: application/json');

try {
    $controller = new Controller(Database::connect());
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $folderEntriesMatch = [];
    $folderDeleteMatch = [];
    $fileDeleteMatch = [];

    [$status, $response] = match (true) {
        $method === 'GET' && $path === '/api/health' => [200, $controller->health()],
        $method === 'GET' && preg_match('#^/api/folders/(\\d+)/entries$#', $path, $folderEntriesMatch) === 1
            => [200, $controller->folderEntries((int) $folderEntriesMatch[1])],
        $method === 'POST' && $path === '/api/folders'
            => [201, $controller->createFolder($body)],
        $method === 'DELETE' && preg_match('#^/api/folders/(\\d+)$#', $path, $folderDeleteMatch) === 1
            => $controller->deleteFolder((int) $folderDeleteMatch[1])
                ? [204, null]
                : [404, ['error' => 'Not found']],
        $method === 'GET' && $path === '/api/files/suggestions'
            => [200, $controller->suggestedFiles(
                $_GET['prefix'] ?? '',
                isset($_GET['folder_id']) ? (int) $_GET['folder_id'] : null,
            )],
        $method === 'GET' && $path === '/api/files'
            => [200, $controller->exactFiles(
                $_GET['name'] ?? '',
                isset($_GET['folder_id']) ? (int) $_GET['folder_id'] : null,
            )],
        $method === 'POST' && $path === '/api/files'
            => [201, $controller->createFile($body)],
        $method === 'DELETE' && preg_match('#^/api/files/(\\d+)$#', $path, $fileDeleteMatch) === 1
            => $controller->deleteFile((int) $fileDeleteMatch[1])
                ? [204, null]
                : [404, ['error' => 'Not found']],
        default => [404, ['error' => 'Not found']],
    };
} catch (Throwable) {
    [$status, $response] = [500, ['error' => 'Internal server error']];
}

http_response_code($status);

if ($status !== 204) {
    echo json_encode($response);
}
