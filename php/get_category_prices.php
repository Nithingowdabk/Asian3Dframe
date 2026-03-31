<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

// Allow category_id = 0 for global pricing
if ($category_id === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit;
}

try {
    $query = $conn->prepare('SELECT frame_type, size, price FROM category_frame_prices WHERE category_id = ?');
    if (!$query) {
        throw new Exception($conn->error);
    }
    
    $query->bind_param('i', $category_id);
    if (!$query->execute()) {
        throw new Exception($query->error);
    }
    
    $result = $query->get_result();
    $prices = [];
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['frame_type'] . '_' . $row['size'];
        $prices[$key] = floatval($row['price']);
    }
    
    echo json_encode([
        'success' => true,
        'prices' => $prices
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
