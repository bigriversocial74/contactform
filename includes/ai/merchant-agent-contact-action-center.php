<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-crm-contact-context.php';

const MG_MERCHANT_CONTACT_ACTION_CENTER_VERSION = 1;

function mg_merchant_contact_action_center_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_merchant_contact_action_center_uuid(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/^[a-f0-9-]{36}$/', $value) === 1 ? $value : '';
}

function mg_merchant_contact_action_center_thread_id(PDO $pdo, int $actorId, mixed $value): string
{
    $thread = mg_agent_thread_by_id($pdo, $actorId, mg_ai_chat_clean($value, 80));
    return (string)($thread['id'] ?? '');
}

function mg_merchant_contact_action_center_latest_selection(PDO $pdo, int $actorId, string $threadId): array
{
    if ($actorId < 1 || $threadId === '') return [];
    try {
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.contact_selected','merchant.agent_chat.contact_cleared') AND event_context_json LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$actorId, '%\"thread_public_id\":\"' . str_replace(['%','_'], ['\\%','\\_'], $threadId) . '\"%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (string)$row['event_type'] === 'merchant.agent_chat.contact_cleared') return [];
        $context = mg_merchant_contact_action_center_json($row['event_context_json'] ?? null);
        $contactId = mg_merchant_contact_action_center_uuid($context['crm_contact_id'] ?? '');
        if ($contactId === '') return [];
        return [
            'contact_id' => $contactId,
            'mention' => mg_ai_chat_clean($context['crm_contact_mention'] ?? '', 90),
            'selected_at' => $row['created_at'] ?? null,
        ];
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_contact_action_center_record_selection(PDO $pdo, int $actorId, string $threadId, ?array $contact): void
{
    if ($actorId < 1 || $threadId === '') return;
    $selected = is_array($contact) && mg_merchant_contact_action_center_uuid($contact['id'] ?? '') !== '';
    $context = [
        'source' => 'merchant_contact_action_center',
        'contract_version' => MG_MERCHANT_CONTACT_ACTION_CENTER_VERSION,
        'thread_public_id' => $threadId,
        'crm_contact_id' => $selected ? (string)$contact['id'] : '',
        'crm_contact_mention' => $selected ? (string)($contact['mention'] ?? '') : '',
        'crm_contact_name' => $selected ? mg_ai_chat_clean($contact['name'] ?? 'CRM contact', 180) : '',
        'guardrail_applied' => 'Selection stores public CRM identity only. Customer actions remain approval-first.',
    ];
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([
            mg_ai_chat_uuid(),
            $actorId,
            null,
            null,
            $selected ? 'merchant.agent_chat.contact_selected' : 'merchant.agent_chat.contact_cleared',
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    if (function_exists('mg_audit')) {
        mg_audit($selected ? 'merchant.agent.contact_selected' : 'merchant.agent.contact_cleared', $actorId, [
            'thread_id' => $threadId,
            'crm_contact_id' => $selected ? (string)$contact['id'] : '',
        ]);
    }
}

function mg_merchant_contact_action_center_find_contact(PDO $pdo, int $merchantOwnerId, int $actorId, string $threadId, array $input = []): ?array
{
    $contactId = mg_merchant_contact_action_center_uuid($input['selected_contact_id'] ?? $input['contact_id'] ?? '');
    if ($contactId === '') {
        $selection = mg_merchant_contact_action_center_latest_selection($pdo, $actorId, $threadId);
        $contactId = mg_merchant_contact_action_center_uuid($selection['contact_id'] ?? '');
    }
    if ($contactId !== '') {
        $contacts = mg_merchant_crm_search_contacts_by_ids($pdo, $merchantOwnerId, [$contactId]);
        if (!empty($contacts[0])) return $contacts[0];
    }

    $mention = strtolower(trim((string)($input['selected_contact_mention'] ?? $input['contact_mention'] ?? '')));
    $mention = ltrim($mention, '@');
    if ($mention !== '') return mg_merchant_agent_crm_exact_contact($pdo, $merchantOwnerId, $mention);
    return null;
}

function mg_merchant_contact_action_center_campaign_contact_ids(PDO $pdo, int $merchantOwnerId, string $crmContactId): array
{
    try {
        $stmt = $pdo->prepare('SELECT user_id,primary_email FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$merchantOwnerId, $crmContactId]);
        $identity = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($identity)) return ['public'=>[], 'database'=>[]];
        $where = [];
        $params = [$merchantOwnerId];
        $userId = (int)($identity['user_id'] ?? 0);
        $email = strtolower(trim((string)($identity['primary_email'] ?? '')));
        if ($userId > 0) { $where[] = 'user_id=?'; $params[] = $userId; }
        if ($email !== '') { $where[] = 'LOWER(email)=?'; $params[] = $email; }
        if ($where === []) return ['public'=>[], 'database'=>[]];
        $stmt = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE merchant_user_id=? AND (' . implode(' OR ', $where) . ') ORDER BY updated_at DESC,id DESC LIMIT 50');
        $stmt->execute($params);
        $public = [];
        $database = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $public[] = (string)$row['public_id'];
            $database[] = (int)$row['id'];
        }
        return ['public'=>array_values(array_unique(array_filter($public))), 'database'=>array_values(array_unique(array_filter($database)))];
    } catch (Throwable) {
        return ['public'=>[], 'database'=>[]];
    }
}

function mg_merchant_contact_action_center_notes(PDO $pdo, int $merchantOwnerId, string $crmContactId): array
{
    if (!mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_notes')) return ['count'=>0,'items'=>[]];
    try {
        $stmt = $pdo->prepare('SELECT id FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$merchantOwnerId, $crmContactId]);
        $databaseId = (int)$stmt->fetchColumn();
        if ($databaseId < 1) return ['count'=>0,'items'=>[]];
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM merchant_crm_notes WHERE merchant_user_id=? AND crm_contact_id=?');
        $countStmt->execute([$merchantOwnerId, $databaseId]);
        $stmt = $pdo->prepare('SELECT public_id,note,created_at,updated_at FROM merchant_crm_notes WHERE merchant_user_id=? AND crm_contact_id=? ORDER BY created_at DESC,id DESC LIMIT 4');
        $stmt->execute([$merchantOwnerId, $databaseId]);
        $items = array_map(static fn(array $row): array => [
            'id'=>(string)$row['public_id'],
            'note'=>mg_ai_chat_clean($row['note'] ?? '', 360),
            'created_at'=>$row['created_at'] ?? null,
            'updated_at'=>$row['updated_at'] ?? null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        return ['count'=>(int)$countStmt->fetchColumn(),'items'=>$items];
    } catch (Throwable) {
        return ['count'=>0,'items'=>[]];
    }
}

function mg_merchant_contact_action_center_messages(PDO $pdo, int $merchantOwnerId, array $campaignContactPublicIds): array
{
    if ($campaignContactPublicIds === [] || !mg_merchant_crm_search_table_exists($pdo, 'message_threads') || !mg_merchant_crm_search_table_exists($pdo, 'messages')) return ['count'=>0,'items'=>[]];
    try {
        $items = [];
        $count = 0;
        foreach (array_slice($campaignContactPublicIds, 0, 10) as $contactId) {
            $pattern = 'crm:' . $contactId . ':%';
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM message_threads mt INNER JOIN messages m ON m.thread_id=mt.id WHERE mt.created_by_user_id=? AND mt.conversation_key LIKE ?');
            $countStmt->execute([$merchantOwnerId, $pattern]);
            $count += (int)$countStmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT m.public_id,m.body,m.sender_user_id,m.created_at,mt.public_id thread_public_id FROM message_threads mt INNER JOIN messages m ON m.thread_id=mt.id WHERE mt.created_by_user_id=? AND mt.conversation_key LIKE ? ORDER BY m.created_at DESC,m.id DESC LIMIT 4');
            $stmt->execute([$merchantOwnerId, $pattern]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = [
                    'id'=>(string)$row['public_id'],
                    'preview'=>mg_ai_chat_clean($row['body'] ?? '', 240),
                    'direction'=>(int)$row['sender_user_id'] === $merchantOwnerId ? 'outbound' : 'inbound',
                    'created_at'=>$row['created_at'] ?? null,
                    'thread_url'=>'/merchant-crm.php?tab=messages&thread=' . rawurlencode((string)$row['thread_public_id']),
                ];
            }
        }
        usort($items, static fn(array $left, array $right): int => (strtotime((string)($right['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($left['created_at'] ?? '')) ?: 0));
        return ['count'=>$count,'items'=>array_slice($items, 0, 4)];
    } catch (Throwable) {
        return ['count'=>0,'items'=>[]];
    }
}

function mg_merchant_contact_action_center_followups(PDO $pdo, int $merchantOwnerId, array $campaignContactDatabaseIds): array
{
    if ($campaignContactDatabaseIds === []) return ['count'=>0,'open'=>0,'overdue'=>0,'items'=>[]];
    try {
        $sql = "SELECT public_id,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.followup.created' AND contact_id IN (" . implode(',', array_fill(0, count($campaignContactDatabaseIds), '?')) . ') ORDER BY id DESC LIMIT 50';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$merchantOwnerId], $campaignContactDatabaseIds));
        $items = [];
        $open = 0;
        $overdue = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $context = mg_merchant_contact_action_center_json($row['event_context_json'] ?? null);
            $status = strtolower((string)($context['status'] ?? 'open'));
            $completed = $status === 'completed' || !empty($context['completed_at']);
            $dueAt = trim((string)($context['due_at'] ?? ''));
            $isOverdue = !$completed && $dueAt !== '' && strtotime($dueAt) !== false && strtotime($dueAt) < strtotime(date('Y-m-d'));
            if (!$completed) $open++;
            if ($isOverdue) $overdue++;
            if (count($items) < 4) {
                $items[] = [
                    'id'=>(string)$row['public_id'],
                    'note'=>mg_ai_chat_clean($context['note'] ?? 'CRM follow-up', 260),
                    'status'=>$completed ? 'completed' : ($isOverdue ? 'overdue' : $status),
                    'due_at'=>$dueAt !== '' ? $dueAt : null,
                    'created_at'=>$row['created_at'] ?? null,
                    'action_url'=>'/merchant-followups.php?followup=' . rawurlencode((string)$row['public_id']),
                ];
            }
        }
        return ['count'=>count($items) > 0 ? count($stmt->fetchAll(PDO::FETCH_ASSOC)) : count($items),'open'=>$open,'overdue'=>$overdue,'items'=>$items];
    } catch (Throwable) {
        return ['count'=>0,'open'=>0,'overdue'=>0,'items'=>[]];
    }
}

function mg_merchant_contact_action_center_public(PDO $pdo, int $merchantOwnerId, array $contact, int $days = 90): array
{
    $details = mg_merchant_agent_crm_contact_details($pdo, $merchantOwnerId, $contact, $days);
    if ($details === []) return [];
    $contactId = (string)$details['id'];
    $campaignContacts = mg_merchant_contact_action_center_campaign_contact_ids($pdo, $merchantOwnerId, $contactId);
    $notes = mg_merchant_contact_action_center_notes($pdo, $merchantOwnerId, $contactId);
    $messages = mg_merchant_contact_action_center_messages($pdo, $merchantOwnerId, $campaignContacts['public']);
    $followups = mg_merchant_contact_action_center_followups($pdo, $merchantOwnerId, $campaignContacts['database']);
    $events = is_array($details['recent_events'] ?? null) ? $details['recent_events'] : [];
    $campaigns = is_array($details['campaign_history'] ?? null) ? $details['campaign_history'] : [];

    return [
        'contract_version'=>MG_MERCHANT_CONTACT_ACTION_CENTER_VERSION,
        'selected'=>true,
        'contact'=>[
            'id'=>$contactId,
            'mention'=>(string)$details['mention'],
            'name'=>(string)$details['name'],
            'lifecycle_stage'=>(string)$details['lifecycle_stage'],
            'crm_status'=>(string)$details['crm_status'],
            'engagement_score'=>(int)$details['engagement_score'],
            'engagement_label'=>(string)$details['engagement_label'],
            'next_best_action'=>(string)$details['next_best_action'],
            'has_account'=>!empty($details['has_account']),
            'email_verified'=>!empty($details['email_verified']),
            'last_activity_at'=>$details['last_engaged_at'] ?? $details['last_seen_at'] ?? null,
            'tags'=>$details['tags'] ?? [],
        ],
        'metrics'=>[
            'purchase_value_cents'=>(int)$details['total_purchase_cents'],
            'rewards_issued'=>(int)$details['total_rewards_issued'],
            'rewards_claimed'=>(int)$details['total_rewards_claimed'],
            'rewards_redeemed'=>(int)$details['total_rewards_redeemed'],
            'campaigns'=>count($campaigns),
            'recent_events'=>count($events),
            'messages'=>(int)$messages['count'],
            'notes'=>(int)$notes['count'],
            'followups'=>(int)$followups['count'],
            'open_followups'=>(int)$followups['open'],
            'overdue_followups'=>(int)$followups['overdue'],
        ],
        'recent_activity'=>array_slice($events, 0, 6),
        'campaign_history'=>array_slice($campaigns, 0, 6),
        'recent_messages'=>$messages['items'],
        'recent_notes'=>$notes['items'],
        'followup_tasks'=>$followups['items'],
        'links'=>[
            'profile'=>(string)($details['profile_url'] ?: '/merchant-customer.php?crm_contact_id=' . rawurlencode($contactId)),
            'timeline'=>(string)($contact['timeline_url'] ?? ($details['crm_url'] ?: '/merchant-crm.php?contact=' . rawurlencode($contactId) . '&action=timeline')),
            'crm'=>(string)($details['crm_url'] ?: '/merchant-crm.php?contact=' . rawurlencode($contactId)),
            'followups'=>'/merchant-followups.php?crm_contact_id=' . rawurlencode($contactId),
        ],
        'capabilities'=>[
            'summarize_activity'=>true,
            'draft_followup'=>true,
            'recommend_reward'=>true,
            'draft_campaign_invite'=>true,
            'create_followup_task'=>true,
            'open_profile'=>true,
            'open_timeline'=>true,
            'send_directly'=>false,
            'issue_reward_directly'=>false,
        ],
        'quick_actions'=>[
            ['key'=>'summarize_activity','label'=>'Summarize activity','approval_mode'=>'advisory'],
            ['key'=>'draft_followup','label'=>'Draft follow-up','approval_mode'=>'review_queue'],
            ['key'=>'recommend_reward','label'=>'Recommend reward','approval_mode'=>'review_queue'],
            ['key'=>'draft_campaign_invite','label'=>'Draft campaign invite','approval_mode'=>'review_queue'],
            ['key'=>'create_followup_task','label'=>'Create follow-up task','approval_mode'=>'review_queue'],
        ],
        'boundary'=>'Merchant-owned CRM context only. Email, phone, database IDs, and unrelated customer records are excluded. Generated actions require review.',
    ];
}

function mg_merchant_contact_action_center_state(PDO $pdo, int $merchantOwnerId, int $actorId, string $threadId, array $input = [], int $days = 90): array
{
    $contact = mg_merchant_contact_action_center_find_contact($pdo, $merchantOwnerId, $actorId, $threadId, $input);
    if (!$contact) return ['contract_version'=>MG_MERCHANT_CONTACT_ACTION_CENTER_VERSION,'selected'=>false,'contact'=>null,'quick_actions'=>[]];
    return mg_merchant_contact_action_center_public($pdo, $merchantOwnerId, $contact, $days);
}

function mg_merchant_contact_action_center_attach_state(PDO $pdo, int $merchantOwnerId, int $actorId, array $state, array $input = []): array
{
    $threadId = (string)($state['active_thread']['id'] ?? '');
    $state['contact_action_center'] = mg_merchant_contact_action_center_state($pdo, $merchantOwnerId, $actorId, $threadId, $input, (int)($input['days'] ?? 90));
    return $state;
}

function mg_merchant_contact_action_center_prompt(string $action, string $mention): array
{
    $mention = trim($mention) !== '' ? trim($mention) : 'the selected contact';
    return match ($action) {
        'summarize_activity' => ['message'=>$mention . ' summarize recent activity, purchases, campaign engagement, rewards, claims, redemptions, messages, notes, and follow-up tasks. Highlight the next best action.','output_type'=>'action_plan','approval_mode'=>'advisory'],
        'draft_followup' => ['message'=>$mention . ' draft a personalized follow-up message based on recent activity and the next best action. Prepare it for review and do not send it.','output_type'=>'message_draft','approval_mode'=>'review_queue'],
        'recommend_reward' => ['message'=>$mention . ' recommend the most appropriate reward based on purchases, claims, redemptions, campaign history, and engagement. Prepare a review-ready recommendation and do not issue anything.','output_type'=>'admin_recommendation','approval_mode'=>'review_queue'],
        'draft_campaign_invite' => ['message'=>$mention . ' draft a personalized campaign invitation based on campaign history and engagement. Prepare it for review and do not send it.','output_type'=>'message_draft','approval_mode'=>'review_queue'],
        'create_followup_task' => ['message'=>$mention . ' create a review-ready CRM follow-up task with a clear objective, due-date recommendation, and reason based on recent activity.','output_type'=>'admin_recommendation','approval_mode'=>'review_queue'],
        default => mg_fail('Unsupported contact action.', 422),
    };
}
