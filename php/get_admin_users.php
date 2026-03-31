<?php
// get_admin_users.php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/db.php';
admin_require_auth_json();

header('Content-Type: application/json');

$result = $conn->query('SELECT id, username, created_at FROM admin_users ORDER BY id');
$admins = [];
while ($row = $result->fetch_assoc()) {
    $admins[] = [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'created_at' => $row['created_at'],
    ];
}
echo json_encode(['success' => true, 'admins' => $admins]);
