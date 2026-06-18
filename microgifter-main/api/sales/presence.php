<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/crm.php';

$user = mg_crm_require_sales_access('sales.leads.view_own');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $status = strtolower(trim((string) ($input['status'] ?? 'online')));
    if (!in_array($status, ['online', 'away', 'offline'], true)) {
        $status = 'online';
    }

    $pdo = mg_db();
    $existing = $pdo->prepare('SELECT user_id FROM sales_presence WHERE user_id = ? LIMIT 1');
    $existing->execute([(int) $user['id']]);
    if ($existing->fetch()) {
        $stmt = $pdo->prepare('UPDATE sales_presence SET status = ?, last_seen_at = NOW(), updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$status, (int) $user['id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO sales_presence (user_id, status, last_seen_at, updated_at) VALUES (?, ?, NOW(), NOW())');
        $stmt->execute([(int) $user['id'], $status]);
    }

    mg_ok(['status' => $status], 'Presence updated.');
}

if ($method === 'GET') {
    $stmt = mg_db()->prepare('SELECT status, last_seen_at FROM sales_presence WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) $user['id']]);
    mg_ok(['presence' => $stmt->fetch() ?: ['status' => 'offline', 'last_seen_at' => null]], 'Presence.');
}

mg_fail('Method not allowed.', 405);
