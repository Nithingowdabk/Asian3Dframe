<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function respond(string $status, string $message, int $http = 200, array $extra = []): void
{
    http_response_code($http);
    echo json_encode(array_merge([
        'status'  => $status,
        'message' => $message,
    ], $extra));
    exit;
}

set_exception_handler(function (Throwable $e): void {
    respond('error', 'Upload failed', 500);
});

function collect_uploaded_images(): array
{
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
        $files = [];
        $names = $_FILES['images']['name'];
        foreach ($names as $index => $name) {
            if (($name ?? '') === '') {
                continue;
            }
            $files[] = [
                'name'     => $name,
                'type'     => $_FILES['images']['type'][$index] ?? '',
                'tmp_name' => $_FILES['images']['tmp_name'][$index] ?? '',
                'error'    => $_FILES['images']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $_FILES['images']['size'][$index] ?? 0,
            ];
        }
        return $files;
    }

    if (isset($_FILES['image']) && ($_FILES['image']['name'] ?? '') !== '') {
        return [$_FILES['image']];
    }

    return [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Method not allowed', 405);
}

require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
require_once __DIR__ . '/db.php';

function db_has_table(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function db_has_column(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function ensure_best_seller_column(mysqli $conn): bool
{
    if (db_has_column($conn, 'products', 'is_best_seller')) {
        return true;
    }

    $ok = $conn->query(
        'ALTER TABLE products ADD COLUMN is_best_seller TINYINT(1) NOT NULL DEFAULT 0 AFTER stock'
    );

    if (!$ok) {
        return false;
    }

    return db_has_column($conn, 'products', 'is_best_seller');
}

$hasCategoriesTable = db_has_table($conn, 'categories');
$hasProductCategoryId = db_has_column($conn, 'products', 'category_id');
if (!ensure_best_seller_column($conn)) {
    respond('error', 'Database update failed', 500);
}

$name        = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$oldPrice    = isset($_POST['old_price']) && $_POST['old_price'] !== '' ? (float)$_POST['old_price'] : null;
$categoryRaw = trim((string)($_POST['category'] ?? ''));
$categoryId  = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$stock       = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
$bestSellerRaw = strtolower(trim((string)($_POST['is_best_seller'] ?? '0')));
$isBestSeller = in_array($bestSellerRaw, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;

if ($categoryId <= 0 && $categoryRaw !== '' && ctype_digit($categoryRaw)) {
    $categoryId = (int)$categoryRaw;
}

$categoryName = '';
if ($hasProductCategoryId && $hasCategoriesTable) {
    if ($categoryId <= 0) {
        respond('error', 'Invalid product data', 422);
    }

    $stmt = $conn->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
    if (!$stmt) {
        respond('error', 'Upload failed', 500);
    }
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        respond('error', 'Invalid product data', 422);
    }
    $categoryName = (string)$row['name'];
} else {
    // Legacy behavior (before categories table/category_id existed)
    $categoryName = $categoryRaw;
    $validCategories = ['mobile', 'normal'];
    if (!in_array($categoryName, $validCategories, true)) {
        respond('error', 'Invalid product data', 422);
    }
}

if ($name === '' || $description === '' || $stock < 0) {
    respond('error', 'Invalid product data', 422);
}

if (!($uploadedFiles = collect_uploaded_images())) {
    respond('error', 'Upload failed', 422);
}

if (count($uploadedFiles) > 6) {
    respond('error', 'Upload failed', 422);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$uploadDir = __DIR__ . '/../assets/images/products';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    respond('error', 'Upload failed', 500);
}

$imagePaths = [];
foreach ($uploadedFiles as $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        respond('error', 'Upload failed', 422);
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        respond('error', 'Upload failed', 422);
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($file['tmp_name']);
    }
    if (!isset($allowed[$mime])) {
        respond('error', 'Upload failed', 422);
    }

    $filename = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        respond('error', 'Upload failed', 500);
    }

    $imagePaths[] = 'assets/images/products/' . $filename;
}

$imagePayload = json_encode($imagePaths, JSON_UNESCAPED_SLASHES);

$stmt = null;
if ($hasProductCategoryId && $hasCategoriesTable) {
    $stmt = $conn->prepare(
        'INSERT INTO products (name, description, price, old_price, category_id, category, stock, is_best_seller, image, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
} else {
    $stmt = $conn->prepare(
        'INSERT INTO products (name, description, price, old_price, category, stock, is_best_seller, image, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
}
if (!$stmt) {
    respond('error', 'Upload failed', 500);
}

if ($hasProductCategoryId && $hasCategoriesTable) {
    $stmt->bind_param('ssddisiis', $name, $description, $price, $oldPrice, $categoryId, $categoryName, $stock, $isBestSeller, $imagePayload);
} else {
    $stmt->bind_param('ssddsiis', $name, $description, $price, $oldPrice, $categoryName, $stock, $isBestSeller, $imagePayload);
}

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Upload failed', 500);
}

$newId = (int)$conn->insert_id;
$stmt->close();

respond('success', 'Product added successfully', 200, [
    'id'     => $newId,
    'image'  => $imagePaths[0] ?? '',
    'images' => $imagePaths,
]);
