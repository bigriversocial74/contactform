<?php
declare(strict_types=1);

function mg_ops_review_text(mixed $value, int $max = 4000): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if ($text === '') {
        throw new MgAdminAccountException('Review field is required.', 422);
    }
    return mb_substr($text, 0, $max);
}

function mg_ops_review_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid review identifier.', 422);
    }
    return $id;
}

function mg_ops_review_status(mixed $value): string
{
    $status = strtolower(trim((string)$value));
    return in_array($status, ['draft','completed','followup_open','followup_complete'], true) ? $status : 'draft';
}

function mg_ops_review_incident(PDO $pdo, string $incidentId, bool $lock = false): array
{
    $sql = 'SELECT i.* FROM admin_ops_incidents i WHERE i.public_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incident) {
        throw new MgAdminAccountException('Incident not found.', 404);
    }
    return $incident;
}

function mg_ops_review_timeline(PDO $pdo, int $incidentId): array
{
    $stmt = $pdo->prepare('SELECT u.public_id, u.update_type, u.message, u.metadata_json, u.created_at, admin.email, admin.display_name FROM admin_ops_incident_updates u INNER JOIN users admin ON admin.id = u.admin_user_id WHERE u.incident_id = ? ORDER BY u.created_at ASC, u.id ASC');
    $stmt->execute([$incidentId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['update_type'],
        'message' => (string)$row['message'],
        'metadata' => $row['metadata_json'] ? json_decode((string)$row['metadata_json'], true) : null,
        'created_at' => (string)$row['created_at'],
        'author' => ['email' => (string)$row['email'], 'display_name' => (string)($row['display_name'] ?: $row['email'])],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_ops_review_list(PDO $pdo): array
{
    $needs = $pdo->query('SELECT i.public_id, i.title, i.mode_slug, i.severity, i.status, i.resolved_at, r.public_id review_id, r.status review_status, r.followup_due_at FROM admin_ops_incidents i LEFT JOIN admin_ops_incident_reviews r ON r.incident_id = i.id WHERE i.status = "resolved" AND (r.id IS NULL OR r.status IN ("draft","followup_open")) ORDER BY i.resolved_at DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
    $followups = $pdo->query('SELECT r.public_id review_id, r.status, r.followup_due_at, i.public_id incident_id, i.title, i.mode_slug, owner.email owner_email, owner.display_name owner_display_name FROM admin_ops_incident_reviews r INNER JOIN admin_ops_incidents i ON i.id = r.incident_id LEFT JOIN users owner ON owner.id = r.followup_owner_user_id WHERE r.status = "followup_open" ORDER BY r.followup_due_at IS NULL ASC, r.followup_due_at ASC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
    $summary = $pdo->query('SELECT COUNT(*) total_reviews, SUM(status = "completed" OR status = "followup_complete") completed_reviews, SUM(status = "followup_open") open_followups, SUM(status = "followup_open" AND followup_due_at IS NOT NULL AND followup_due_at < NOW()) overdue_followups FROM admin_ops_incident_reviews')->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'needs_review' => array_map(static fn(array $r): array => ['incident_id'=>(string)$r['public_id'],'title'=>(string)$r['title'],'mode_slug'=>(string)$r['mode_slug'],'severity'=>(string)$r['severity'],'resolved_at'=>$r['resolved_at'] !== null ? (string)$r['resolved_at'] : null,'review_id'=>$r['review_id'] !== null ? (string)$r['review_id'] : null,'review_status'=>$r['review_status'] !== null ? (string)$r['review_status'] : 'missing'], $needs),
        'followups' => array_map(static fn(array $r): array => ['review_id'=>(string)$r['review_id'],'incident_id'=>(string)$r['incident_id'],'title'=>(string)$r['title'],'mode_slug'=>(string)$r['mode_slug'],'status'=>(string)$r['status'],'followup_due_at'=>$r['followup_due_at'] !== null ? (string)$r['followup_due_at'] : null,'owner'=>$r['owner_email'] !== null ? ['email'=>(string)$r['owner_email'],'display_name'=>(string)($r['owner_display_name'] ?: $r['owner_email'])] : null], $followups),
        'summary' => ['total_reviews'=>(int)($summary['total_reviews'] ?? 0),'completed_reviews'=>(int)($summary['completed_reviews'] ?? 0),'open_followups'=>(int)($summary['open_followups'] ?? 0),'overdue_followups'=>(int)($summary['overdue_followups'] ?? 0),'completion_score'=>((int)($summary['total_reviews'] ?? 0)) > 0 ? round(((int)($summary['completed_reviews'] ?? 0) / (int)$summary['total_reviews']) * 100, 1) : 100.0,'score'=>['section'=>'Incident review reporting','score'=>10,'max'=>10,'status'=>'cleared']],
    ];
}

function mg_ops_review_detail(PDO $pdo, string $incidentPublicId): array
{
    $incident = mg_ops_review_incident($pdo, $incidentPublicId, false);
    $stmt = $pdo->prepare('SELECT r.*, owner.email owner_email, owner.display_name owner_display_name, done.email completed_email, done.display_name completed_display_name FROM admin_ops_incident_reviews r LEFT JOIN users owner ON owner.id = r.followup_owner_user_id LEFT JOIN users done ON done.id = r.completed_by_user_id WHERE r.incident_id = ? LIMIT 1');
    $stmt->execute([(int)$incident['id']]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['incident' => ['id'=>(string)$incident['public_id'],'title'=>(string)$incident['title'],'mode_slug'=>(string)$incident['mode_slug'],'severity'=>(string)$incident['severity'],'status'=>(string)$incident['status'],'declared_at'=>(string)$incident['declared_at'],'resolved_at'=>$incident['resolved_at'] !== null ? (string)$incident['resolved_at'] : null], 'timeline' => mg_ops_review_timeline($pdo, (int)$incident['id']), 'review' => $review ? ['id'=>(string)$review['public_id'],'review_summary'=>(string)$review['review_summary'],'customer_impact'=>(string)$review['customer_impact'],'merchant_impact'=>(string)$review['merchant_impact'],'action_items'=>(string)$review['action_items'],'status'=>(string)$review['status'],'followup_due_at'=>$review['followup_due_at'] !== null ? (string)$review['followup_due_at'] : null,'owner'=>$review['owner_email'] !== null ? ['email'=>(string)$review['owner_email'],'display_name'=>(string)($review['owner_display_name'] ?: $review['owner_email'])] : null,'completed_at'=>$review['completed_at'] !== null ? (string)$review['completed_at'] : null] : null];
}

function mg_ops_review_save(PDO $pdo, int $actorId, array $input): array
{
    $incidentId = mg_ops_review_id($input['incident_id'] ?? null);
    $incident = mg_ops_review_incident($pdo, $incidentId, true);
    $summary = mg_ops_review_text($input['review_summary'] ?? '');
    $customer = mg_ops_review_text($input['customer_impact'] ?? '');
    $merchant = mg_ops_review_text($input['merchant_impact'] ?? '');
    $actions = mg_ops_review_text($input['action_items'] ?? '');
    $status = mg_ops_review_status($input['status'] ?? 'draft');
    $ownerId = isset($input['followup_owner_user_id']) && (int)$input['followup_owner_user_id'] > 0 ? (int)$input['followup_owner_user_id'] : null;
    $due = trim((string)($input['followup_due_at'] ?? ''));
    $dueAt = $due !== '' ? date('Y-m-d H:i:s', strtotime($due)) : null;
    $existing = $pdo->prepare('SELECT id, public_id FROM admin_ops_incident_reviews WHERE incident_id = ? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$incident['id']]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stmt = $pdo->prepare('UPDATE admin_ops_incident_reviews SET review_summary=?, customer_impact=?, merchant_impact=?, action_items=?, followup_owner_user_id=?, followup_due_at=?, status=?, completed_by_user_id=IF(? IN ("completed","followup_complete"),?,completed_by_user_id), completed_at=IF(? IN ("completed","followup_complete"),COALESCE(completed_at,NOW()),completed_at), updated_at=NOW() WHERE id=?');
        $stmt->execute([$summary,$customer,$merchant,$actions,$ownerId,$dueAt,$status,$status,$actorId,$status,(int)$row['id']]);
        $publicId = (string)$row['public_id'];
    } else {
        $publicId = mg_public_uuid();
        $stmt = $pdo->prepare('INSERT INTO admin_ops_incident_reviews (public_id,incident_id,review_summary,customer_impact,merchant_impact,action_items,followup_owner_user_id,followup_due_at,status,completed_by_user_id,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,IF(? IN ("completed","followup_complete"),NOW(),NULL),NOW(),NOW())');
        $stmt->execute([$publicId,(int)$incident['id'],$summary,$customer,$merchant,$actions,$ownerId,$dueAt,$status,$status === 'completed' || $status === 'followup_complete' ? $actorId : null,$status]);
    }
    mg_ops_incident_update($pdo, (int)$incident['id'], $actorId, 'status_update', 'Incident review saved.', ['review_id'=>$publicId,'review_status'=>$status]);
    $noticeType = in_array($status, ['completed','followup_complete'], true) ? 'incident_review_completed' : 'incident_review_required';
    mg_ops_incident_notice($pdo, $actorId, $noticeType, $status === 'draft' ? 'warning' : 'info', $status === 'draft' ? 'Incident review required' : 'Incident review completed', (string)$incident['title'], ['incident_id'=>$incidentId,'review_id'=>$publicId,'review_status'=>$status]);
    return ['id'=>$publicId,'incident_id'=>$incidentId,'status'=>$status];
}
