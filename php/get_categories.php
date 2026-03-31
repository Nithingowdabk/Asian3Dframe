<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$result = $conn->query('SELECT id, name, image FROM categories ORDER BY name');
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'image' => $row['image']
    ];
}
echo json_encode(['success' => true, 'categories' => $categories]);
