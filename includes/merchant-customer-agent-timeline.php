<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-messages.php';

function mg_customer_agent_timeline_event_types(): array
{
    return [
        'crm.playbook.triggered',
        'crm.followup.created',
        'crm.agent.approval.approved',
        'crm.agent.approval.rejected',
        'crm.agent.approval.deferred',
        'crm.agent.approval.task_created',
        'crm.agent.execution.started',
        'crm.agent.execution.completed',
        'crm.agent.execution.failed',
        'crm.agent.execution.skipped',
        'crm.agent.message.draft.created',
        'crm.agent.message.draft.edited',
        'crm.agent.message.draft.approved',
        'crm.agent.message.sent',
        'crm.agent.message.discarded',
        'crm.agent.message.followup_created',
    ];
}

function mg_customer_agent_timeline_json($value): array
{
    return mg_automation_json($value);
}

function mg_customer_agent_timeline_url(string $type): string
{
    if (str_starts_with($type, 'crm.agent.approval.')) return '/merchant-agent-approvals.php';
    if (str_starts_with($type, 'crm.agent.execution.')) return '/merchant-agent-execution.php';
    if (str_starts_with($type, 'crm.agent.message.')) return '/merchant-agent-messages.php';
    if ($type === 'crm.followup.created' || $type === 'crm.agent.approval.task_created' || $type === 'crm.agent.message.followup_created') return '/merchant-followups.php';
    return '/merchant-agent-monitor.php';
}

function mg_customer_agent_timeline_group(string $type): string
{
    if ($type === 'crm.playbook.triggered') return 'agent_recommendations';
    if (str_starts_with($type, 'crm.agent.approval.')) return 'merchant_decisions';
    if (str_starts_with($type, 'crm.agent.execution.')) return 'executions';
    if (str_starts_with($type, 'crm.agent.message.')) return 'message_drafts_sends';
    if ($type === 'crm.followup.created') return 'followup_tasks';
    return 'agent_recommendations';
}

function mg_customer_agent_timeline_tone(string $group, string $type): string
{
    if (str_contains($type, '.failed') || str_contains($type, '.rejected') || str_contains($type, '.discarded')) return 'is-red';
    if (str_contains($type, '.approved') || str_contains($type, '.completed') || str_contains($type, '.sent')) return 'is-green';
    if ($group === 'merchant_decisions') return 'is-indigo';
    if ($group === 'executions') return 'is-blue';
    if ($group === 'message_drafts_sends') return 'is-purple';
    if ($group === 'followup_tasks') return 'is-orange';
    return 'is-blue';
}

function mg_customer_agent_timeline_icon(string $group, string $type): string
{
    if ($group === 'merchant_decisions') return '✓';
    if ($group === 'executions') return '▶';
    if ($group === 'message_drafts_sends') return '💬';
    if ($group === 'followup_tasks') return '⏱';
    return '⚙';
}

function mg_customer_agent_timeline_label(string $type): string
{
    $map = [
        'crm.playbook.triggered' => 'Agent recommendation',
        'crm.followup.created' => 'Follow-up task created',
        'crm.agent.approval.approved' => 'Merchant approved action',
        'crm.agent.approval.rejected' => 'Merchant rejected action',
        'crm.agent.approval.deferred' => 'Merchant deferred action',
        'crm.agent.approval.task_created' => 'Merchant converted action to task',
        'crm.agent.execution.started' => 'Agent execution started',
        'crm.agent.execution.completed' => 'Agent execution completed',
        'crm.agent.execution.failed' => 'Agent execution failed',
        'crm.agent.execution.skipped' => 'Agent execution skipped',
        'crm.agent.message.draft.created' => 'Agent message draft created',
        'crm.agent.message.draft.edited' => 'Message draft edited',
        'crm.agent.message.draft.approved' => 'Message draft approved',
        'crm.agent.message.sent' => 'Message sent',
        'crm.agent.message.discarded' => 'Message draft discarded',
        'crm.agent.message.followup_created' => 'Message converted to follow-up',
    ];
    return $map[$type] ?? ucwords(str_replace(['crm.', '.', '_'], ['', ' ', ' '], $type));
}

