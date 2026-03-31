<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/admin_auth.php';

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);

if (!is_array($data)) {
    $data = $_POST;
}

$username = trim((string)($data['username'] ?? ''));
$password = trim((string)($data['password'] ?? ''));

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

if (!admin_login($username, $password)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    exit;
}

echo json_encode([
    'success' => true,
    'authenticated' => true,
    'username' => $_SESSION['admin_username'] ?? 'admin',
]);
