<?php
// edit_admin_user.php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/db.php';
admin_require_auth_json();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = isset($data['id']) ? (int)$data['id'] : 0;
$username = trim((string)($data['username'] ?? ''));
$password = isset($data['password']) ? (string)$data['password'] : null;

if ($id <= 0 || $username === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID and username are required.']);
    exit;
}

// Check if username already exists for another admin
$stmt = $conn->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ? LIMIT 1');
$stmt->bind_param('si', $username, $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Username already exists.']);
    exit;
}
$stmt->close();

if ($password !== null && $password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE admin_users SET username = ?, password_hash = ? WHERE id = ?');
    $stmt->bind_param('ssi', $username, $hash, $id);
} else {
    $stmt = $conn->prepare('UPDATE admin_users SET username = ? WHERE id = ?');
    $stmt->bind_param('si', $username, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update admin user.']);
}
$stmt->close();
