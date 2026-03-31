<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/admin_auth.php';
admin_require_auth_json();
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

    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    if ($days < 1) $days = 1;
    if ($days > 30) $days = 30;

    $today = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $dailyMap = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $dailyMap[$day] = 0;
    }

    $stmt = $conn->prepare(
        'SELECT visit_date, COUNT(*) AS visitors
         FROM visitor_logs
         WHERE visit_date >= ?
         GROUP BY visit_date
         ORDER BY visit_date ASC'
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to load visitors.');
    }

    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $day = (string)$row['visit_date'];
        if (isset($dailyMap[$day])) {
            $dailyMap[$day] = (int)$row['visitors'];
        }
    }
    $stmt->close();

    $daily = [];
    foreach ($dailyMap as $day => $count) {
        $daily[] = [
            'date' => $day,
            'label' => date('d M', strtotime($day)),
            'count' => $count,
        ];
    }

    $todayVisitors = (int)($dailyMap[$today] ?? 0);

    echo json_encode([
        'success' => true,
        'days' => $days,
        'today' => $today,
        'today_visitors' => $todayVisitors,
        'daily' => $daily,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
