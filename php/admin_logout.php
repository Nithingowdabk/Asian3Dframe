<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['POST', 'GET'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/admin_auth.php';
admin_logout();

echo json_encode(['success' => true, 'authenticated' => false]);
