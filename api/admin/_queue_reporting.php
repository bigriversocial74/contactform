<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_schema.php';

function mg_queue_reporting_outcomes(): array
{
    return ['resolved_successfully','escalated_externally','merchant_action_required','customer_action_required','billing_adjustment','risk_restriction','catalog_correction','no_action_needed'];
}

function mg_queue_reporting_confidences(): array
{
    return ['high','medium','low','unknown'];
}

function mg_queue_reporting_choice(mixed $value, array $allowed, ?string $fallback = null): ?string
{
    $text = strtolower(trim((string)$value));
    if ($text === '' && $fallback === null) return null;
    return in_array($text, $allowed, true) ? $text : $fallback;
}

function mg_queue_reporting_note_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid queue note identifier.', 422);
    }
    return $id;
}

function mg_queue_reporting_required_columns(): array
{
    return [
        'public_id','status','priority','category','flag_state','created_at','updated_at','resolved_at','due_at',
        'routed_lane','sla_status','sla_due_at','playbook_slug','resolution_template_slug','resolution_outcome',
        'resolution_confidence','followup_required','reopened_after_resolution','notes_incomplete','resolution_reviewed_at',
    ];
}

function mg_queue_reporting_schema_payload(int $days, array $missing): array
{
    return [
        'summary' => [
            'total' => 0,
            'active_total' => 0,
            'resolved_total' => 0,
            'resolved_window_total' => 0,
            'avg_resolution_hours' => 0.0,
            'sla_breach_rate' => 0.0,
            'reopen_rate' => 0.0,
            'followup_required_total' => 0,
            'notes_incomplete_total' => 0,
            'confidence_high_total' => 0,
            'confidence_medium_total' => 0,
            'confidence_low_total' => 0,
            'aging_7_total' => 0,
            'aging_14_total' => 0,
            'aging_30_total' => 0,
            'schema_required' => $missing,
            'score' => ['section' => 'Resolution reporting', 'score' => 7, 'max' => 10, 'status' => 'schema_required'],
        ],
        'outcomes' => [],
        'playbooks' => [],
        'aging' => ['age_0_3' => 0, 'age_4_7' => 0, 'age_8_14' => 0, 'age_15_30' => 0, 'age_30_plus' => 0],
        'export' => [],
        'schema_required' => $missing,
        'filters' => ['outcomes' => mg_queue_reporting_outcomes(), 'confidences' => mg_queue_reporting_confidences(), 'window_days' => $days],
    ];
}

