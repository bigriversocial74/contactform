<?php
/**
 * Microgifter CRM helper layer.
 *
 * 02H creates a practical sales CRM module that is intentionally separate from
 * the core gifting/commerce stages. This module uses the 02G schema.
 */
declare(strict_types=1);

function mg_crm_public_id(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function mg_crm_hash(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : hash('sha256', strtolower($value));
}

function mg_crm_user_can_view_all(array $user): bool
{
    return mg_api_user_has_permission($user, 'sales.leads.view_all') || mg_api_user_has_permission($user, 'sales.roster.manage');
}

function mg_crm_require_sales_access(string $permission = 'sales.leads.view_own'): array
{
    $user = mg_require_api_user();
    if (!mg_api_user_has_permission($user, $permission) && !mg_api_user_has_permission($user, 'sales.leads.view_all')) {
        mg_fail('Sales access required.', 403);
    }
    return $user;
}

function mg_crm_normalize_lead_type(string $value): string
{
    $value = strtolower(trim($value));
    $allowed = ['merchant', 'workplace', 'creator', 'affiliate', 'partner', 'general'];
    return in_array($value, $allowed, true) ? $value : 'general';
}

function mg_crm_normalize_lead_status(string $value): string
{
    $value = strtolower(trim($value));
    $allowed = ['new', 'assigned', 'contacted', 'qualified', 'nurture', 'converted', 'closed_lost', 'spam'];
    return in_array($value, $allowed, true) ? $value : 'new';
}

function mg_crm_normalize_priority(string $value): string
{
    $value = strtolower(trim($value));
    $allowed = ['low', 'normal', 'high', 'urgent'];
    return in_array($value, $allowed, true) ? $value : 'normal';
}

function mg_crm_read_utm(array $input): array
{
    $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    $out = [];
    foreach ($keys as $key) {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value !== '') {
            $out[$key] = substr($value, 0, 160);
        }
    }
    return $out;
}

