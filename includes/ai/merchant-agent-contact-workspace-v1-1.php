<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-contact-action-center.php';
require_once dirname(__DIR__) . '/merchant-crm.php';

const MG_MERCHANT_CONTACT_WORKSPACE_VERSION = 1;

function mg_merchant_contact_workspace_table_exists(PDO $pdo, string $table): bool
{
    if (function_exists('mg_merchant_crm_search_table_exists')) {
        return mg_merchant_crm_search_table_exists($pdo, $table);
    }
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_merchant_contact_workspace_contact_row(PDO $pdo, int $merchantOwnerId, string $contactPublicId): array
{
    $contactPublicId = mg_merchant_contact_action_center_uuid($contactPublicId);
    if ($contactPublicId === '') mg_fail('Select a valid CRM contact.', 422);
    $stmt = $pdo->prepare('SELECT id,public_id,user_id,primary_email,display_name,lifecycle_stage,crm_status FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=? AND merged_into_contact_id IS NULL LIMIT 1');
    $stmt->execute([$merchantOwnerId, $contactPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) mg_fail('CRM contact was not found in this merchant workspace.', 404);
    return $row;
}

function mg_merchant_contact_workspace_add_note(PDO $pdo, int $merchantOwnerId, int $actorId, array $contact, array $input): array
{
    if (!mg_merchant_contact_workspace_table_exists($pdo, 'merchant_crm_notes')) {
        mg_fail('CRM notes are not available for this workspace.', 503);
    }
    $note = trim((string)($input['note'] ?? ''));
    if ($note === '' || mb_strlen($note) > 4000) mg_fail('Enter a CRM note up to 4,000 characters.', 422);
    $row = mg_merchant_contact_workspace_contact_row($pdo, $merchantOwnerId, (string)($contact['id'] ?? ''));
    $publicId = mg_ai_chat_uuid();
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO merchant_crm_notes (public_id,merchant_user_id,crm_contact_id,author_user_id,note,visibility,created_at,updated_at) VALUES (?,?,?,?,?,'merchant_internal',NOW(),NOW())")
            ->execute([$publicId, $merchantOwnerId, (int)$row['id'], $actorId, $note]);
        mg_merchant_crm_record_event($pdo, [
            'merchant_user_id'=>$merchantOwnerId,
            'campaign_type'=>'merchant_crm',
            'event_type'=>'crm.note.added',
            'source_type'=>'merchant_contact_action_center',
            'source_public_id'=>$publicId,
            'user_id'=>(int)($row['user_id'] ?? 0) ?: null,
            'email'=>(string)($row['primary_email'] ?? ''),
            'name'=>(string)($row['display_name'] ?? ''),
            'metadata'=>['note_id'=>$publicId,'crm_contact_id'=>(string)$row['public_id'],'author_user_id'=>$actorId],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'merchant.agent.contact_note_failed', 'Contact Action Center note could not be saved.', ['exception_class'=>$error::class], $actorId);
        mg_fail('Unable to save the CRM note.', 500);
    }
    if (function_exists('mg_audit')) {
        mg_audit('merchant.agent.contact_note_added', 'merchant_crm_contact', ['crm_contact_id'=>(string)$row['public_id'],'note_length'=>mb_strlen($note)], $actorId);
    }
    return ['id'=>$publicId,'note'=>$note,'created_at'=>date('c')];
}

function mg_merchant_contact_workspace_review_duplicate(PDO $pdo, int $actorId, string $idempotencyKey): array
{
    if ($idempotencyKey === '' || !mg_merchant_contact_workspace_table_exists($pdo, 'ai_merchant_plan_items')) return [];
    try {
        $stmt = $pdo->prepare("SELECT i.public_id item_id,p.public_id plan_id,i.status,i.title FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND JSON_VALID(i.suggested_payload_json)=1 AND JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.idempotency_key'))=? ORDER BY i.id DESC LIMIT 1");
        $stmt->execute([$actorId, $idempotencyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_merchant_contact_workspace_clean_date(mixed $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    $time = strtotime($value);
    if ($time === false) return '';
    return date('Y-m-d', $time);
}

function mg_merchant_contact_workspace_review_card(array $contact, array $input): array
{
    $kind = strtolower(trim((string)($input['draft_kind'] ?? '')));
    $contactId = (string)($contact['id'] ?? '');
    $mention = (string)($contact['mention'] ?? '');
    $name = mg_ai_chat_clean($contact['name'] ?? 'CRM contact', 180) ?: 'CRM contact';
    $idempotencyKey = mg_ai_chat_clean($input['idempotency_key'] ?? '', 120);
    if ($idempotencyKey === '') mg_fail('Refresh the draft and try again.', 422);

    if ($kind === 'message') {
        $channels = ['email','sms','crm_message','social_dm'];
        $channel = strtolower(trim((string)($input['channel'] ?? 'crm_message')));
        if (!in_array($channel, $channels, true)) mg_fail('Choose a supported message channel.', 422);
        $subject = mg_ai_chat_clean($input['subject'] ?? '', 180);
        $body = trim((string)($input['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 4000) mg_fail('Enter a message draft up to 4,000 characters.', 422);
        $channelLabel = ucwords(str_replace('_', ' ', $channel));
        return [
            'idempotency_key'=>$idempotencyKey,
            'card'=>[
                'type'=>'message_draft',
                'title'=>$channelLabel . ' draft for ' . $name,
                'body'=>mg_ai_chat_clean($body, 500),
                'action_url'=>'/merchant-agent-approvals.php',
                'bridgeable'=>true,
                'risk_level'=>'low',
                'review_action_key'=>'create_message_draft',
                'review_payload'=>[
                    'source'=>'merchant_contact_action_center_v1_1',
                    'draft_kind'=>'message',
                    'idempotency_key'=>$idempotencyKey,
                    'crm_contact_id'=>$contactId,
                    'crm_contact_mention'=>$mention,
                    'crm_contact_name'=>$name,
                    'channel'=>$channel,
                    'subject'=>$subject,
                    'body'=>$body,
                    'approval_required'=>true,
                    'send_directly'=>false,
                ],
            ],
            'summary'=>'Message draft prepared for Agent Review.',
        ];
    }

    if ($kind === 'followup') {
        $types = ['call','email','reward_reminder','campaign_invite','customer_service'];
        $priorities = ['low','medium','high'];
        $taskType = strtolower(trim((string)($input['task_type'] ?? 'customer_service')));
        $priority = strtolower(trim((string)($input['priority'] ?? 'medium')));
        if (!in_array($taskType, $types, true)) mg_fail('Choose a supported follow-up type.', 422);
        if (!in_array($priority, $priorities, true)) mg_fail('Choose a valid follow-up priority.', 422);
        $dueAt = mg_merchant_contact_workspace_clean_date($input['due_at'] ?? '');
        if ($dueAt === '') mg_fail('Choose a valid follow-up due date.', 422);
        $note = trim((string)($input['note'] ?? ''));
        if ($note === '' || mb_strlen($note) > 1000) mg_fail('Enter a follow-up objective up to 1,000 characters.', 422);
        $typeLabel = ucwords(str_replace('_', ' ', $taskType));
        return [
            'idempotency_key'=>$idempotencyKey,
            'card'=>[
                'type'=>'crm_followup_task',
                'title'=>$typeLabel . ' follow-up for ' . $name,
                'body'=>mg_ai_chat_clean($note . ' · Due ' . $dueAt . ' · ' . ucfirst($priority) . ' priority', 500),
                'action_url'=>'/merchant-agent-approvals.php',
                'bridgeable'=>true,
                'risk_level'=>$priority === 'high' ? 'medium' : 'low',
                'review_action_key'=>'create_crm_followup_task',
                'review_payload'=>[
                    'source'=>'merchant_contact_action_center_v1_1',
                    'draft_kind'=>'followup',
                    'idempotency_key'=>$idempotencyKey,
                    'crm_contact_id'=>$contactId,
                    'crm_contact_mention'=>$mention,
                    'crm_contact_name'=>$name,
                    'task_type'=>$taskType,
                    'priority'=>$priority,
                    'due_at'=>$dueAt,
                    'note'=>$note,
                    'approval_required'=>true,
                    'create_directly'=>false,
                ],
            ],
            'summary'=>'Follow-up task prepared for Agent Review.',
        ];
    }

    mg_fail('Choose a supported contact draft type.', 422);
}

function mg_merchant_contact_workspace_create_review_draft(PDO $pdo, array $user, int $merchantOwnerId, int $actorId, string $threadId, array $contact, array $input): array
{
    $row = mg_merchant_contact_workspace_contact_row($pdo, $merchantOwnerId, (string)($contact['id'] ?? ''));
    $contact['id'] = (string)$row['public_id'];
    $contact['name'] = (string)($contact['name'] ?? $row['display_name'] ?? 'CRM contact');
    $prepared = mg_merchant_contact_workspace_review_card($contact, $input);
    $duplicate = mg_merchant_contact_workspace_review_duplicate($pdo, $actorId, (string)$prepared['idempotency_key']);
    if ($duplicate !== []) {
        return ['duplicate'=>true,'plan_id'=>(string)$duplicate['plan_id'],'item_id'=>(string)$duplicate['item_id'],'status'=>(string)$duplicate['status'],'title'=>(string)$duplicate['title']];
    }

    $card = $prepared['card'];
    $messageId = '';
    try {
        $messageId = mg_ai_chat_record_message($pdo, $actorId, 'assistant', (string)$prepared['summary'], [$card], [
            'scope'=>'crm',
            'mode'=>'review',
            'output_type'=>$card['review_action_key'] === 'create_message_draft' ? 'message_draft' : 'admin_recommendation',
            'approval_mode'=>'review_queue',
            'context_profile'=>'crm_contact_workspace',
            'thread_public_id'=>$threadId,
            'crm_contact_ids'=>[(string)$contact['id']],
            'crm_contact_mentions'=>[(string)($contact['mention'] ?? '')],
            'source'=>'merchant_contact_action_center_v1_1',
        ]);
        $bridged = mg_ai_chat_bridge_to_review($pdo, $user, ['message_id'=>$messageId,'card_index'=>0]);
    } catch (Throwable $error) {
        if ($messageId !== '') {
            try { $pdo->prepare("DELETE FROM campaign_events WHERE merchant_user_id=? AND public_id=? AND event_type='merchant.agent_chat.assistant' LIMIT 1")->execute([$actorId, $messageId]); } catch (Throwable) {}
        }
        mg_security_log('error', 'merchant.agent.contact_review_draft_failed', 'Contact Action Center draft could not be added to review.', ['exception_class'=>$error::class], $actorId);
        mg_fail('Unable to add this contact draft to Agent Review.', 500);
    }

    if (function_exists('mg_audit')) {
        mg_audit('merchant.agent.contact_review_draft_created', 'merchant_crm_contact', [
            'crm_contact_id'=>(string)$contact['id'],
            'draft_kind'=>(string)($input['draft_kind'] ?? ''),
            'review_item_id'=>(string)($bridged['item_id'] ?? ''),
        ], $actorId);
    }
    return ['duplicate'=>false,'message_id'=>$messageId,'plan_id'=>(string)($bridged['plan_id'] ?? ''),'item_id'=>(string)($bridged['item_id'] ?? ''),'status'=>'recommended','title'=>(string)$card['title']];
}

function mg_merchant_contact_workspace_review_status(PDO $pdo, int $actorId, string $contactPublicId): array
{
    if (!mg_merchant_contact_workspace_table_exists($pdo, 'ai_merchant_plan_items')) return ['count'=>0,'waiting'=>0,'items'=>[]];
    try {
        $stmt = $pdo->prepare("SELECT i.public_id item_id,p.public_id plan_id,i.title,i.action_key,i.status item_status,p.status plan_status,i.created_at,i.updated_at,JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.draft_kind')) draft_kind,JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.channel')) channel,JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.due_at')) due_at FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND JSON_VALID(i.suggested_payload_json)=1 AND JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.source'))='merchant_contact_action_center_v1_1' AND JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.crm_contact_id'))=? ORDER BY i.id DESC LIMIT 12");
        $stmt->execute([$actorId, $contactPublicId]);
        $items = [];
        $waiting = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $raw = strtolower((string)($row['item_status'] ?? 'recommended'));
            $label = match ($raw) {
                'executed'=>'Executed',
                'approved'=>'Approved',
                'rejected'=>'Rejected',
                'failed'=>'Failed',
                'deferred'=>'Deferred',
                default=>'Waiting for review',
            };
            if ($label === 'Waiting for review') $waiting++;
            $items[] = [
                'item_id'=>(string)$row['item_id'],
                'plan_id'=>(string)$row['plan_id'],
                'title'=>(string)$row['title'],
                'action_key'=>(string)$row['action_key'],
                'draft_kind'=>(string)($row['draft_kind'] ?? ''),
                'channel'=>(string)($row['channel'] ?? ''),
                'due_at'=>$row['due_at'] ?: null,
                'status'=>$raw,
                'status_label'=>$label,
                'created_at'=>$row['created_at'] ?? null,
                'updated_at'=>$row['updated_at'] ?? null,
                'action_url'=>'/merchant-agent-approvals.php?item=' . rawurlencode((string)$row['item_id']),
            ];
        }
        return ['count'=>count($items),'waiting'=>$waiting,'items'=>$items];
    } catch (Throwable) {
        return ['count'=>0,'waiting'=>0,'items'=>[]];
    }
}

function mg_merchant_contact_workspace_attach_state(PDO $pdo, int $merchantOwnerId, int $actorId, array $state): array
{
    $center = is_array($state['contact_action_center'] ?? null) ? $state['contact_action_center'] : [];
    $contactId = mg_merchant_contact_action_center_uuid($center['contact']['id'] ?? '');
    if (empty($center['selected']) || $contactId === '') return $state;
    $reviews = mg_merchant_contact_workspace_review_status($pdo, $actorId, $contactId);
    $center['workspace_version'] = MG_MERCHANT_CONTACT_WORKSPACE_VERSION;
    $center['workspace'] = [
        'timeline_filters'=>[
            ['key'=>'all','label'=>'All activity'],
            ['key'=>'purchases','label'=>'Purchases'],
            ['key'=>'rewards','label'=>'Rewards'],
            ['key'=>'messages','label'=>'Messages'],
            ['key'=>'campaigns','label'=>'Campaigns'],
            ['key'=>'tasks_notes','label'=>'Tasks & notes'],
        ],
        'notes'=>['can_add'=>true,'max_length'=>4000,'items'=>$center['recent_notes'] ?? []],
        'followup_builder'=>[
            'types'=>['call'=>'Call','email'=>'Email','reward_reminder'=>'Reward reminder','campaign_invite'=>'Campaign invite','customer_service'=>'Customer service'],
            'priorities'=>['low'=>'Low','medium'=>'Medium','high'=>'High'],
            'requires_review'=>true,
        ],
        'message_builder'=>[
            'channels'=>['email'=>'Email','sms'=>'SMS','crm_message'=>'CRM message','social_dm'=>'Social DM'],
            'requires_review'=>true,
            'send_directly'=>false,
        ],
        'review_status'=>$reviews,
    ];
    $state['contact_action_center'] = $center;
    return $state;
}