function mg_queue_reporting_read(PDO $pdo, int $days = 30): array
{
    $days = max(7, min(180, $days));
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) {
        return mg_queue_reporting_schema_payload($days, ['admin_user_notes']);
    }
    $missing = mg_admin_schema_missing_columns($pdo, 'admin_user_notes', mg_queue_reporting_required_columns());
    if ($missing) {
        return mg_queue_reporting_schema_payload($days, array_map(static fn(string $column): string => 'admin_user_notes.' . $column, $missing));
    }

    $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
    $summary = $pdo->prepare('SELECT
        COUNT(*) total,
        SUM(status <> "resolved") active_total,
        SUM(status = "resolved") resolved_total,
        SUM(status = "resolved" AND resolved_at >= ?) resolved_window_total,
        AVG(CASE WHEN status = "resolved" AND resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) avg_resolution_hours,
        SUM(sla_status = "breached") sla_breached_total,
        SUM(reopened_after_resolution = 1) reopened_total,
        SUM(followup_required = 1 AND status <> "resolved") followup_required_total,
        SUM(notes_incomplete = 1) notes_incomplete_total,
        SUM(resolution_confidence = "high") confidence_high_total,
        SUM(resolution_confidence = "medium") confidence_medium_total,
        SUM(resolution_confidence = "low") confidence_low_total,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) aging_7_total,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)) aging_14_total,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) aging_30_total
      FROM admin_user_notes');
    $summary->execute([$cutoff]);
    $row = $summary->fetch(PDO::FETCH_ASSOC) ?: [];

    $outcomes = $pdo->query('SELECT COALESCE(resolution_outcome,"unclassified") outcome, COUNT(*) total FROM admin_user_notes GROUP BY COALESCE(resolution_outcome,"unclassified") ORDER BY total DESC')->fetchAll(PDO::FETCH_ASSOC);
    $playbooks = $pdo->query('SELECT COALESCE(playbook_slug,"none") playbook, COUNT(*) total, SUM(status="resolved") resolved_total FROM admin_user_notes GROUP BY COALESCE(playbook_slug,"none") ORDER BY total DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
    $aging = $pdo->query('SELECT
        SUM(status <> "resolved" AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)) age_0_3,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) age_4_7,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)) age_8_14,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) age_15_30,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) age_30_plus
      FROM admin_user_notes')->fetch(PDO::FETCH_ASSOC) ?: [];

    $export = $pdo->prepare('SELECT public_id id, status, priority, category, flag_state, routed_lane, sla_status, playbook_slug, resolution_template_slug, resolution_outcome, resolution_confidence, followup_required, reopened_after_resolution, notes_incomplete, created_at, due_at, sla_due_at, resolved_at, updated_at FROM admin_user_notes WHERE updated_at >= ? OR status <> "resolved" ORDER BY updated_at DESC LIMIT 500');
    $export->execute([$cutoff]);

    $total = (int)($row['total'] ?? 0);
    $breached = (int)($row['sla_breached_total'] ?? 0);
    $reopened = (int)($row['reopened_total'] ?? 0);
    return [
        'summary' => [
            'total' => $total,
            'active_total' => (int)($row['active_total'] ?? 0),
            'resolved_total' => (int)($row['resolved_total'] ?? 0),
            'resolved_window_total' => (int)($row['resolved_window_total'] ?? 0),
            'avg_resolution_hours' => round((float)($row['avg_resolution_hours'] ?? 0), 1),
            'sla_breach_rate' => $total > 0 ? round(($breached / $total) * 100, 1) : 0.0,
            'reopen_rate' => $total > 0 ? round(($reopened / $total) * 100, 1) : 0.0,
            'followup_required_total' => (int)($row['followup_required_total'] ?? 0),
            'notes_incomplete_total' => (int)($row['notes_incomplete_total'] ?? 0),
            'confidence_high_total' => (int)($row['confidence_high_total'] ?? 0),
            'confidence_medium_total' => (int)($row['confidence_medium_total'] ?? 0),
            'confidence_low_total' => (int)($row['confidence_low_total'] ?? 0),
            'aging_7_total' => (int)($row['aging_7_total'] ?? 0),
            'aging_14_total' => (int)($row['aging_14_total'] ?? 0),
            'aging_30_total' => (int)($row['aging_30_total'] ?? 0),
            'schema_required' => [],
            'score' => ['section' => 'Resolution reporting', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        ],
        'outcomes' => array_map(static fn(array $r): array => ['outcome' => (string)$r['outcome'], 'total' => (int)$r['total']], $outcomes),
        'playbooks' => array_map(static fn(array $r): array => ['playbook' => (string)$r['playbook'], 'total' => (int)$r['total'], 'resolved_total' => (int)$r['resolved_total']], $playbooks),
        'aging' => array_map('intval', $aging),
        'export' => array_map(static fn(array $r): array => [
            'id' => (string)$r['id'],
            'status' => (string)$r['status'],
            'priority' => (string)$r['priority'],
            'category' => (string)$r['category'],
            'flag_state' => (string)$r['flag_state'],
            'routed_lane' => (string)($r['routed_lane'] ?? 'general'),
            'sla_status' => (string)($r['sla_status'] ?? 'unknown'),
            'playbook_slug' => $r['playbook_slug'] !== null ? (string)$r['playbook_slug'] : null,
            'resolution_template_slug' => $r['resolution_template_slug'] !== null ? (string)$r['resolution_template_slug'] : null,
            'resolution_outcome' => $r['resolution_outcome'] !== null ? (string)$r['resolution_outcome'] : null,
            'resolution_confidence' => (string)($r['resolution_confidence'] ?? 'unknown'),
            'followup_required' => (bool)$r['followup_required'],
            'reopened_after_resolution' => (bool)$r['reopened_after_resolution'],
            'notes_incomplete' => (bool)$r['notes_incomplete'],
            'created_at' => (string)$r['created_at'],
            'due_at' => $r['due_at'] !== null ? (string)$r['due_at'] : null,
            'sla_due_at' => $r['sla_due_at'] !== null ? (string)$r['sla_due_at'] : null,
            'resolved_at' => $r['resolved_at'] !== null ? (string)$r['resolved_at'] : null,
            'updated_at' => (string)$r['updated_at'],
        ], $export->fetchAll(PDO::FETCH_ASSOC)),
        'schema_required' => [],
        'filters' => ['outcomes' => mg_queue_reporting_outcomes(), 'confidences' => mg_queue_reporting_confidences(), 'window_days' => $days],
    ];
}

function mg_queue_reporting_update(PDO $pdo, string $notePublicId, array $input): array
{
    $missing = mg_admin_schema_missing_columns($pdo, 'admin_user_notes', ['public_id','resolution_outcome','resolution_confidence','followup_required','reopened_after_resolution','notes_incomplete','resolution_reviewed_at']);
    if ($missing) {
        throw new MgAdminAccountException('Queue reporting SQL migration required before updating resolution fields.', 503);
    }
    $stmt = $pdo->prepare('SELECT * FROM admin_user_notes WHERE public_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$notePublicId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        throw new MgAdminAccountException('Queue note not found.', 404);
    }
    $outcome = mg_queue_reporting_choice($input['resolution_outcome'] ?? null, mg_queue_reporting_outcomes());
    $confidence = mg_queue_reporting_choice($input['resolution_confidence'] ?? null, mg_queue_reporting_confidences(), 'unknown');
    $followup = !empty($input['followup_required']) ? 1 : 0;
    $reopened = !empty($input['reopened_after_resolution']) ? 1 : 0;
    $incomplete = !empty($input['notes_incomplete']) ? 1 : 0;
    $update = $pdo->prepare('UPDATE admin_user_notes SET resolution_outcome = ?, resolution_confidence = ?, followup_required = ?, reopened_after_resolution = ?, notes_incomplete = ?, resolution_reviewed_at = NOW(), updated_at = NOW() WHERE id = ?');
    $update->execute([$outcome, $confidence, $followup, $reopened, $incomplete, (int)$note['id']]);
    return ['id' => $notePublicId, 'resolution_outcome' => $outcome, 'resolution_confidence' => $confidence, 'followup_required' => (bool)$followup, 'reopened_after_resolution' => (bool)$reopened, 'notes_incomplete' => (bool)$incomplete];
}
