<?php
/**
 * CLI security-log report for pre-public-traffic monitoring.
 *
 * Usage:
 *   php scripts/security_log_report.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/db.php';

$pdo = mg_db();

$summary = $pdo->query(
    "SELECT severity, event_type, COUNT(*) AS total
     FROM security_logs
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY severity, event_type
     ORDER BY total DESC, severity ASC, event_type ASC
     LIMIT 50"
)->fetchAll();

$recent = $pdo->query(
    "SELECT id, request_id, user_id, severity, event_type, message, ip_address, created_at
     FROM security_logs
     ORDER BY id DESC
     LIMIT 25"
)->fetchAll();

echo "Microgifter Security Log Report\n";
echo "Generated: " . date(DATE_ATOM) . "\n\n";

echo "24-hour summary\n";
echo "---------------\n";
foreach ($summary as $row) {
    echo sprintf("%-8s %-40s %5d\n", (string) $row['severity'], (string) $row['event_type'], (int) $row['total']);
}

echo "\nRecent events\n";
echo "-------------\n";
foreach ($recent as $row) {
    echo sprintf(
        "#%-6d %-8s %-34s %-15s %s\n",
        (int) $row['id'],
        (string) $row['severity'],
        (string) $row['event_type'],
        (string) $row['ip_address'],
        (string) $row['created_at']
    );
}
