<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    exit;
}

function normalize_image_entry(string $path): string
{
    $value = trim($path);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }
    if (str_starts_with($value, 'assets/')) {
        return $value;
    }
    return 'assets/images/products/' . ltrim($value, '/');
}

function normalize_image_entries(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $raw = trim((string)$value);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $parts = $decoded;
        } else {
            $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        }
    }

    $images = [];
    foreach ($parts as $part) {
        $normalized = normalize_image_entry((string)$part);
        if ($normalized !== '' && !in_array($normalized, $images, true)) {
            $images[] = $normalized;
        }
    }

    return $images;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$id          = isset($body['id']) ? (int)$body['id'] : 0;
$name        = isset($body['name']) ? trim((string)$body['name']) : '';
$description = isset($body['description']) ? trim((string)$body['description']) : '';
$price       = isset($body['price']) ? (float)$body['price'] : 0;
$oldPrice    = isset($body['old_price']) && $body['old_price'] !== null ? (float)$body['old_price'] : null;
$categoryRaw = isset($body['category']) ? trim((string)$body['category']) : '';
$categoryId  = isset($body['category_id']) ? (int)$body['category_id'] : 0;
$stock       = isset($body['stock']) ? (int)$body['stock'] : 0;
$isBestSeller = isset($body['is_best_seller']) ? (int)$body['is_best_seller'] : 0;
$isBestSeller = $isBestSeller === 1 ? 1 : 0;
$images      = normalize_image_entries($body['images'] ?? ($body['image'] ?? ''));

if ($categoryId <= 0 && $categoryRaw !== '' && ctype_digit($categoryRaw)) {
    $categoryId = (int)$categoryRaw;
}

$categoryName = '';
if ($hasProductCategoryId && $hasCategoriesTable) {
    if ($categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare error.']);
        exit;
    }
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data.']);
        exit;
    }
    $categoryName = (string)$row['name'];
} else {
    $categoryName = $categoryRaw;
    $validCategories = ['mobile', 'normal'];
    if (!in_array($categoryName, $validCategories, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data.']);
        exit;
    }
}

if ($id <= 0 || $name === '' || $description === '' || $price <= 0 || $stock < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product data.']);
    exit;
}

foreach ($images as $image) {
    if (!preg_match('/^(assets\/images\/products\/[a-zA-Z0-9_\/-]+\.(jpg|jpeg|png|webp)|https?:\/\/[^\s]+)$/i', $image)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image path.']);
        exit;
    }
}

if (!$images) {
    if ($hasProductCategoryId && $hasCategoriesTable) {
        $stmt = $conn->prepare(
            'UPDATE products
             SET name = ?, description = ?, price = ?, old_price = ?, category_id = ?, category = ?, stock = ?, is_best_seller = ?
             WHERE id = ?'
        );
    } else {
        $stmt = $conn->prepare(
            'UPDATE products
             SET name = ?, description = ?, price = ?, old_price = ?, category = ?, stock = ?, is_best_seller = ?
             WHERE id = ?'
        );
    }
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare error.']);
        exit;
    }
    if ($hasProductCategoryId && $hasCategoriesTable) {
        $stmt->bind_param('ssddisiii', $name, $description, $price, $oldPrice, $categoryId, $categoryName, $stock, $isBestSeller, $id);
    } else {
        $stmt->bind_param('ssddsiii', $name, $description, $price, $oldPrice, $categoryName, $stock, $isBestSeller, $id);
    }
} else {
    $imagePayload = json_encode($images, JSON_UNESCAPED_SLASHES);
    if ($hasProductCategoryId && $hasCategoriesTable) {
        $stmt = $conn->prepare(
            'UPDATE products
             SET name = ?, description = ?, price = ?, old_price = ?, category_id = ?, category = ?, image = ?, stock = ?, is_best_seller = ?
             WHERE id = ?'
        );
    } else {
        $stmt = $conn->prepare(
            'UPDATE products
             SET name = ?, description = ?, price = ?, old_price = ?, category = ?, image = ?, stock = ?, is_best_seller = ?
             WHERE id = ?'
        );
    }
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare error.']);
        exit;
    }
    if ($hasProductCategoryId && $hasCategoriesTable) {
        $stmt->bind_param('ssddissiii', $name, $description, $price, $oldPrice, $categoryId, $categoryName, $imagePayload, $stock, $isBestSeller, $id);
    } else {
        $stmt->bind_param('ssddssiii', $name, $description, $price, $oldPrice, $categoryName, $imagePayload, $stock, $isBestSeller, $id);
    }
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update product.']);
    $stmt->close();
    exit;
}

if ($conn->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found or unchanged.']);
    $stmt->close();
    exit;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Product updated successfully.',
]);
