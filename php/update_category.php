<?php
header('Content-Type: application/json');
require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
require_once __DIR__ . '/db.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$imagePath = '';

if ($id <= 0 || $name === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit;
}

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['image']['tmp_name']);
    if (!isset($allowed[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Invalid image type']);
        exit;
    }
    $filename = 'cat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $target = __DIR__ . '/../assets/images/categories/' . $filename;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'message' => 'Image upload failed']);
        exit;
    }
    $imagePath = 'assets/images/categories/' . $filename;
}

if ($imagePath !== '') {
    $stmt = $conn->prepare('UPDATE categories SET name = ?, image = ? WHERE id = ?');
    $stmt->bind_param('ssi', $name, $imagePath, $id);
} else {
    $stmt = $conn->prepare('UPDATE categories SET name = ? WHERE id = ?');
    $stmt->bind_param('si', $name, $id);
}
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Category updated']);
