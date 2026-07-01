<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_schema.php';

function mg_queue_sla_policy(string $priority, string $category, string $flagState): array
{
    $hours = ['critical' => 4, 'high' => 12, 'normal' => 48, 'low' => 96][$priority] ?? 48;
    if (in_array($category, ['risk','billing'], true)) {
        $hours = min($hours, 8);
    }
    if (in_array($category, ['merchant_onboarding','product_catalog','crm_campaigns'], true)) {
        $hours = min($hours, 24);
    }
    if (in_array($flagState, ['flagged','review'], true)) {
        $hours = min($hours, 6);
    }
    return ['hours' => $hours, 'at_risk_hours' => max(1, (int)ceil($hours * 0.25))];
}

function mg_queue_sla_lane(string $category, string $flagState): string
{
    if (in_array($flagState, ['flagged','review'], true)) {
        return 'risk';
    }
    return in_array($category, ['support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general'], true) ? $category : 'general';
}

function mg_queue_sla_status(?string $slaDueAt, string $status): string
{
    if ($status === 'resolved') {
        return 'resolved';
    }
    if (in_array($status, ['waiting_on_merchant','waiting_on_customer'], true)) {
        return 'paused';
    }
    if (!$slaDueAt) {
        return 'compliant';
    }
    $due = strtotime($slaDueAt . ' UTC');
    if ($due === false) {
        return 'compliant';
    }
    $now = time();
    if ($due < $now) {
        return 'breached';
    }
    return ($due - $now) <= 7200 ? 'at_risk' : 'compliant';
}

function mg_queue_sla_required_columns(): array
{
    return ['id','public_id','target_user_id','assigned_admin_user_id','category','priority','status','flag_state','created_at','updated_at','routed_lane','sla_due_at','sla_status','sla_policy_json','last_routed_at','auto_escalated_at'];
}

function mg_queue_sla_schema_result(PDO $pdo): ?array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) {
        return ['schema_required' => ['admin_user_notes']];
    }
    $missing = mg_admin_schema_missing_columns($pdo, 'admin_user_notes', mg_queue_sla_required_columns());
    if (!$missing) {
        return null;
    }
    return ['schema_required' => array_map(static fn(string $column): string => 'admin_user_notes.' . $column, $missing)];
}

function mg_queue_sla_recalculate(PDO $pdo, int $actorId, int $limit = 250): array
{
    $schema = mg_queue_sla_schema_result($pdo);
    if ($schema !== null) {
        return ['processed' => 0, 'updated' => 0, 'breached' => 0, 'auto_escalated' => 0, 'auto_routed' => 0] + $schema;
    }

    $stmt = $pdo->prepare(
        'SELECT id, public_id, target_user_id, assigned_admin_user_id, category, priority, status, flag_state, created_at, updated_at, sla_due_at, sla_status, routed_lane
         FROM admin_user_notes
         WHERE status <> "resolved"
         ORDER BY updated_at DESC, id DESC
         LIMIT ' . max(1, min(500, $limit))
    );
    $stmt->execute();
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $breached = 0;
    $autoEscalated = 0;
    $autoRouted = 0;
    foreach ($notes as $note) {
        $policy = mg_queue_sla_policy((string)$note['priority'], (string)$note['category'], (string)$note['flag_state']);
        $lane = mg_queue_sla_lane((string)$note['category'], (string)$note['flag_state']);
        $base = strtotime((string)($note['created_at'] ?: $note['updated_at']) . ' UTC') ?: time();
        $dueAt = gmdate('Y-m-d H:i:s', $base + ((int)$policy['hours'] * 3600));
        $slaStatus = mg_queue_sla_status($dueAt, (string)$note['status']);
        $set = ['routed_lane = ?', 'sla_due_at = ?', 'sla_status = ?', 'sla_policy_json = ?', 'last_routed_at = CASE WHEN routed_lane <> ? THEN NOW() ELSE last_routed_at END'];
        $params = [$lane, $dueAt, $slaStatus, json_encode($policy + ['lane' => $lane], JSON_UNESCAPED_SLASHES), $lane];
        $shouldEscalate = $slaStatus === 'breached'
            || ((string)$note['priority'] === 'critical' && empty($note['assigned_admin_user_id']))
            || (in_array((string)$note['flag_state'], ['flagged','review'], true) && empty($note['assigned_admin_user_id']))
            || (in_array((string)$note['status'], ['waiting_on_merchant','waiting_on_customer'], true) && strtotime((string)$note['updated_at'] . ' UTC') < time() - 7 * 86400);
        if ($shouldEscalate && (string)$note['status'] !== 'escalated') {
            $set[] = 'status = "escalated"';
            $set[] = 'auto_escalated_at = COALESCE(auto_escalated_at,NOW())';
            $autoEscalated++;
            mg_queue_notice_create($pdo, [
                'note_id' => (int)$note['id'],
                'target_user_id' => (int)$note['target_user_id'],
                'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
                'actor_user_id' => $actorId,
                'notification_type' => 'auto_escalated',
                'severity' => 'critical',
                'title' => 'Queue item auto-escalated',
                'message' => 'A follow-up queue item was automatically escalated by SLA rules.',
                'metadata' => ['note_public_id' => (string)$note['public_id'], 'sla_status' => $slaStatus, 'lane' => $lane],
            ]);
        }
        if ($slaStatus === 'breached') {
            $breached++;
            mg_queue_notice_create($pdo, [
                'note_id' => (int)$note['id'],
                'target_user_id' => (int)$note['target_user_id'],
                'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
                'actor_user_id' => $actorId,
                'notification_type' => 'sla_breach',
                'severity' => 'critical',
                'title' => 'SLA breached',
                'message' => 'A follow-up queue item has breached its SLA window.',
                'metadata' => ['note_public_id' => (string)$note['public_id'], 'sla_due_at' => $dueAt, 'lane' => $lane],
            ]);
        }
        if ((string)$note['routed_lane'] !== $lane) {
            $autoRouted++;
            mg_queue_notice_create($pdo, [
                'note_id' => (int)$note['id'],
                'target_user_id' => (int)$note['target_user_id'],
                'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
                'actor_user_id' => $actorId,
                'notification_type' => 'auto_routed',
                'severity' => 'info',
                'title' => 'Queue item routed',
                'message' => 'A follow-up queue item was routed into the correct admin lane.',
                'metadata' => ['note_public_id' => (string)$note['public_id'], 'lane' => $lane],
            ]);
        }
        $sql = 'UPDATE admin_user_notes SET ' . implode(', ', $set) . ', updated_at = updated_at WHERE id = ?';
        $params[] = (int)$note['id'];
        $update = $pdo->prepare($sql);
        $update->execute($params);
        $updated += $update->rowCount();
    }
    return ['processed' => count($notes), 'updated' => $updated, 'breached' => $breached, 'auto_escalated' => $autoEscalated, 'auto_routed' => $autoRouted, 'schema_required' => []];
}

