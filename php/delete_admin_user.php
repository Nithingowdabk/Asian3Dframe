<?php
// delete_admin_user.php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/db.php';
admin_require_auth_json();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID is required.']);
    exit;
}

// Prevent deleting the last admin
$result = $conn->query('SELECT COUNT(*) AS cnt FROM admin_users');
$row = $result->fetch_assoc();
if ($row && (int)$row['cnt'] <= 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Cannot delete the last admin user.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM admin_users WHERE id = ?');
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete admin user.']);
}
$stmt->close();
