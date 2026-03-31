<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$uploadsBase = realpath(__DIR__ . '/../uploads');
if ($uploadsBase === false) {
    json_out(['success' => false, 'message' => 'Uploads directory not found'], 500);
}

$albumsBase = $uploadsBase . DIRECTORY_SEPARATOR . 'albums';
if (!is_dir($albumsBase)) {
    json_out(['success' => true, 'albums' => []]);
}

$albums = [];
$dir = new DirectoryIterator($albumsBase);
foreach ($dir as $entry) {
    if ($entry->isDot() || !$entry->isDir()) continue;

    $id = $entry->getFilename();
    $metaPath = $entry->getPathname() . DIRECTORY_SEPARATOR . 'meta.json';
    $name = $id;
    $createdAt = null;
    $coverPhoto = null;

    if (is_file($metaPath)) {
        $metaRaw = file_get_contents($metaPath);
        $meta = json_decode((string)$metaRaw, true);
        if (is_array($meta)) {
            $name = (string)($meta['name'] ?? $name);
            $createdAt = $meta['created_at'] ?? null;
            $coverPhoto = isset($meta['cover_photo']) ? (string)$meta['cover_photo'] : null;
        }
    }

    $albums[] = [
        'id' => $id,
        'name' => $name,
        'created_at' => $createdAt,
        'cover_photo' => $coverPhoto,
    ];
}

usort($albums, function ($a, $b) {
    $at = (string)($a['created_at'] ?? '');
    $bt = (string)($b['created_at'] ?? '');
    // Newest first (fallback to id)
    return strcmp($bt ?: (string)$b['id'], $at ?: (string)$a['id']);
});

json_out(['success' => true, 'albums' => $albums]);