function mg_queue_sla_empty_health(array $schemaRequired): array
{
    return [
        'summary' => [
            'total' => 0,
            'active_total' => 0,
            'compliant_total' => 0,
            'at_risk_total' => 0,
            'breached_total' => 0,
            'unassigned_total' => 0,
            'stale_waiting_total' => 0,
            'auto_escalated_total' => 0,
            'schema_required' => $schemaRequired,
            'score' => ['section' => 'Queue SLA health', 'score' => 7, 'max' => 10, 'status' => 'schema_required'],
        ],
        'lanes' => [],
        'workload' => [],
        'schema_required' => $schemaRequired,
    ];
}

function mg_queue_sla_health(PDO $pdo): array
{
    $schema = mg_queue_sla_schema_result($pdo);
    if ($schema !== null) {
        return mg_queue_sla_empty_health($schema['schema_required']);
    }

    $summary = $pdo->query(
        'SELECT
            COUNT(*) total,
            SUM(status <> "resolved") active_total,
            SUM(sla_status = "compliant" AND status <> "resolved") compliant_total,
            SUM(sla_status = "at_risk" AND status <> "resolved") at_risk_total,
            SUM(sla_status = "breached" AND status <> "resolved") breached_total,
            SUM(assigned_admin_user_id IS NULL AND status <> "resolved") unassigned_total,
            SUM(status IN ("waiting_on_merchant","waiting_on_customer") AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) stale_waiting_total,
            SUM(auto_escalated_at IS NOT NULL AND status <> "resolved") auto_escalated_total
         FROM admin_user_notes'
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $lanes = $pdo->query('SELECT routed_lane lane, COUNT(*) total, SUM(status <> "resolved") active_total, SUM(sla_status = "breached") breached_total FROM admin_user_notes GROUP BY routed_lane ORDER BY active_total DESC')->fetchAll(PDO::FETCH_ASSOC);
    $workload = $pdo->query(
        'SELECT assigned.id admin_id, COALESCE(assigned.display_name,assigned.email,"Unassigned") admin_name,
                COUNT(n.id) active_total,
                SUM(n.priority = "critical") critical_total,
                SUM(n.sla_status = "breached") breached_total,
                MIN(n.created_at) oldest_created_at
         FROM admin_user_notes n
         LEFT JOIN users assigned ON assigned.id = n.assigned_admin_user_id
         WHERE n.status <> "resolved"
         GROUP BY assigned.id, assigned.display_name, assigned.email
         ORDER BY breached_total DESC, critical_total DESC, active_total DESC
         LIMIT 20'
    )->fetchAll(PDO::FETCH_ASSOC);
    return [
        'summary' => [
            'total' => (int)($summary['total'] ?? 0),
            'active_total' => (int)($summary['active_total'] ?? 0),
            'compliant_total' => (int)($summary['compliant_total'] ?? 0),
            'at_risk_total' => (int)($summary['at_risk_total'] ?? 0),
            'breached_total' => (int)($summary['breached_total'] ?? 0),
            'unassigned_total' => (int)($summary['unassigned_total'] ?? 0),
            'stale_waiting_total' => (int)($summary['stale_waiting_total'] ?? 0),
            'auto_escalated_total' => (int)($summary['auto_escalated_total'] ?? 0),
            'schema_required' => [],
            'score' => ['section' => 'Queue SLA health', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        ],
        'lanes' => array_map(static fn(array $row): array => ['lane' => (string)$row['lane'], 'total' => (int)$row['total'], 'active_total' => (int)$row['active_total'], 'breached_total' => (int)$row['breached_total']], $lanes),
        'workload' => array_map(static fn(array $row): array => ['admin_id' => $row['admin_id'] !== null ? (int)$row['admin_id'] : null, 'admin_name' => (string)$row['admin_name'], 'active_total' => (int)$row['active_total'], 'critical_total' => (int)$row['critical_total'], 'breached_total' => (int)$row['breached_total'], 'oldest_created_at' => $row['oldest_created_at'] !== null ? (string)$row['oldest_created_at'] : null], $workload),
        'schema_required' => [],
    ];
}
