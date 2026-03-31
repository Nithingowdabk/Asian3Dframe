<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$frame_type = isset($_GET['frame_type']) ? trim($_GET['frame_type']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';

// Allow category_id = 0 for global pricing
if ($category_id === null || !$frame_type || !$size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $query = $conn->prepare('SELECT price FROM category_frame_prices WHERE category_id = ? AND frame_type = ? AND size = ?');
    if (!$query) {
        throw new Exception($conn->error);
    }
    
    $query->bind_param('iss', $category_id, $frame_type, $size);
    if (!$query->execute()) {
        throw new Exception($query->error);
    }
    
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        echo json_encode([
            'success' => true,
            'price' => floatval($row['price'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Price not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
