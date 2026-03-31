<?php
header('Content-Type: application/json');
require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
require_once __DIR__ . '/db.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Category deleted']);
