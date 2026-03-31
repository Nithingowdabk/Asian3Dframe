<?php
/**
 * upload_photo.php
 * Accepts an image upload, validates it securely, stores it in /uploads/
 * and returns the saved file path as JSON.
 *
 * POST /php/upload_photo.php  (multipart/form-data, field name: "photo")
 *
 * Response (JSON):
 *   { "success": true, "path": "uploads/abc123.jpg", "filename": "abc123.jpg" }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

/* ── Check file was received ───────────────────────────────────────── */
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server size limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
    ];
    $code = isset($_FILES['photo']) ? (int) $_FILES['photo']['error'] : UPLOAD_ERR_NO_FILE;
    $msg  = $codes[$code] ?? 'Unknown upload error.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$file = $_FILES['photo'];

/* ── Size check: max 5 MB ──────────────────────────────────────────── */
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5 MB allowed.']);
    exit;
}

/* ── MIME validation via finfo (not extension / client header) ─────── */
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG and WEBP are accepted.']);
    exit;
}

$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$ext    = $extMap[$mimeType];

/* ── Generate cryptographically random filename ────────────────────── */
$filename  = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory is unavailable.']);
        exit;
    }
}

/* ── Move temp file to uploads/ ────────────────────────────────────── */
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
    exit;
}

/* ── Save upload record in DB (optional — for order linking) ────────── */
require_once __DIR__ . '/db.php';

$photoPath = 'uploads/' . $filename;

// Store in session so place_order.php can access it
session_start();
$_SESSION['last_uploaded_photo'] = $photoPath;

echo json_encode([
    'success'  => true,
    'message'  => 'Photo uploaded successfully.',
    'path'     => $photoPath,
    'filename' => $filename,
]);
