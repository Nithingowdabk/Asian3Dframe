<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
    exit;
}

if (empty($body['items']) || !is_array($body['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cart items are required.']);
    exit;
}

$subtotal = 0.0;
foreach ($body['items'] as $item) {
    $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;

    if ($productId <= 0 || $quantity < 1 || $quantity > 99) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid item data.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id, price FROM products WHERE id = ?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Product ID {$productId} not found."]);
        exit;
    }

    $dbUnitPrice = (float) $row['price'];
    $clientUnitPrice = isset($item['price']) ? (float) $item['price'] : 0.0;
    $unitPrice = $clientUnitPrice > 0 ? $clientUnitPrice : $dbUnitPrice;

    $subtotal += $unitPrice * $quantity;
}

$shippingThreshold = 3000.0;
$shippingFee = 60.0;
$shipping = ($subtotal >= $shippingThreshold) ? 0.0 : $shippingFee;
$total = round($subtotal + $shipping, 2);

$amountPaise = (int) round($total * 100);
if ($amountPaise < 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum payable amount is Rs. 1.00']);
    exit;
}

$readSecret = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === null || $value === '') {
        $value = $_ENV[$name] ?? ($_SERVER[$name] ?? '');
    }
    return trim((string) $value);
};

$keyId = $readSecret('rzp_live_SYVUaDMRcwcAWf');
$keySecret = $readSecret('bBpZVmKNwgE5gDnv84EJPnRH');
if ($keyId === '' || $keySecret === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Online payment is not configured on server. Please set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.'
    ]);
    exit;
}

try {
    $receipt = 'g4y_' . date('YmdHis') . '_' . random_int(1000, 9999);
} catch (Exception $e) {
    $receipt = 'g4y_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
}

$requestPayload = [
    'amount' => $amountPaise,
    'currency' => 'INR',
    'receipt' => $receipt,
    'payment_capture' => 1,
    'notes' => [
        'email' => substr((string) ($body['email'] ?? ''), 0, 100),
        'phone' => substr((string) ($body['phone'] ?? ''), 0, 30),
    ],
];

$apiUrl = 'https://api.razorpay.com/v1/orders';
$httpCode = 0;
$responseBody = '';

if (function_exists('curl_init')) {
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $responseBody = (string) curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                        'Authorization: Basic ' . base64_encode($keyId . ':' . $keySecret) . "\r\n",
            'content' => json_encode($requestPayload),
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $responseBody = (string) @file_get_contents($apiUrl, false, $context);
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int) $m[1];
    }
}

$responseData = json_decode($responseBody, true);

if ($httpCode < 200 || $httpCode >= 300 || !is_array($responseData) || empty($responseData['id'])) {
    http_response_code(502);
    $errorMessage = 'Unable to create payment order. Please try again.';
    if (is_array($responseData) && isset($responseData['error']['description'])) {
        $errorMessage = (string) $responseData['error']['description'];
    }

    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'amount' => $amountPaise,
    'currency' => 'INR',
    'razorpay_order_id' => (string) $responseData['id'],
    'razorpay_key_id' => $keyId,
]);
