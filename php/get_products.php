<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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

$hasCategoriesTable = db_has_table($conn, 'categories');
$hasProductCategoryId = db_has_column($conn, 'products', 'category_id');
$hasBestSellerColumn = db_has_column($conn, 'products', 'is_best_seller');

function normalize_image_path(?string $path): string
{
    $value = trim((string)($path ?? ''));
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

function normalize_image_list(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $raw = trim((string)($value ?? ''));
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
        $normalized = normalize_image_path((string)$part);
        if ($normalized !== '' && !in_array($normalized, $images, true)) {
            $images[] = $normalized;
        }
    }

    return $images;
}

$id       = isset($_GET['id'])       ? (int)$_GET['id']         : 0;
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search   = isset($_GET['q'])        ? trim($_GET['q'])        : '';
$limit    = isset($_GET['limit'])    ? min((int)$_GET['limit'], 100) : 100;

if ($categoryId <= 0 && $category !== '' && ctype_digit($category)) {
    $categoryId = (int)$category;
}

/* ── Get one product by ID ── */
if ($id > 0) {
    if ($hasProductCategoryId && $hasCategoriesTable) {
        $bestSellerSelect = $hasBestSellerColumn ? ', p.is_best_seller' : '';
        $stmt = $conn->prepare(
            'SELECT p.id, p.name, p.description, p.price, p.old_price, p.category, p.category_id,
                    p.image, p.stock, p.created_at' . $bestSellerSelect . ',
                    c.name AS category_name, c.image AS category_image
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? LIMIT 1'
        );
    } else {
        $bestSellerSelect = $hasBestSellerColumn ? ', is_best_seller' : '';
        $stmt = $conn->prepare(
            'SELECT id, name, description, price, old_price, category, image, stock, created_at' . $bestSellerSelect . '
             FROM products WHERE id = ? LIMIT 1'
        );
    }
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query error.']);
        exit;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $images = normalize_image_list($row['image']);

    $resolvedCategoryId = $hasProductCategoryId ? (int)($row['category_id'] ?? 0) : 0;
    $resolvedCategoryName = ($hasProductCategoryId && $hasCategoriesTable) ? (string)($row['category_name'] ?? '') : '';
    $resolvedCategoryImage = ($hasProductCategoryId && $hasCategoriesTable) ? (string)($row['category_image'] ?? '') : '';

    echo json_encode([
        'success' => true,
        'product' => [
            'id'          => (int)$row['id'],
            'name'        => $row['name'],
            'description' => $row['description'],
            'price'       => (float)$row['price'],
            'old_price'   => $row['old_price'] !== null ? (float)$row['old_price'] : null,
            'category'    => $row['category'],
            'category_id' => $resolvedCategoryId,
            'category_name' => $resolvedCategoryName,
            'category_image' => $resolvedCategoryImage,
            'image'       => $images[0] ?? '',
            'images'      => $images,
            'stock'       => (int)$row['stock'],
            'is_best_seller' => $hasBestSellerColumn ? ((int)($row['is_best_seller'] ?? 0) === 1) : false,
            'created_at'  => $row['created_at'],
        ],
    ]);
    exit;
}

/* ── Build query ── */
$where  = [];
$types  = '';
$params = [];

if ($hasProductCategoryId && $categoryId > 0) {
    $where[]  = 'p.category_id = ?';
    $types   .= 'i';
    $params[] = $categoryId;
} elseif ($category !== '') {
    $where[]  = 'p.category = ?';
    $types   .= 's';
    $params[] = $category;
}

if ($search !== '') {
    $like     = '%' . $search . '%';
    $where[]  = '(name LIKE ? OR description LIKE ?)';
    $types   .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT p.id, p.name, p.description, p.price, p.old_price, p.category, p.image, p.stock, p.created_at';
if ($hasProductCategoryId) {
    $sql .= ', p.category_id';
}
if ($hasBestSellerColumn) {
    $sql .= ', p.is_best_seller';
}
if ($hasProductCategoryId && $hasCategoriesTable) {
    $sql .= ', c.name AS category_name, c.image AS category_image';
}
$sql .= ' FROM products p';
if ($hasProductCategoryId && $hasCategoriesTable) {
    $sql .= ' LEFT JOIN categories c ON c.id = p.category_id';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC LIMIT ?';
$types   .= 'i';
$params[] = $limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query error.']);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result   = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    $images = normalize_image_list($row['image']);
    $resolvedCategoryId = $hasProductCategoryId ? (int)($row['category_id'] ?? 0) : 0;
    $resolvedCategoryName = ($hasProductCategoryId && $hasCategoriesTable) ? (string)($row['category_name'] ?? '') : '';
    $resolvedCategoryImage = ($hasProductCategoryId && $hasCategoriesTable) ? (string)($row['category_image'] ?? '') : '';
    $products[] = [
        'id'          => (int)$row['id'],
        'name'        => $row['name'],
        'description' => $row['description'],
        'price'       => (float)$row['price'],
        'old_price'   => $row['old_price'] ? (float)$row['old_price'] : null,
        'category'    => $row['category'],
        'category_id' => $resolvedCategoryId,
        'category_name' => $resolvedCategoryName,
        'category_image' => $resolvedCategoryImage,
        'image'       => $images[0] ?? '',
        'images'      => $images,
        'stock'       => (int)$row['stock'],
        'is_best_seller' => $hasBestSellerColumn ? ((int)($row['is_best_seller'] ?? 0) === 1) : false,
        'created_at'  => $row['created_at'],
    ];
}
$stmt->close();

echo json_encode([
    'success'  => true,
    'count'    => count($products),
    'products' => $products,
]);
