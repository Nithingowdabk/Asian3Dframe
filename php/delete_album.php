<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function rrmdir(string $dir): bool {
    if (!is_dir($dir)) return false;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    return @rmdir($dir);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
$raw = file_get_contents('php://input');
$parsed = json_decode((string)$raw, true);

$id = '';
if (is_array($parsed) && isset($parsed['id'])) {
    $id = trim((string)$parsed['id']);
} elseif (isset($_POST['id'])) {
    $id = trim((string)$_POST['id']);
}

if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
    json_out(['success' => false, 'message' => 'Invalid album id'], 400);
}

$uploadsBase = realpath(__DIR__ . '/../uploads');
if ($uploadsBase === false) {
    json_out(['success' => false, 'message' => 'Uploads directory not found'], 500);
}

$albumDir = $uploadsBase . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id;
if (!is_dir($albumDir)) {
    json_out(['success' => false, 'message' => 'Album not found'], 404);
}

if (!rrmdir($albumDir)) {
    json_out(['success' => false, 'message' => 'Failed to delete album'], 500);
}

json_out(['success' => true]);
