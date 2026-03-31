<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
require_once __DIR__ . '/db.php';

function extract_address_meta(string $notesRaw): array {
    $meta = [
        'shipping_address' => '',
        'district' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
    ];

    if (preg_match('/^__ADDR_META__(\{.*?\})\n/s', $notesRaw, $m) === 1) {
        $decoded = json_decode($m[1], true);
        if (is_array($decoded)) {
            foreach ($meta as $k => $v) {
                if (isset($decoded[$k])) {
                    $meta[$k] = trim((string)$decoded[$k]);
                }
            }
        }
        $cleanNotes = preg_replace('/^__ADDR_META__\{.*?\}\n/s', '', $notesRaw, 1);
        return ['meta' => $meta, 'notes' => trim((string)$cleanNotes)];
    }

    return ['meta' => $meta, 'notes' => trim($notesRaw)];
}

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 500) : 200;

$validStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

/* ── Fetch orders ── */
if ($status !== '' && in_array($status, $validStatuses, true)) {
    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, phone, address, city,
                notes, payment_method, total, status, created_at
         FROM orders WHERE status = ? ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->bind_param('si', $status, $limit);
} else {
    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, phone, address, city,
                notes, payment_method, total, status, created_at
         FROM orders ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->bind_param('i', $limit);
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query error.']);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($order = $result->fetch_assoc()) {
    $orderId = (int)$order['id'];

    /* ── Fetch items for this order ── */
    $itemStmt = $conn->prepare(
        'SELECT oi.quantity, oi.price, oi.custom_message, oi.custom_photo, oi.photo,
                p.name AS product_name, p.image
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?'
    );
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();
    $items      = [];
    while ($item = $itemResult->fetch_assoc()) {
        $items[] = [
            'product_name'   => $item['product_name'],
            'image'          => $item['image'],
            'photo'          => $item['photo'],
            'quantity'       => (int)$item['quantity'],
            'price'          => (float)$item['price'],
            'custom_message' => $item['custom_message'],
            'custom_photo'   => $item['custom_photo'],
        ];
    }
    $itemStmt->close();

    $metaResult = extract_address_meta((string)($order['notes'] ?? ''));

    $orders[] = [
        'id'             => $orderId,
        'first_name'     => $order['first_name'],
        'last_name'      => $order['last_name'],
        'email'          => $order['email'],
        'phone'          => $order['phone'],
        'address'        => $order['address'],
        'city'           => $order['city'],
        'state'          => $metaResult['meta']['state'],
        'district'       => $metaResult['meta']['district'],
        'pincode'        => $metaResult['meta']['pincode'],
        'shipping_address' => $metaResult['meta']['shipping_address'],
        'notes'          => $metaResult['notes'],
        'payment_method' => $order['payment_method'],
        'total'          => (float)$order['total'],
        'status'         => $order['status'],
        'created_at'     => $order['created_at'],
        'items'          => $items,
    ];
}
$stmt->close();

/* ── Summary counts for dashboard ── */
$countStmt = $conn->query(
    "SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status"
);
$counts = ['pending' => 0, 'confirmed' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];
while ($row = $countStmt->fetch_assoc()) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
}

$revenueRow = $conn->query("SELECT SUM(total) AS rev FROM orders WHERE status != 'cancelled'")->fetch_assoc();
$revenue    = (float)($revenueRow['rev'] ?? 0);

echo json_encode([
    'success' => true,
    'count'   => count($orders),
    'orders'  => $orders,
    'summary' => [
        'total_orders' => array_sum($counts),
        'revenue'      => round($revenue, 2),
        'counts'       => $counts,
    ],
]);
