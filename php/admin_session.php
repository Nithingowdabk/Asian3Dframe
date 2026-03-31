<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/admin_auth.php';

echo json_encode([
    'success' => true,
    'authenticated' => admin_is_authenticated(),
    'username' => admin_is_authenticated() ? (string)($_SESSION['admin_username'] ?? 'admin') : '',
]);
