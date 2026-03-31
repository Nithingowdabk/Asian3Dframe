<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';

function ensure_visitors_table(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS visitor_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visit_date DATE NOT NULL,
            visitor_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            page_path VARCHAR(255) NOT NULL DEFAULT '',
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_daily_visitor (visit_date, visitor_hash),
            KEY idx_visit_date (visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed to prepare visitor storage.');
    }
}

try {
    ensure_visitors_table($conn);

    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    $pagePath = '';
    if (is_array($payload) && isset($payload['page'])) {
        $pagePath = substr(trim((string)$payload['page']), 0, 255);
    }

    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    if ($ip === '') {
        $ip = '0.0.0.0';
    }

    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    if ($ua === '') {
        $ua = 'unknown';
    }
    $ua = substr($ua, 0, 255);

    $visitDate = date('Y-m-d');
    $visitorHash = hash('sha256', $ip . '|' . $ua);

    $stmt = $conn->prepare(
        'INSERT INTO visitor_logs (visit_date, visitor_hash, ip_address, user_agent, page_path)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE page_path = VALUES(page_path)'
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to save visitor data.');
    }

    $stmt->bind_param('sssss', $visitDate, $visitorHash, $ip, $ua, $pagePath);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        throw new RuntimeException('Failed to save visitor data.');
    }

    echo json_encode([
        'success' => true,
        'tracked' => $affected === 1,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
