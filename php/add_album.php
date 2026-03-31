<?php
declare(strict_types=1);

// Always return JSON, even if PHP hits warnings/fatals.
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)$err['type'], $fatalTypes, true)) return;

    if (ob_get_length()) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, 500);
    } else {
        http_response_code(500);
    }

    echo json_encode([
        'success' => false,
        'message' => 'Server error while uploading album.',
        'error' => (string)($err['message'] ?? 'Unknown error'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
});

function safe_segment(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/', '-', $s);
    $s = preg_replace('/[^A-Za-z0-9._-]+/', '_', $s);
    $s = trim($s, '._-');
    return $s;
}

function safe_relpath(string $path): string {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^/+#', '', $path);
    $path = preg_replace('#\.+/#', '', $path);
    $parts = array_values(array_filter(explode('/', $path), fn($p) => $p !== '' && $p !== '.' && $p !== '..'));
    $safe = [];
    foreach ($parts as $p) {
        $seg = safe_segment($p);
        if ($seg === '') continue;
        $safe[] = $seg;
    }
    return implode('/', $safe);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
$name = trim((string)($_POST['name'] ?? ''));
if ($name === '') {
    json_out(['success' => false, 'message' => 'Album name is required'], 400);
}

$nameLen = function_exists('mb_strlen') ? (int)mb_strlen($name) : (int)strlen($name);
if ($nameLen > 80) {
    json_out(['success' => false, 'message' => 'Album name is too long'], 400);
}

$files = $_FILES['photos'] ?? null;
if (!$files || !isset($files['name'])) {
    json_out(['success' => false, 'message' => 'No photos uploaded'], 400);
}

$cover = $_FILES['profile_photo'] ?? null;
if (!$cover || !isset($cover['tmp_name']) || (int)($cover['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_out(['success' => false, 'message' => 'Profile photo is required'], 400);
}

$uploadsBase = realpath(__DIR__ . '/../uploads');
if ($uploadsBase === false) {
    json_out(['success' => false, 'message' => 'Uploads directory not found'], 500);
}

$albumsBase = $uploadsBase . DIRECTORY_SEPARATOR . 'albums';
if (!is_dir($albumsBase) && !mkdir($albumsBase, 0775, true)) {
    json_out(['success' => false, 'message' => 'Failed to create albums directory'], 500);
}

$lower = function_exists('mb_strtolower') ? (string)mb_strtolower($name) : (string)strtolower($name);
$slug = safe_segment($lower);
if ($slug === '') $slug = 'album';
$albumId = $slug . '_' . date('Ymd_His');
$albumDir = $albumsBase . DIRECTORY_SEPARATOR . $albumId;
if (!mkdir($albumDir, 0775, true)) {
    json_out(['success' => false, 'message' => 'Failed to create album folder'], 500);
}

$coverTmp = (string)($cover['tmp_name'] ?? '');
if ($coverTmp === '' || !is_uploaded_file($coverTmp)) {
    json_out(['success' => false, 'message' => 'Invalid profile photo upload'], 400);
}

$coverInfo = @getimagesize($coverTmp);
if ($coverInfo === false) {
    json_out(['success' => false, 'message' => 'Profile photo must be an image'], 400);
}

$coverOrigName = (string)($cover['name'] ?? 'cover.jpg');
$coverExt = strtolower((string)pathinfo($coverOrigName, PATHINFO_EXTENSION));
if ($coverExt === '') {
    $coverExt = 'jpg';
}
$allowedCoverExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($coverExt, $allowedCoverExt, true)) {
    $coverExt = 'jpg';
}

$coverFile = 'cover.' . $coverExt;
$coverTarget = $albumDir . DIRECTORY_SEPARATOR . $coverFile;
if (!move_uploaded_file($coverTmp, $coverTarget)) {
    json_out(['success' => false, 'message' => 'Failed to save profile photo'], 500);
}

$coverRelative = 'albums/' . $albumId . '/' . $coverFile;

$meta = [
    'id' => $albumId,
    'name' => $name,
    'created_at' => date('c'),
    'cover_photo' => $coverRelative,
];
file_put_contents($albumDir . DIRECTORY_SEPARATOR . 'meta.json', json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$names = $files['name'];
$tmpNames = $files['tmp_name'] ?? [];
$errors = $files['error'] ?? [];

// Normalize to arrays
if (!is_array($names)) {
    $names = [$names];
    $tmpNames = [$tmpNames];
    $errors = [$errors];
}

$maxAlbumPhotos = 500;
if (count($names) > $maxAlbumPhotos) {
    json_out([
        'success' => false,
        'message' => 'Maximum 500 images allowed per album upload',
    ], 400);
}

$uploaded = [];
$failures = [];

for ($i = 0; $i < count($names); $i++) {
    $origName = (string)($names[$i] ?? '');
    $tmp = (string)($tmpNames[$i] ?? '');
    $err = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);

    if ($err !== UPLOAD_ERR_OK) {
        $failures[] = ['file' => $origName, 'error' => $err];
        continue;
    }
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $failures[] = ['file' => $origName, 'error' => 'not_uploaded'];
        continue;
    }

    // Basic image validation
    $imgInfo = @getimagesize($tmp);
    if ($imgInfo === false) {
        $failures[] = ['file' => $origName, 'error' => 'not_image'];
        continue;
    }

    $rel = safe_relpath($origName);
    if ($rel === '') {
        $rel = safe_segment(basename($origName));
    }
    if ($rel === '') {
        $rel = 'photo_' . ($i + 1);
    }

    $target = $albumDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        $failures[] = ['file' => $origName, 'error' => 'mkdir_failed'];
        continue;
    }

    // Ensure target stays within albumDir
    $realTargetDir = realpath($targetDir);
    $realAlbumDir = realpath($albumDir);
    if ($realTargetDir === false || $realAlbumDir === false || strpos($realTargetDir, $realAlbumDir) !== 0) {
        $failures[] = ['file' => $origName, 'error' => 'invalid_path'];
        continue;
    }

    // Avoid collisions
    $baseName = pathinfo($target, PATHINFO_FILENAME);
    $ext = pathinfo($target, PATHINFO_EXTENSION);
    $ext = $ext ? ('.' . $ext) : '';
    $candidate = $target;
    $n = 1;
    while (file_exists($candidate)) {
        $candidate = $targetDir . DIRECTORY_SEPARATOR . $baseName . '_' . $n . $ext;
        $n++;
        if ($n > 999) break;
    }

    if (!move_uploaded_file($tmp, $candidate)) {
        $failures[] = ['file' => $origName, 'error' => 'move_failed'];
        continue;
    }

    $relativeFromUploads = 'albums/' . $albumId . '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($candidate, strlen($albumDir) + 1));
    $uploaded[] = $relativeFromUploads;
}

json_out([
    'success' => true,
    'album' => ['id' => $albumId, 'name' => $name, 'cover_photo' => $coverRelative],
    'uploaded_count' => count($uploaded),
    'photos' => $uploaded,
    'failures' => $failures,
]);
