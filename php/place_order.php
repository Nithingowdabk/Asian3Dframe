<?php
/**
 * place_order.php
 * Saves a full order (+ items + optional custom photo path) to MySQL.
 *
 * POST /php/place_order.php
 * Body (JSON):
 * {
 *   "first_name", "last_name", "email", "phone", "address", "city",
 *   "notes", "payment_method",
 *   "items": [{ "product_id", "quantity", "price", "custom_message", "custom_photo" }]
 * }
 *
 * Response: { "success": true, "order_id": 42 }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/db.php';

/* ── Parse input ─────────────────────────────────────────────────────── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

/* ── Validate required fields ─────────────────────────────────────────── */
$required = ['first_name','last_name','email','phone','address','city','payment_method','items'];
foreach ($required as $f) {
    if (empty($body[$f])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $f"]);
        exit;
    }
}

if (!is_array($body['items']) || count($body['items']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Order must have at least one item.']);
    exit;
}

/* ── Sanitise scalar fields ─────────────────────────────────────────── */
$firstName     = mb_substr(trim((string) $body['first_name']),     0, 100);
$lastName      = mb_substr(trim((string) $body['last_name']),      0, 100);
$email         = mb_substr(trim((string) $body['email']),          0, 200);
$phone         = mb_substr(trim((string) $body['phone']),          0, 30);
$address       = mb_substr(trim((string) $body['address']),        0, 500);
$city          = mb_substr(trim((string) $body['city']),           0, 100);
$state         = mb_substr(trim((string) ($body['state'] ?? '')),  0, 100);
$district      = mb_substr(trim((string) ($body['district'] ?? '')),0, 100);
$pincode       = mb_substr(trim((string) ($body['pincode'] ?? '')),0, 20);
$shippingAddr  = mb_substr(trim((string) ($body['shipping_address'] ?? $address)), 0, 500);
$userNotes     = trim((string) ($body['notes'] ?? ''));
$paymentMethod = mb_substr(trim((string) $body['payment_method']), 0, 50);

$addressMeta = [
    'shipping_address' => $shippingAddr,
    'district'         => $district,
    'city'             => $city,
    'state'            => $state,
    'pincode'          => $pincode,
];
$addressMetaJson = json_encode($addressMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($addressMetaJson)) {
    $addressMetaJson = '{}';
}

$notesPrefix = '__ADDR_META__' . $addressMetaJson . "\n";
$maxNotesLen = 1000;
$remainingForUserNotes = max(0, $maxNotesLen - strlen($notesPrefix));
$notes = $notesPrefix . mb_substr($userNotes, 0, $remainingForUserNotes);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$allowedPayments = ['cod', 'razorpay'];
if (!in_array($paymentMethod, $allowedPayments, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
    exit;
}

/* ── Validate items & fetch authoritative prices from DB ────────────── */
$cleanItems = [];
$subtotal   = 0.0;

$validFrameTypes = ['mobile', 'normal'];
$validFrameSizes = [
    'A1', 'A2', 'A3', 'A4', '2 FEET X 4 FEET',
    '4 FEET X 4 FEET', '4 FEET X 6 FEET', '4 FEET X 8 FEET', 'CUSTOMISED SIZE'
];

/**
 * Accept either:
 * 1) A safe relative uploads path (uploads/...)
 * 2) A data URL (data:image/...;base64,...) that gets persisted into uploads/order_photos/
 */
function persistCustomPhoto(string $rawValue): string
{
    $value = trim($rawValue);
    if ($value === '') {
        return '';
    }

    // Keep existing upload paths if they are safe.
    if (preg_match('/^uploads\/[A-Za-z0-9_\/.\-]+\.(jpg|jpeg|png|webp)$/i', $value)) {
        if (strpos($value, '..') === false && strpos($value, '\\') === false) {
            return $value;
        }
    }

    // Persist data URL images to disk.
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $value, $m)) {
        return '';
    }

    $extension = strtolower($m[1]);
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }

    $base64 = substr($value, strpos($value, ',') + 1);
    if ($base64 === '') {
        return '';
    }

    $binary = base64_decode($base64, true);
    if ($binary === false) {
        return '';
    }

    // Limit to 10MB decoded image.
    if (strlen($binary) > (10 * 1024 * 1024)) {
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/order_photos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return '';
    }

    try {
        $fileName = 'orderphoto_' . bin2hex(random_bytes(8)) . '.' . $extension;
    } catch (Exception $e) {
        $fileName = 'orderphoto_' . uniqid('', true) . '.' . $extension;
    }

    $absolutePath = $uploadDir . '/' . $fileName;
    if (file_put_contents($absolutePath, $binary) === false) {
        return '';
    }

    return 'uploads/order_photos/' . $fileName;
}

