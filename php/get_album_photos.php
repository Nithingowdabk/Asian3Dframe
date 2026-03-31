<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$id = trim((string)($_GET['id'] ?? ''));
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

$coverRelativeInsideAlbum = null;
$metaPath = $albumDir . DIRECTORY_SEPARATOR . 'meta.json';
if (is_file($metaPath)) {
    $metaRaw = file_get_contents($metaPath);
    $meta = json_decode((string)$metaRaw, true);
    if (is_array($meta) && !empty($meta['cover_photo'])) {
        $coverPath = (string)$meta['cover_photo'];
        $prefix = 'albums/' . $id . '/';
        if (strpos($coverPath, $prefix) === 0) {
            $coverRelativeInsideAlbum = substr($coverPath, strlen($prefix));
        }
    }
}

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$photos = [];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($albumDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;

    $basename = $file->getBasename();
    if ($basename === 'meta.json') continue;

    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) continue;

    $fullPath = $file->getPathname();
    $relativeInsideAlbum = substr($fullPath, strlen($albumDir) + 1);
    $relativeInsideAlbum = str_replace(DIRECTORY_SEPARATOR, '/', $relativeInsideAlbum);
    if ($coverRelativeInsideAlbum !== null && $relativeInsideAlbum === $coverRelativeInsideAlbum) {
        continue;
    }
    $photos[] = 'albums/' . $id . '/' . $relativeInsideAlbum;
}

sort($photos);
json_out(['success' => true, 'photos' => $photos]);

