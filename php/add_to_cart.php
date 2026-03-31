<?php
/**
 * add_to_cart.php
 * Adds a product to the PHP session cart and returns JSON.
 *
 * POST /php/add_to_cart.php
 * Body (JSON): { "product_id": 1, "quantity": 1, "frame_type": "mobile|normal", "frame_size": "A1|A2|A3|A4|2 FEET X 4 FEET|4 FEET X 4 FEET" }
 */

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/db.php';

/* ── Parse JSON input ─────────────────────────────────────────────── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$productId = isset($body['product_id']) ? (int) $body['product_id'] : 0;
$quantity  = isset($body['quantity'])   ? (int) $body['quantity']   : 1;
$frameType = isset($body['frame_type']) ? strtolower(trim((string) $body['frame_type'])) : 'normal';
$frameSize = isset($body['frame_size']) ? strtoupper(trim((string) $body['frame_size'])) : 'A4';

$validFrameTypes = ['mobile', 'normal'];
$validFrameSizes = [
    'A1', 'A2', 'A3', 'A4', '2 FEET X 4 FEET',
    '4 FEET X 4 FEET', '4 FEET X 6 FEET', '4 FEET X 8 FEET', 'CUSTOMISED SIZE'
];

if ($productId <= 0 || $quantity < 1 || $quantity > 99 || !in_array($frameType, $validFrameTypes, true) || !in_array($frameSize, $validFrameSizes, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

/* ── Verify product exists ─────────────────────────────────────────── */
$stmt = $conn->prepare('SELECT id, name, price, image, category FROM products WHERE id = ?');
$stmt->bind_param('i', $productId);
$stmt->execute();
$result  = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

/* ── Add / update session cart ─────────────────────────────────────── */
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$key = $productId . '::' . $frameType . '::' . $frameSize;

if (isset($_SESSION['cart'][$key])) {
    $newQty = $_SESSION['cart'][$key]['quantity'] + $quantity;
    $_SESSION['cart'][$key]['quantity'] = min($newQty, 99);
} else {
    $_SESSION['cart'][$key] = [
        'line_key'   =>         $key,
        'product_id' => (int)   $product['id'],
        'name'       =>         $product['name'],
        'price'      => (float) $product['price'],
        'image'      =>         $product['image'],
        'category'   =>         $product['category'],
        'frame_type' =>         $frameType,
        'frame_size' =>         $frameSize,
        'quantity'   =>         $quantity,
    ];
}

$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
$cartTotal = array_sum(array_map(
    fn($i) => $i['price'] * $i['quantity'],
    $_SESSION['cart']
));

echo json_encode([
    'success'    => true,
    'message'    => 'Added to cart.',
    'cart_count' => $cartCount,
    'cart_total' => round($cartTotal, 2),
    'product'    => [
        'id'       => (int)   $product['id'],
        'line_key' =>         $key,
        'name'     =>         $product['name'],
        'price'    => (float) $product['price'],
        'image'    =>         $product['image'],
        'category' =>         $product['category'],
        'frame_type' =>       $frameType,
        'frame_size' =>       $frameSize,
    ],
]);
