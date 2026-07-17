<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-plan-actions.php';
require_once __DIR__ . '/merchant-agent-contact-workspace-v1-1.php';

function mg_contact_workspace_review_is_payload(array $payload): bool
{
    return (string)($payload['source'] ?? '') === 'merchant_contact_action_center_v1_1'
        && in_array((string)($payload['draft_kind'] ?? ''), ['message','followup'], true)
        && mg_merchant_contact_action_center_uuid($payload['crm_contact_id'] ?? '') !== '';
}

function mg_contact_workspace_review_identity(PDO $pdo, int $merchantOwnerId, string $crmContactPublicId): array
{
    $crmContactPublicId = mg_merchant_contact_action_center_uuid($crmContactPublicId);
    if ($crmContactPublicId === '') throw new RuntimeException('Contact Action Center target is invalid.');
    $stmt = $pdo->prepare('SELECT id,public_id,user_id,primary_email,display_name FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=? AND merged_into_contact_id IS NULL LIMIT 1 FOR UPDATE');
    $stmt->execute([$merchantOwnerId, $crmContactPublicId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($contact)) throw new RuntimeException('Contact Action Center target no longer belongs to this merchant workspace.');

    $where = [];
    $params = [$merchantOwnerId];
    $userId = (int)($contact['user_id'] ?? 0);
    $email = strtolower(trim((string)($contact['primary_email'] ?? '')));
    if ($userId > 0) { $where[] = 'cc.user_id=?'; $params[] = $userId; }
    if ($email !== '') { $where[] = 'LOWER(cc.email)=?'; $params[] = $email; }
    $campaignContact = [];
    if ($where !== []) {
        $stmt = $pdo->prepare('SELECT cc.id,cc.public_id,cc.campaign_id,cc.user_id,cc.email,cc.name,c.title campaign_title,c.campaign_type FROM campaign_contacts cc LEFT JOIN campaigns c ON c.id=cc.campaign_id AND c.merchant_user_id=cc.merchant_user_id WHERE cc.merchant_user_id=? AND (' . implode(' OR ', $where) . ') ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) $campaignContact = $row;
    }
    return ['crm'=>$contact,'campaign_contact'=>$campaignContact];
}

function mg_contact_workspace_review_followup(PDO $pdo, int $merchantOwnerId, int $actorId, array $item, array $payload, array $identity, string $approvalId): array
{
    $note = trim((string)($payload['note'] ?? ''));
    $dueAt = mg_merchant_contact_workspace_clean_date($payload['due_at'] ?? '');
    $taskType = strtolower(trim((string)($payload['task_type'] ?? 'customer_service')));
    $priority = strtolower(trim((string)($payload['priority'] ?? 'medium')));
    if ($note === '' || mb_strlen($note) > 1000 || $dueAt === '') throw new RuntimeException('Reviewed follow-up payload is incomplete.');
    if (!in_array($taskType, ['call','email','reward_reminder','campaign_invite','customer_service'], true)) $taskType = 'customer_service';
    if (!in_array($priority, ['low','medium','high'], true)) $priority = 'medium';

    $crm = $identity['crm'];
    $campaignContact = $identity['campaign_contact'];
    $eventId = mg_ai_chat_uuid();
    $context = [
        'note'=>$note,
        'due_at'=>$dueAt,
        'status'=>'open',
        'task_type'=>$taskType,
        'priority'=>$priority,
        'idempotency_key'=>(string)($payload['idempotency_key'] ?? ''),
        'source'=>'merchant_contact_action_center_v1_1',
        'crm_contact_id'=>(string)$crm['public_id'],
        'campaign_contact_id'=>(string)($campaignContact['public_id'] ?? ''),
        'ai_plan_id'=>(string)($item['plan_public_id'] ?? ''),
        'ai_plan_item_id'=>(string)$item['public_id'],
        'approval_id'=>$approvalId,
        'approved_by_user_id'=>$actorId,
        'approval_required'=>true,
        'approved_at'=>date('c'),
    ];
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([
            $eventId,
            $merchantOwnerId,
            !empty($campaignContact['campaign_id']) ? (int)$campaignContact['campaign_id'] : null,
            !empty($campaignContact['id']) ? (int)$campaignContact['id'] : null,
            'crm.followup.created',
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    mg_merchant_crm_record_event($pdo, [
        'merchant_user_id'=>$merchantOwnerId,
        'campaign_id'=>!empty($campaignContact['campaign_id']) ? (int)$campaignContact['campaign_id'] : null,
        'campaign_type'=>(string)($campaignContact['campaign_type'] ?? 'merchant_crm'),
        'event_type'=>'crm.followup.created',
        'source_type'=>'merchant_contact_action_center_v1_1',
        'source_public_id'=>$eventId,
        'user_id'=>(int)($crm['user_id'] ?? 0) ?: null,
        'email'=>(string)($crm['primary_email'] ?? ''),
        'name'=>(string)($crm['display_name'] ?? ''),
        'metadata'=>$context,
    ]);
    return [
        'resource_type'=>'crm_followup',
        'resource_id'=>$eventId,
        'url'=>'/merchant-followups.php?followup=' . rawurlencode($eventId),
        'status'=>'followup_created',
        'crm_contact_id'=>(string)$crm['public_id'],
    ];
}

function mg_contact_workspace_review_message(PDO $pdo, int $merchantOwnerId, int $actorId, array $item, array $payload, array $identity, string $approvalId): array
{
    $body = trim((string)($payload['body'] ?? ''));
    if ($body === '' || mb_strlen($body) > 4000) throw new RuntimeException('Reviewed message draft is incomplete.');
    $channel = strtolower(trim((string)($payload['channel'] ?? 'crm_message')));
    if (!in_array($channel, ['email','sms','crm_message','social_dm'], true)) $channel = 'crm_message';
    $subject = mg_ai_plan_short($payload['subject'] ?? '', 180);
    $crm = $identity['crm'];
    $campaignContact = $identity['campaign_contact'];
    $messageDraftId = 'amd_' . substr(hash('sha256', $merchantOwnerId . '|' . (string)$item['public_id'] . '|' . (string)($payload['idempotency_key'] ?? '')), 0, 24);
    $eventId = mg_ai_chat_uuid();
    $context = [
        'message_draft_id'=>$messageDraftId,
        'execution_id'=>(string)$item['public_id'],
        'approval_id'=>$approvalId,
        'playbook_key'=>'contact_action_center_message',
        'playbook_title'=>(string)$item['title'],
        'customer_name'=>(string)($crm['display_name'] ?? $payload['crm_contact_name'] ?? 'CRM contact'),
        'customer_email'=>(string)($crm['primary_email'] ?? ''),
        'campaign_title'=>(string)($campaignContact['campaign_title'] ?? ''),
        'channel'=>$channel,
        'subject'=>$subject,
        'draft_body'=>$body,
        'message_body'=>$body,
        'why'=>(string)($item['reason'] ?? 'Merchant prepared this contact message in the Contact Action Center.'),
        'guardrail_applied'=>'Merchant approved creation of an editable message draft. Sending still requires the Agent Messages send action and its permission checks.',
        'expected_action'=>'Review or edit the message in Agent Messages before sending.',
        'source'=>'merchant_contact_action_center_v1_1',
        'crm_contact_id'=>(string)$crm['public_id'],
        'campaign_contact_id'=>(string)($campaignContact['public_id'] ?? ''),
        'ai_plan_id'=>(string)($item['plan_public_id'] ?? ''),
        'ai_plan_item_id'=>(string)$item['public_id'],
        'idempotency_key'=>(string)($payload['idempotency_key'] ?? ''),
        'approved_by_user_id'=>$actorId,
        'approved_at'=>date('c'),
        'send_directly'=>false,
    ];
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([
            $eventId,
            $merchantOwnerId,
            !empty($campaignContact['campaign_id']) ? (int)$campaignContact['campaign_id'] : null,
            !empty($campaignContact['id']) ? (int)$campaignContact['id'] : null,
            'crm.agent.message.draft.created',
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    mg_merchant_crm_record_event($pdo, [
        'merchant_user_id'=>$merchantOwnerId,
        'campaign_id'=>!empty($campaignContact['campaign_id']) ? (int)$campaignContact['campaign_id'] : null,
        'campaign_type'=>(string)($campaignContact['campaign_type'] ?? 'merchant_crm'),
        'event_type'=>'crm.agent.message.draft.created',
        'source_type'=>'merchant_contact_action_center_v1_1',
        'source_public_id'=>$eventId,
        'user_id'=>(int)($crm['user_id'] ?? 0) ?: null,
        'email'=>(string)($crm['primary_email'] ?? ''),
        'name'=>(string)($crm['display_name'] ?? ''),
        'metadata'=>[
            'message_draft_id'=>$messageDraftId,
            'channel'=>$channel,
            'subject'=>$subject,
            'ai_plan_item_id'=>(string)$item['public_id'],
        ],
    ]);
    return [
        'resource_type'=>'message_draft',
        'resource_id'=>$messageDraftId,
        'event_id'=>$eventId,
        'url'=>'/merchant-agent-messages.php?message=' . rawurlencode($messageDraftId),
        'status'=>'message_draft_created',
        'crm_contact_id'=>(string)$crm['public_id'],
        'send_directly'=>false,
    ];
}

function mg_contact_workspace_review_item(PDO $pdo, array $user, array $input): array
{
    $actorId = (int)$user['id'];
    $merchantOwnerId = max(1, (int)($input['_merchant_owner_id'] ?? $actorId));
    $itemPublicId = strtolower(trim((string)($input['item_id'] ?? '')));
    $approvalId = mg_ai_plan_short($input['approval_id'] ?? '', 120);
    $note = mg_ai_plan_short($input['note'] ?? '', 1000);

    $pdo->beginTransaction();
    try {
        $item = mg_ai_plan_item_owned($pdo, $actorId, $itemPublicId, true);
        if (!in_array((string)$item['status'], ['recommended','deferred','failed'], true)) {
            throw new RuntimeException('Recommendation is not available for review.');
        }
        $payload = mg_ai_plan_json($item['suggested_payload_json'] ?? null);
        if (!mg_contact_workspace_review_is_payload($payload)) throw new RuntimeException('Contact Action Center review payload is invalid.');
        $identity = mg_contact_workspace_review_identity($pdo, $merchantOwnerId, (string)$payload['crm_contact_id']);

        mg_ai_plan_record_review_event($pdo, $actorId, 'merchant.ai_plan_item.approved', $item, [
            'status'=>'approved',
            'merchant_note'=>$note,
            'decided_by_user_id'=>$actorId,
            'merchant_owner_id'=>$merchantOwnerId,
        ]);
        $execution = (string)$payload['draft_kind'] === 'followup'
            ? mg_contact_workspace_review_followup($pdo, $merchantOwnerId, $actorId, $item, $payload, $identity, $approvalId)
            : mg_contact_workspace_review_message($pdo, $merchantOwnerId, $actorId, $item, $payload, $identity, $approvalId);
        $pdo->prepare("UPDATE ai_merchant_plan_items SET status='executed',updated_at=NOW() WHERE id=?")->execute([(int)$item['id']]);
        mg_ai_plan_record_review_event($pdo, $actorId, 'merchant.ai_plan_item.executed', $item, [
            'status'=>'executed',
            'merchant_note'=>$note,
            'execution'=>$execution,
            'decided_by_user_id'=>$actorId,
            'merchant_owner_id'=>$merchantOwnerId,
        ]);
        mg_ai_plan_update_parent_status($pdo, (int)$item['plan_id']);
        $fresh = mg_ai_plan_item_owned($pdo, $actorId, $itemPublicId, false);
        mg_audit('merchant.contact_workspace_review_approved', 'ai_merchant_plan_item', [
            'plan_id'=>(string)($item['plan_public_id'] ?? ''),
            'item_id'=>$itemPublicId,
            'draft_kind'=>(string)$payload['draft_kind'],
            'crm_contact_id'=>(string)$payload['crm_contact_id'],
            'execution'=>$execution,
        ], $actorId);
        $pdo->commit();
        return mg_ai_plan_public_item($fresh, $execution);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'merchant.contact_workspace_review_failed', 'Contact Action Center approval adapter failed.', [
            'exception_class'=>$error::class,
            'item_id'=>$itemPublicId,
        ], $actorId);
        mg_fail('Unable to approve Contact Action Center draft: ' . $error->getMessage(), 500);
    }
}