function mg_customer_agent_timeline_resolve_contact(PDO $pdo, int $merchantId, array $input): array
{
    $contactRef = strtolower(trim((string)($input['contact_id'] ?? $input['crm_contact_id'] ?? $input['id'] ?? '')));
    $campaignContactRef = strtolower(trim((string)($input['campaign_contact_id'] ?? '')));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    if ($contactRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $contactRef) === 1) {
        $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$contactRef, $merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    if ($campaignContactRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $campaignContactRef) === 1) {
        $stmt = $pdo->prepare('SELECT email,user_id,name FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$campaignContactRef, $merchantId]);
        $cc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cc) {
            $email = strtolower((string)($cc['email'] ?? '')) ?: $email;
            $userId = (int)($cc['user_id'] ?? 0);
            if ($email !== '') {
                $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
                $stmt->execute([$merchantId, $email]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
            if ($userId > 0) {
                $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND user_id=? LIMIT 1');
                $stmt->execute([$merchantId, $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
        }
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
        $stmt->execute([$merchantId, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1');
    $stmt->execute([$merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
    mg_fail('Customer profile not found for this merchant.', 404);
}

function mg_customer_agent_timeline_campaign_contact_ids(PDO $pdo, int $merchantId, array $contact): array
{
    $ids = [];
    $email = strtolower((string)($contact['primary_email'] ?? ''));
    $userId = (int)($contact['user_id'] ?? 0);
    if ($email === '' && $userId <= 0) return ['db_ids' => [], 'public_ids' => []];
    $where = [];
    $params = [$merchantId];
    if ($email !== '') { $where[] = 'email=?'; $params[] = $email; }
    if ($userId > 0) { $where[] = 'user_id=?'; $params[] = $userId; }
    $stmt = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE merchant_user_id=? AND (' . implode(' OR ', $where) . ') ORDER BY updated_at DESC,id DESC');
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $ids[] = $row;
    return [
        'db_ids' => array_values(array_unique(array_map(static fn($r) => (int)$r['id'], $ids))),
        'public_ids' => array_values(array_unique(array_map(static fn($r) => (string)$r['public_id'], $ids))),
    ];
}

function mg_customer_agent_timeline_context_matches(array $ctx, array $contact, array $contactRefs): bool
{
    $email = strtolower((string)($contact['primary_email'] ?? ''));
    $userId = (int)($contact['user_id'] ?? 0);
    $publicIds = $contactRefs['public_ids'] ?? [];
    if ($email !== '') {
        foreach (['customer_email','email','recipient_email'] as $key) {
            if (strtolower((string)($ctx[$key] ?? '')) === $email) return true;
        }
    }
    if ($userId > 0) {
        foreach (['user_id','customer_user_id','recipient_user_id'] as $key) {
            if ((int)($ctx[$key] ?? 0) === $userId) return true;
        }
    }
    foreach (['campaign_contact_id','contact_public_id','customer_public_id'] as $key) {
        if (($ctx[$key] ?? '') !== '' && in_array((string)$ctx[$key], $publicIds, true)) return true;
    }
    return false;
}

function mg_customer_agent_timeline_rows(PDO $pdo, int $merchantId, array $contact, array $input = []): array
{
    $types = mg_customer_agent_timeline_event_types();
    $contactRefs = mg_customer_agent_timeline_campaign_contact_ids($pdo, $merchantId, $contact);
    $dbIds = $contactRefs['db_ids'];
    $params = [$merchantId];
    $inTypes = implode(',', array_fill(0, count($types), '?'));
    array_push($params, ...$types);
    $where = ['merchant_user_id=?', "event_type IN ({$inTypes})"];
    if ($dbIds) {
        $where[] = '(contact_id IN (' . implode(',', array_fill(0, count($dbIds), '?')) . ') OR contact_id IS NULL)';
        array_push($params, ...$dbIds);
    }
    $stmt = $pdo->prepare('SELECT public_id,event_type,event_context_json,campaign_id,contact_id,created_at FROM campaign_events WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC,id DESC LIMIT 250');
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_customer_agent_timeline_json($row['event_context_json'] ?? null);
        $contactId = $row['contact_id'] !== null ? (int)$row['contact_id'] : 0;
        if ($contactId > 0 && $dbIds && !in_array($contactId, $dbIds, true)) continue;
        if ($contactId === 0 && !mg_customer_agent_timeline_context_matches($ctx, $contact, $contactRefs)) continue;
        $type = (string)$row['event_type'];
        $group = mg_customer_agent_timeline_group($type);
        $title = mg_customer_agent_timeline_label($type);
        $playbook = (string)($ctx['playbook_title'] ?? $ctx['playbook_key'] ?? '');
        $why = (string)($ctx['why'] ?? $ctx['reason'] ?? $ctx['result_summary'] ?? $ctx['note'] ?? '');
        $guardrail = (string)($ctx['guardrail_applied'] ?? '');
        $message = (string)($ctx['message_body'] ?? $ctx['draft_body'] ?? '');
        $bodyParts = array_values(array_filter([$playbook, $why, $guardrail, $message]));
        $rows[] = [
            'id' => (string)$row['public_id'],
            'event_type' => $type,
            'group' => $group,
            'title' => $title,
            'body' => implode(' · ', array_slice($bodyParts, 0, 3)),
            'why' => $why,
            'guardrail_applied' => $guardrail,
            'playbook_title' => $playbook,
            'campaign_id' => $row['campaign_id'] !== null ? (int)$row['campaign_id'] : null,
            'contact_id' => $contactId ?: null,
            'actor_user_id' => (int)($ctx['decided_by_user_id'] ?? 0),
            'at' => $row['created_at'] ?? null,
            'icon' => mg_customer_agent_timeline_icon($group, $type),
            'tone' => mg_customer_agent_timeline_tone($group, $type),
            'action_url' => mg_customer_agent_timeline_url($type),
            'context' => $ctx,
        ];
    }
    $limit = max(1, min(100, (int)($input['limit'] ?? 60)));
    return array_slice($rows, 0, $limit);
}

function mg_customer_agent_timeline_summary(array $items): array
{
    $summary = ['total' => count($items), 'agent_recommendations' => 0, 'merchant_decisions' => 0, 'executions' => 0, 'message_drafts_sends' => 0, 'followup_tasks' => 0];
    foreach ($items as $item) {
        $group = (string)($item['group'] ?? 'agent_recommendations');
        if (isset($summary[$group])) $summary[$group]++;
    }
    return $summary;
}

function mg_customer_agent_timeline(PDO $pdo, int $merchantId, array $input = []): array
{
    $contact = mg_customer_agent_timeline_resolve_contact($pdo, $merchantId, $input);
    $items = mg_customer_agent_timeline_rows($pdo, $merchantId, $contact, $input);
    return [
        'customer' => [
            'id' => (string)$contact['public_id'],
            'name' => (string)($contact['display_name'] ?: 'Customer'),
            'email' => (string)($contact['primary_email'] ?? ''),
            'user_id' => (int)($contact['user_id'] ?? 0),
        ],
        'items' => $items,
        'summary' => mg_customer_agent_timeline_summary($items),
        'groups' => ['agent_recommendations','merchant_decisions','executions','message_drafts_sends','followup_tasks'],
        'links' => [
            'agent_monitor' => '/merchant-agent-monitor.php',
            'review_queue' => '/merchant-agent-approvals.php',
            'execution_center' => '/merchant-agent-execution.php',
            'message_outbox' => '/merchant-agent-messages.php',
            'followups' => '/merchant-followups.php',
        ],
    ];
}
