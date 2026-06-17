<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('sales.roster.view');

if ($method === 'GET') {
    $stmt = mg_db()->prepare(
        'SELECT sr.*, u.email, COALESCE(u.display_name, u.full_name, u.email) AS name,
                CASE
                  WHEN sp.status = "online" AND sp.last_seen_at >= (NOW() - INTERVAL 2 MINUTE) THEN "online"
                  WHEN sp.status = "away" AND sp.last_seen_at >= (NOW() - INTERVAL 10 MINUTE) THEN "away"
                  ELSE "offline"
                END AS presence_status,
                sp.last_seen_at,
                (
                  SELECT COUNT(*)
                  FROM employee_chat_messages ecm
                  WHERE ecm.sender_user_id = sr.user_id
                    AND ecm.recipient_user_id = ?
                    AND ecm.read_at IS NULL
                ) AS unread_count
         FROM sales_roster sr
         INNER JOIN users u ON u.id = sr.user_id
         LEFT JOIN sales_presence sp ON sp.user_id = sr.user_id
         ORDER BY FIELD(sr.status, "active", "paused", "inactive", "suspended"),
                  FIELD(presence_status, "online", "away", "offline"),
                  sr.open_lead_count ASC,
                  sr.last_assigned_at ASC,
                  sr.id ASC'
    );
    $stmt->execute([(int) $user['id']]);
    mg_ok(['roster' => $stmt->fetchAll() ?: [], 'current_user_id' => (int) $user['id']], 'Sales roster.');
}

if ($method === 'POST') {
    $manager = mg_require_permission('sales.roster.manage');
    $input = mg_input();
    mg_require_csrf_for_write($input);

    $salesUserId = (int) ($input['user_id'] ?? 0);
    if ($salesUserId <= 0) {
        mg_fail('User is required.', 422, ['user_id' => 'User is required.']);
    }

    $status = trim((string) ($input['status'] ?? 'active'));
    if (!in_array($status, ['active', 'inactive', 'paused', 'suspended'], true)) {
        mg_fail('Invalid roster status.', 422, ['status' => 'Invalid status.']);
    }

    $territory = substr(trim((string) ($input['territory'] ?? '')), 0, 180) ?: null;
    $regionCode = substr(trim((string) ($input['region_code'] ?? '')), 0, 80) ?: null;
    $leadWeight = max(1, (int) ($input['lead_weight'] ?? 100));
    $maxOpenLeads = max(1, (int) ($input['max_open_leads'] ?? 50));

    $existing = mg_db()->prepare('SELECT id FROM sales_roster WHERE user_id = ? LIMIT 1');
    $existing->execute([$salesUserId]);
    $row = $existing->fetch();

    if ($row) {
        $stmt = mg_db()->prepare('UPDATE sales_roster SET status = ?, territory = ?, region_code = ?, lead_weight = ?, max_open_leads = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$status, $territory, $regionCode, $leadWeight, $maxOpenLeads, $salesUserId]);
    } else {
        $stmt = mg_db()->prepare(
            'INSERT INTO sales_roster (public_id, user_id, status, territory, region_code, lead_weight, max_open_leads, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([mg_crm_public_id('sr'), $salesUserId, $status, $territory, $regionCode, $leadWeight, $maxOpenLeads]);
    }

    mg_audit('sales.roster.updated', 'sales_roster', ['sales_user_id' => $salesUserId], (int) $manager['id']);
    mg_ok(['saved' => true], 'Sales roster saved.');
}

mg_fail('Method not allowed.', 405);
