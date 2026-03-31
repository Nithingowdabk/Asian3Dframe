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

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
$id   = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM products WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare error.']);
    exit;
}

$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete product.']);
    $stmt->close();
    exit;
}

if ($conn->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    $stmt->close();
    exit;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Product deleted successfully.',
]);
