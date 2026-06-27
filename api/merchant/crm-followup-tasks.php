<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_ft_json(mixed $json): array
{
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}

function mg_ft_uuid(): string
{
    if (function_exists('mg_merchant_uuid')) return mg_merchant_uuid();
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 15) | 64);
    $b[8] = chr((ord($b[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_ft_clean_uuid(string $value, string $message = 'Invalid follow-up task.'): string
{
    $value = strtolower(trim($value));
    if ($value === '' || preg_match('/^[a-f0-9-]{36}$/i', $value) !== 1) mg_fail($message, 422);
    return $value;
}

function mg_ft_date(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : '';
}

function mg_ft_status(array $context): string
{
    $raw = strtolower(trim((string)($context['status'] ?? 'open')));
    if ($raw === 'completed' || !empty($context['completed_at'])) return 'completed';
    $snooze = mg_ft_date((string)($context['snoozed_until'] ?? ''));
    if ($snooze !== '' && $snooze > date('Y-m-d')) return 'snoozed';
    $due = mg_ft_date((string)($context['due_at'] ?? ''));
    if ($due !== '') {
        $today = date('Y-m-d');
        if ($due < $today) return 'overdue';
        if ($due === $today) return 'today';
        return 'upcoming';
    }
    return $raw !== '' ? $raw : 'open';
}

function mg_ft_customer_filters(PDO $pdo, int $merchantId, array $input, array &$params): array
{
    $filters = [];
    $campaignContactId = strtolower(trim((string)($input['campaign_contact_id'] ?? $input['contact_ref'] ?? '')));
    $crmContactId = strtolower(trim((string)($input['contact_id'] ?? $input['crm_contact_id'] ?? '')));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $userId = (int)($input['user_id'] ?? 0);

    if ($campaignContactId !== '') {
        if (preg_match('/^[a-f0-9-]{36}$/i', $campaignContactId) !== 1) mg_fail('Invalid campaign contact.', 422);
        $filters[] = 'cc.public_id=?';
        $params[] = $campaignContactId;
        return $filters;
    }

    if ($crmContactId !== '') {
        if (preg_match('/^[a-f0-9-]{36}$/i', $crmContactId) !== 1) mg_fail('Invalid customer profile.', 422);
        $stmt = $pdo->prepare('SELECT primary_email,user_id FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$crmContactId, $merchantId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) mg_fail('Customer profile not found for this merchant.', 404);
        $sub = [];
        $crmEmail = strtolower((string)($contact['primary_email'] ?? ''));
        $crmUserId = (int)($contact['user_id'] ?? 0);
        if ($crmEmail !== '') { $sub[] = 'LOWER(cc.email)=?'; $params[] = $crmEmail; }
        if ($crmUserId > 0) { $sub[] = 'cc.user_id=?'; $params[] = $crmUserId; }
        if (!$sub) $filters[] = '1=0'; else $filters[] = '(' . implode(' OR ', $sub) . ')';
        return $filters;
    }

    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) mg_fail('Invalid customer email.', 422);
        $filters[] = 'LOWER(cc.email)=?';
        $params[] = $email;
    }
    if ($userId > 0) {
        $filters[] = 'cc.user_id=?';
        $params[] = $userId;
    }
    return $filters;
}

function mg_ft_task_from_row(array $row): array
{
    $context = mg_ft_json($row['event_context_json'] ?? null);
    $status = mg_ft_status($context);
    $contactId = (string)($row['campaign_contact_public_id'] ?? '');
    $customerUrl = $contactId !== '' ? '/merchant-customer.php?campaign_contact_id=' . rawurlencode($contactId) . '&tab=followups' : '';
    $messageUrl = $contactId !== '' ? '/merchant-crm.php?tab=contacts&action=message&campaign_contact_id=' . rawurlencode($contactId) : '';
    $rewardUrl = $contactId !== '' ? '/merchant-crm.php?tab=contacts&action=reward&campaign_contact_id=' . rawurlencode($contactId) : '';
    return [
        'id' => (string)$row['public_id'],
        'followup_id' => (string)$row['public_id'],
        'campaign_contact_id' => $contactId,
        'campaign_id' => (string)($row['campaign_public_id'] ?? ''),
        'campaign_title' => (string)($row['campaign_title'] ?? ''),
        'campaign_type' => (string)($row['campaign_type'] ?? ''),
        'customer_name' => (string)($row['contact_name'] ?? '') ?: 'Customer',
        'customer_email' => (string)($row['contact_email'] ?? ''),
        'customer_user_id' => (int)($row['contact_user_id'] ?? 0),
        'note' => (string)($context['note'] ?? ''),
        'due_at' => $context['due_at'] ?? null,
        'status' => $status,
        'completed_at' => $context['completed_at'] ?? null,
        'snoozed_until' => $context['snoozed_until'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'notes' => is_array($context['task_notes'] ?? null) ? array_values($context['task_notes']) : [],
        'activity' => is_array($context['task_activity'] ?? null) ? array_values($context['task_activity']) : [],
        'customer_url' => $customerUrl,
        'message_url' => $messageUrl,
        'reward_url' => $rewardUrl,
        'action_url' => $customerUrl,
    ];
}

function mg_ft_summary(array $tasks): array
{
    $summary = ['total' => count($tasks), 'open' => 0, 'today' => 0, 'overdue' => 0, 'upcoming' => 0, 'snoozed' => 0, 'completed' => 0];
    foreach ($tasks as $task) {
        $status = (string)($task['status'] ?? 'open');
        if (isset($summary[$status])) $summary[$status]++;
        if ($status !== 'completed') $summary['open']++;
    }
    return $summary;
}

function mg_ft_filter_task(array $task, string $filter): bool
{
    $filter = strtolower(trim($filter));
    $status = (string)($task['status'] ?? 'open');
    if ($filter === '' || $filter === 'all') return true;
    if ($filter === 'open') return $status !== 'completed';
    return $status === $filter;
}

function mg_ft_load_tasks(PDO $pdo, int $merchantId, array $input): array
{
    $params = [$merchantId];
    $where = ['ce.merchant_user_id=?', "ce.event_type='crm.followup.created'"];
    foreach (mg_ft_customer_filters($pdo, $merchantId, $input, $params) as $filter) $where[] = $filter;
    $limit = max(1, min(200, (int)($input['limit'] ?? 100)));
    $sql = "SELECT ce.public_id,ce.event_context_json,ce.created_at,cc.public_id campaign_contact_public_id,cc.email contact_email,cc.name contact_name,cc.user_id contact_user_id,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_events ce LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id AND cc.merchant_user_id=ce.merchant_user_id LEFT JOIN campaigns c ON c.id=ce.campaign_id AND c.merchant_user_id=ce.merchant_user_id WHERE " . implode(' AND ', $where) . " ORDER BY COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.due_at')),ce.created_at) ASC,ce.id DESC LIMIT " . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = array_map('mg_ft_task_from_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    $filter = strtolower(trim((string)($input['status'] ?? $input['bucket'] ?? 'all')));
    if ($filter !== '' && $filter !== 'all') $tasks = array_values(array_filter($tasks, static fn(array $task): bool => mg_ft_filter_task($task, $filter)));
    return $tasks;
}

function mg_ft_load_one(PDO $pdo, int $merchantId, string $followupId, bool $forUpdate = false): array
{
    $sql = "SELECT ce.public_id,ce.event_context_json,ce.created_at,cc.public_id campaign_contact_public_id,cc.email contact_email,cc.name contact_name,cc.user_id contact_user_id,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_events ce LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id AND cc.merchant_user_id=ce.merchant_user_id LEFT JOIN campaigns c ON c.id=ce.campaign_id AND c.merchant_user_id=ce.merchant_user_id WHERE ce.public_id=? AND ce.merchant_user_id=? AND ce.event_type='crm.followup.created' LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$followupId, $merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Follow-up task not found for this merchant.', 404);
    return $row;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST' ? mg_require_permission('merchant.campaigns.manage') : mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $tasks = mg_ft_load_tasks($pdo, $merchantId, $_GET);
    mg_ok(['tasks' => $tasks, 'summary' => mg_ft_summary($tasks)]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$followupId = mg_ft_clean_uuid((string)($input['followup_id'] ?? $input['id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if (!in_array($action, ['complete', 'reopen', 'snooze', 'reschedule', 'add_note'], true)) mg_fail('Unsupported follow-up action.', 422);

try {
    $pdo->beginTransaction();
    $row = mg_ft_load_one($pdo, $merchantId, $followupId, true);
    $context = mg_ft_json($row['event_context_json'] ?? null);
    $activity = is_array($context['task_activity'] ?? null) ? $context['task_activity'] : [];
    $activity[] = ['action' => $action, 'at' => date('c'), 'by_user_id' => $merchantId];
    if (count($activity) > 25) $activity = array_slice($activity, -25);

    if ($action === 'complete') {
        $context['status'] = 'completed';
        $context['completed_at'] = date('c');
        $context['completed_by_user_id'] = $merchantId;
    } elseif ($action === 'reopen') {
        $context['status'] = 'open';
        unset($context['completed_at'], $context['completed_by_user_id'], $context['snoozed_until']);
    } elseif ($action === 'snooze') {
        $days = max(1, min(30, (int)($input['days'] ?? 3)));
        $dueAt = mg_ft_date((string)($input['due_at'] ?? '')) ?: date('Y-m-d', strtotime('+' . $days . ' days'));
        $context['status'] = 'open';
        $context['due_at'] = $dueAt;
        $context['snoozed_until'] = $dueAt;
        unset($context['completed_at'], $context['completed_by_user_id']);
    } elseif ($action === 'reschedule') {
        $dueAt = mg_ft_date((string)($input['due_at'] ?? ''));
        if ($dueAt === '') mg_fail('Choose a valid follow-up due date.', 422);
        $context['status'] = 'open';
        $context['due_at'] = $dueAt;
        unset($context['completed_at'], $context['completed_by_user_id'], $context['snoozed_until']);
    } elseif ($action === 'add_note') {
        $note = trim((string)($input['note'] ?? ''));
        if ($note === '' || mb_strlen($note) > 1000) mg_fail('Task note is required.', 422);
        $notes = is_array($context['task_notes'] ?? null) ? $context['task_notes'] : [];
        $notes[] = ['id' => mg_ft_uuid(), 'note' => $note, 'created_at' => date('c'), 'author_user_id' => $merchantId];
        if (count($notes) > 25) $notes = array_slice($notes, -25);
        $context['task_notes'] = $notes;
    }
    $context['task_activity'] = $activity;
    $pdo->prepare('UPDATE campaign_events SET event_context_json=? WHERE public_id=? AND merchant_user_id=? AND event_type=\'crm.followup.created\'')->execute([json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $followupId, $merchantId]);
    $pdo->commit();
    $row['event_context_json'] = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    mg_ok(['task' => mg_ft_task_from_row($row)], 'Follow-up task updated.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($error instanceof RuntimeException) mg_fail($error->getMessage(), 422);
    mg_security_log('error', 'merchant.crm_followup_task.failed', 'Unable to update CRM follow-up task.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to update follow-up task.', 500);
}
