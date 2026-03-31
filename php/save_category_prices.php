<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$category_id = isset($data['category_id']) ? intval($data['category_id']) : null;
$prices = isset($data['prices']) ? $data['prices'] : [];

// Allow category_id = 0 for global pricing
if ($category_id === null || !is_array($prices) || empty($prices)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Delete existing prices for this category
    $deleteQuery = $conn->prepare('DELETE FROM category_frame_prices WHERE category_id = ?');
    if (!$deleteQuery) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $deleteQuery->bind_param('i', $category_id);
    if (!$deleteQuery->execute()) {
        throw new Exception('Delete failed: ' . $deleteQuery->error);
    }
    
    // Insert new prices
    $insertQuery = $conn->prepare('INSERT INTO category_frame_prices (category_id, frame_type, size, price) VALUES (?, ?, ?, ?)');
    if (!$insertQuery) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    foreach ($prices as $key => $price) {
        // Key format: "mobile_A1" or "normal_A1"
        $parts = explode('_', $key, 2);
        if (count($parts) !== 2) {
            continue;
        }
        
        $frame_type = $parts[0];
        $size = $parts[1];
        $price = floatval($price);
        
        $insertQuery->bind_param('sssd', $category_id, $frame_type, $size, $price);
        if (!$insertQuery->execute()) {
            throw new Exception('Insert failed: ' . $insertQuery->error);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Prices saved successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