foreach ($body['items'] as $item) {
    $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    $quantity  = isset($item['quantity'])   ? (int) $item['quantity']   : 0;

    if ($productId <= 0 || $quantity < 1 || $quantity > 99) {
        echo json_encode(['success' => false, 'message' => 'Invalid item data.']);
        exit;
    }

    /* Re-fetch product to validate ID, but prefer client-calculated price when provided. */
    $stmt = $conn->prepare('SELECT id, price FROM products WHERE id = ?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => "Product ID $productId not found."]);
        exit;
    }

    $dbUnitPrice = (float) $row['price'];
    $clientUnitPrice = isset($item['price']) ? (float) $item['price'] : 0.0;
    $unitPrice = $clientUnitPrice > 0 ? $clientUnitPrice : $dbUnitPrice;
    $subtotal  += $unitPrice * $quantity;

    $customMessage = mb_substr(trim((string) ($item['custom_message'] ?? '')), 0, 200);

    $frameType = strtolower(trim((string) ($item['frame_type'] ?? 'normal')));
    if (!in_array($frameType, $validFrameTypes, true)) {
        $frameType = 'normal';
    }

    $frameSize = strtoupper(trim((string) ($item['frame_size'] ?? 'A4')));
    if (!in_array($frameSize, $validFrameSizes, true)) {
        $frameSize = 'A4';
    }

    $incomingPhoto = (string)($item['custom_photo'] ?? ($item['image'] ?? ''));
    $customPhoto   = persistCustomPhoto($incomingPhoto);

    $cleanItems[] = [
        'product_id'     => $productId,
        'quantity'       => $quantity,
        'price'          => $unitPrice,
        'custom_message' => $customMessage,
        'custom_photo'   => $customPhoto,
        'frame_type'     => $frameType,
        'frame_size'     => $frameSize,
    ];
}

/* Shipping: keep in sync with checkout UI */
$shippingThreshold = 3000.0;
$shippingFee = 60.0;
$shipping = ($subtotal >= $shippingThreshold) ? 0.0 : $shippingFee;
$total    = round($subtotal + $shipping, 2);

/* ── Insert order ────────────────────────────────────────────────────── */
$conn->begin_transaction();

try {
    $stmt = $conn->prepare('
        INSERT INTO orders
            (first_name, last_name, email, phone, address, city, notes,
             payment_method, total, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())
    ');
    $stmt->bind_param(
        'ssssssssd',
        $firstName, $lastName, $email, $phone, $address,
        $city, $notes, $paymentMethod, $total
    );
    $stmt->execute();
    $orderId = (int) $conn->insert_id;
    $stmt->close();

    $itemStmt = $conn->prepare('
        INSERT INTO order_items
            (order_id, product_id, quantity, price, custom_message, custom_photo, frame_type, frame_size)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($cleanItems as $i) {
        $itemStmt->bind_param(
            'iiidssss',
            $orderId,
            $i['product_id'],
            $i['quantity'],
            $i['price'],
            $i['custom_message'],
            $i['custom_photo'],
            $i['frame_type'],
            $i['frame_size']
        );
        $itemStmt->execute();
    }
    $itemStmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to place order. Please try again.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['cart']);

echo json_encode([
    'success'  => true,
    'message'  => 'Order placed successfully.',
    'order_id' => $orderId,
]);

