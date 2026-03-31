<?php
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

$orderId = isset($body['order_id']) ? (int)$body['order_id'] : 0;
$status  = isset($body['status'])   ? trim($body['status'])  : '';

$validStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

$stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare error.']);
    exit;
}

$stmt->bind_param('si', $status, $orderId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update order.']);
    $stmt->close();
    exit;
}

if ($conn->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    $stmt->close();
    exit;
}

$stmt->close();

echo json_encode([
    'success'  => true,
    'message'  => 'Order status updated.',
    'order_id' => $orderId,
    'status'   => $status,
]);