function mg_crm_record_event(int $leadId, string $eventType, ?string $fromStatus = null, ?string $toStatus = null, ?int $actorUserId = null, ?string $note = null, array $metadata = []): void
{
    $stmt = mg_db()->prepare(
        'INSERT INTO crm_lead_events (public_id, lead_id, event_type, from_status, to_status, actor_user_id, note, metadata_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        mg_crm_public_id('cle'),
        $leadId,
        $eventType,
        $fromStatus,
        $toStatus,
        $actorUserId,
        $note,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_crm_pick_sales_user(?string $regionCode = null): ?array
{
    $pdo = mg_db();
    $params = [];
    $sql = 'SELECT sr.*, u.email, u.display_name, u.full_name
            FROM sales_roster sr
            INNER JOIN users u ON u.id = sr.user_id
            WHERE sr.status = "active"
              AND sr.open_lead_count < sr.max_open_leads';

    if ($regionCode) {
        $sql .= ' ORDER BY CASE WHEN sr.region_code = ? THEN 0 ELSE 1 END, sr.open_lead_count ASC, sr.last_assigned_at ASC, sr.id ASC';
        $params[] = $regionCode;
    } else {
        $sql .= ' ORDER BY sr.open_lead_count ASC, sr.last_assigned_at ASC, sr.id ASC';
    }

    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mg_crm_assign_lead(int $leadId, int $assignedUserId, ?int $assignedByUserId = null, string $method = 'auto_least_open', ?string $reason = null): void
{
    $pdo = mg_db();
    $leadStmt = $pdo->prepare('SELECT status, assigned_user_id FROM crm_leads WHERE id = ? LIMIT 1');
    $leadStmt->execute([$leadId]);
    $lead = $leadStmt->fetch();
    if (!$lead) {
        throw new RuntimeException('Lead not found.');
    }

    $fromStatus = (string) $lead['status'];
    $toStatus = in_array($fromStatus, ['new', 'assigned'], true) ? 'assigned' : $fromStatus;
    $oldAssignedUserId = $lead['assigned_user_id'] ? (int) $lead['assigned_user_id'] : null;

    $update = $pdo->prepare('UPDATE crm_leads SET assigned_user_id = ?, assigned_at = NOW(), status = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([$assignedUserId, $toStatus, $leadId]);

    $assignment = $pdo->prepare(
        'INSERT INTO crm_lead_assignments (public_id, lead_id, assigned_to_user_id, assigned_by_user_id, assignment_method, reason, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $assignment->execute([
        mg_crm_public_id('cla'),
        $leadId,
        $assignedUserId,
        $assignedByUserId,
        in_array($method, ['auto_least_open', 'manual', 'system'], true) ? $method : 'manual',
        $reason,
    ]);

    if ($oldAssignedUserId && $oldAssignedUserId !== $assignedUserId) {
        $dec = $pdo->prepare('UPDATE sales_roster SET open_lead_count = GREATEST(open_lead_count - 1, 0), updated_at = NOW() WHERE user_id = ?');
        $dec->execute([$oldAssignedUserId]);
    }

    $inc = $pdo->prepare('UPDATE sales_roster SET open_lead_count = open_lead_count + 1, last_assigned_at = NOW(), updated_at = NOW() WHERE user_id = ?');
    $inc->execute([$assignedUserId]);

    mg_crm_record_event($leadId, 'crm_lead.assigned', $fromStatus, $toStatus, $assignedByUserId, $reason, [
        'assigned_to_user_id' => $assignedUserId,
        'method' => $method,
    ]);
}

function mg_crm_create_lead(array $input, ?int $actorUserId = null, bool $autoAssign = true): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));

    if ($name === '') {
        mg_fail('Name is required.', 422, ['name' => 'Name is required.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        mg_fail('Valid email is required.', 422, ['email' => 'Valid email is required.']);
    }

    $pdo = mg_db();
    $regionCode = trim((string) ($input['region_code'] ?? $input['zip_code'] ?? ''));
    $sourcePage = trim((string) ($input['source_page'] ?? 'learn-more')) ?: 'learn-more';
    $leadType = mg_crm_normalize_lead_type((string) ($input['lead_type'] ?? 'general'));
    $priority = mg_crm_normalize_priority((string) ($input['priority'] ?? 'normal'));
    $utm = mg_crm_read_utm($input);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO crm_leads
             (public_id, lead_type, source_page, source_url, source_utm_json, name, email, phone, business_name, website_url, zip_code, category, message, status, priority, region_country, region_state, region_city, region_postal, ip_hash, user_agent_hash, metadata_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            mg_crm_public_id('lead'),
            $leadType,
            substr($sourcePage, 0, 180),
            substr((string) ($input['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 600),
            json_encode($utm, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            substr($name, 0, 180),
            substr($email, 0, 255),
            substr(trim((string) ($input['phone'] ?? '')), 0, 80) ?: null,
            substr(trim((string) ($input['business_name'] ?? '')), 0, 220) ?: null,
            substr(trim((string) ($input['website_url'] ?? '')), 0, 600) ?: null,
            substr(trim((string) ($input['zip_code'] ?? '')), 0, 20) ?: null,
            substr(trim((string) ($input['category'] ?? '')), 0, 120) ?: null,
            trim((string) ($input['message'] ?? '')) ?: null,
            'new',
            $priority,
            substr(trim((string) ($input['region_country'] ?? '')), 0, 80) ?: null,
            substr(trim((string) ($input['region_state'] ?? '')), 0, 120) ?: null,
            substr(trim((string) ($input['region_city'] ?? '')), 0, 120) ?: null,
            substr(trim((string) ($input['region_postal'] ?? $input['zip_code'] ?? '')), 0, 40) ?: null,
            mg_crm_hash(mg_client_ip()),
            mg_crm_hash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            json_encode(['source' => $sourcePage], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $leadId = (int) $pdo->lastInsertId();
        mg_crm_record_event($leadId, 'crm_lead.created', null, 'new', $actorUserId, null, ['source_page' => $sourcePage]);

        if ($autoAssign) {
            $salesUser = mg_crm_pick_sales_user($regionCode ?: null);
            if ($salesUser) {
                mg_crm_assign_lead($leadId, (int) $salesUser['user_id'], $actorUserId, 'auto_least_open', 'Auto-assigned to active sales roster user.');
            }
        }

        $pdo->commit();
        return mg_crm_get_lead($leadId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function mg_crm_get_lead(int $leadId): array
{
    $stmt = mg_db()->prepare(
        'SELECT l.*, u.email AS assigned_email, COALESCE(u.display_name, u.full_name, u.email) AS assigned_name
         FROM crm_leads l
         LEFT JOIN users u ON u.id = l.assigned_user_id
         WHERE l.id = ? LIMIT 1'
    );
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    return $lead ?: [];
}

function mg_crm_list_leads(array $user, array $filters = []): array
{
    $pdo = mg_db();
    $where = [];
    $params = [];

    if (!mg_crm_user_can_view_all($user)) {
        $where[] = 'l.assigned_user_id = ?';
        $params[] = (int) $user['id'];
    }

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all') {
        $where[] = 'l.status = ?';
        $params[] = mg_crm_normalize_lead_status($status);
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(l.name LIKE ? OR l.email LIKE ? OR l.business_name LIKE ? OR l.category LIKE ? OR l.zip_code LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $sql = 'SELECT l.*, COALESCE(u.display_name, u.full_name, u.email) AS assigned_name, u.email AS assigned_email
            FROM crm_leads l
            LEFT JOIN users u ON u.id = l.assigned_user_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY l.updated_at DESC, l.created_at DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function mg_crm_update_lead_status(int $leadId, string $status, int $actorUserId, ?string $note = null): array
{
    $pdo = mg_db();
    $lead = mg_crm_get_lead($leadId);
    if (!$lead) {
        mg_fail('Lead not found.', 404);
    }

    $from = (string) $lead['status'];
    $to = mg_crm_normalize_lead_status($status);
    $stmt = $pdo->prepare('UPDATE crm_leads SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$to, $leadId]);
    mg_crm_record_event($leadId, 'crm_lead.status_changed', $from, $to, $actorUserId, $note);

    if ($note) {
        mg_crm_add_note($leadId, $actorUserId, $note);
    }

    return mg_crm_get_lead($leadId);
}

function mg_crm_add_note(int $leadId, int $userId, string $note): void
{
    $note = trim($note);
    if ($note === '') {
        return;
    }
    $stmt = mg_db()->prepare('INSERT INTO crm_lead_notes (public_id, lead_id, user_id, note, visibility, created_at, updated_at) VALUES (?, ?, ?, ?, "internal", NOW(), NOW())');
    $stmt->execute([mg_crm_public_id('cln'), $leadId, $userId, $note]);
    mg_crm_record_event($leadId, 'crm_lead.note_added', null, null, $userId, $note);
}

function mg_crm_record_page_view(array $input): void
{
    try {
        $stmt = mg_db()->prepare(
            'INSERT INTO website_analytics_events
             (public_id, event_type, source_page, path, referrer, utm_source, utm_medium, utm_campaign, utm_term, utm_content, region_country, region_state, region_city, region_postal, timezone_label, ip_hash, user_agent_hash, session_key_hash, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            mg_crm_public_id('wae'),
            substr((string) ($input['event_type'] ?? 'page_view'), 0, 80),
            substr((string) ($input['source_page'] ?? 'learn-more'), 0, 180),
            substr((string) ($input['path'] ?? ($_SERVER['REQUEST_URI'] ?? '')), 0, 500),
            substr((string) ($input['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 700),
            substr((string) ($input['utm_source'] ?? ''), 0, 120) ?: null,
            substr((string) ($input['utm_medium'] ?? ''), 0, 120) ?: null,
            substr((string) ($input['utm_campaign'] ?? ''), 0, 160) ?: null,
            substr((string) ($input['utm_term'] ?? ''), 0, 160) ?: null,
            substr((string) ($input['utm_content'] ?? ''), 0, 160) ?: null,
            substr((string) ($input['region_country'] ?? ''), 0, 80) ?: null,
            substr((string) ($input['region_state'] ?? ''), 0, 120) ?: null,
            substr((string) ($input['region_city'] ?? ''), 0, 120) ?: null,
            substr((string) ($input['region_postal'] ?? ''), 0, 40) ?: null,
            substr((string) ($input['timezone_label'] ?? ''), 0, 120) ?: null,
            mg_crm_hash(mg_client_ip()),
            mg_crm_hash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            mg_crm_hash(session_id()),
            json_encode(['screen' => $input['screen'] ?? null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        mg_security_log('warning', 'crm.analytics_failed', 'Analytics event failed.', ['exception' => $e->getMessage()]);
    }
}

function mg_crm_dashboard_stats(array $user): array
{
    $pdo = mg_db();
    $ownOnly = !mg_crm_user_can_view_all($user);
    $params = [];
    $where = '';
    if ($ownOnly) {
        $where = ' WHERE assigned_user_id = ?';
        $params[] = (int) $user['id'];
    }

    $stmt = $pdo->prepare('SELECT status, COUNT(*) AS count FROM crm_leads' . $where . ' GROUP BY status');
    $stmt->execute($params);
    $byStatus = $stmt->fetchAll() ?: [];

    $todayStmt = $pdo->query('SELECT COUNT(*) FROM website_analytics_events WHERE created_at >= CURDATE()');
    $leadTodayStmt = $pdo->query('SELECT COUNT(*) FROM crm_leads WHERE created_at >= CURDATE()');

    return [
        'by_status' => $byStatus,
        'page_views_today' => (int) $todayStmt->fetchColumn(),
        'leads_today' => (int) $leadTodayStmt->fetchColumn(),
    ];
}
