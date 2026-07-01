<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat.php';

function mg_ai_chat_creative_draft_types(): array
{
    return [
        'social' => ['label' => 'Social Draft', 'action_key' => 'create_campaign_draft', 'action_url' => '/merchant-campaigns.php', 'target_type' => 'agent_social_draft'],
        'sms' => ['label' => 'SMS Draft', 'action_key' => 'create_message_draft', 'action_url' => '/merchant-agent-messages.php', 'target_type' => 'agent_sms_draft'],
        'email' => ['label' => 'Email Draft', 'action_key' => 'create_message_draft', 'action_url' => '/merchant-agent-messages.php', 'target_type' => 'agent_email_draft'],
        'campaign' => ['label' => 'Campaign Draft', 'action_key' => 'create_campaign_draft', 'action_url' => '/merchant-campaigns.php', 'target_type' => 'agent_campaign_draft'],
        'reward' => ['label' => 'Reward Copy Draft', 'action_key' => 'create_reward_template_draft', 'action_url' => '/merchant-reward-templates.php', 'target_type' => 'agent_reward_copy_draft'],
    ];
}

function mg_ai_chat_save_creative_draft(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)$user['id'];
    $messageId = mg_ai_chat_clean($input['message_id'] ?? '', 80);
    $draftType = strtolower(mg_ai_chat_clean($input['draft_type'] ?? '', 40));
    $cardIndex = isset($input['card_index']) ? (int)$input['card_index'] : -1;
    $types = mg_ai_chat_creative_draft_types();
    if ($messageId === '') mg_fail('Select an agent response to save as a draft.', 422);
    if (!isset($types[$draftType])) mg_fail('Select a valid creative draft type.', 422);
    $typeInfo = $types[$draftType];

    $stmt = $pdo->prepare("SELECT id,public_id,event_context_json FROM campaign_events WHERE merchant_user_id=? AND public_id=? AND event_type='merchant.agent_chat.assistant' LIMIT 1");
    $stmt->execute([$merchantId, $messageId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($event)) mg_fail('Agent chat message was not found.', 404);

    $ctx = mg_ai_chat_json($event['event_context_json'] ?? null);
    $cards = mg_ai_chat_normalize_cards($ctx['cards'] ?? []);
    $blocks = mg_agent_chat_normalize_blocks($ctx['blocks'] ?? []);
    $card = [];
    if ($cardIndex >= 0) {
        if (!isset($cards[$cardIndex])) mg_fail('Agent response card was not found.', 404);
        $card = $cards[$cardIndex];
    }

    $draftKey = $draftType . ':' . max(-1, $cardIndex);
    $existing = is_array($ctx['creative_drafts'] ?? null) ? $ctx['creative_drafts'] : [];
    if (!empty($existing[$draftKey]['item_id'])) {
        return ['draft' => $existing[$draftKey], 'state' => mg_ai_chat_public_state($pdo, $merchantId), 'already_saved' => true];
    }

    $assistantBody = mg_ai_chat_clean($ctx['body'] ?? '', 6000);
    $cardTitle = mg_ai_chat_clean($card['title'] ?? '', 180);
    $cardBody = mg_ai_chat_clean($card['body'] ?? '', 3000);
    $draftTitle = $cardTitle !== '' ? $cardTitle : (string)$typeInfo['label'];
    $draftBody = $cardBody !== '' ? $cardBody : $assistantBody;
    if ($draftBody === '') {
        $parts = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) continue;
            $parts[] = trim((string)($block['title'] ?? '') . "\n" . (string)($block['body'] ?? ''));
            foreach (($block['posts'] ?? []) as $post) {
                if (is_array($post)) $parts[] = trim((string)($post['channel'] ?? 'Social') . ': ' . (string)($post['copy'] ?? ''));
            }
        }
        $draftBody = mg_ai_chat_clean(implode("\n\n", array_filter($parts)), 6000);
    }

    $payload = [
        'source' => 'merchant_agent_chat_creative_draft',
        'draft_type' => $draftType,
        'draft_label' => (string)$typeInfo['label'],
        'draft_title' => $draftTitle,
        'draft_body' => $draftBody,
        'message_channel' => in_array($draftType, ['sms','email','social'], true) ? $draftType : '',
        'source_chat_message_id' => $messageId,
        'source_card_index' => $cardIndex,
        'source_agent_reply' => $assistantBody,
        'source_card' => $card,
        'source_cards' => $cards,
        'source_blocks' => $blocks,
        'action_url' => (string)$typeInfo['action_url'],
        'review_note' => 'Saved from Merchant Agent Chat for merchant review before publishing or sending.',
    ];
    $title = mg_ai_chat_clean((string)$typeInfo['label'] . ': ' . $draftTitle, 180);
    $reason = mg_ai_chat_clean($draftBody, 1000);
    if ($reason === '') $reason = 'Creative draft saved from Merchant Agent Chat.';
    $scope = mg_ai_chat_clean($ctx['scope'] ?? 'campaigns', 40) ?: 'campaigns';
    $model = mg_ai_chat_catalog_model($pdo, mg_ai_chat_clean($ctx['model'] ?? '', 120));

    try {
        $pdo->beginTransaction();
        $planPublicId = mg_ai_chat_uuid();
        $itemPublicId = mg_ai_chat_uuid();
        $contextJson = json_encode(['source' => 'merchant_agent_chat_creative_draft', 'chat_message_id' => $messageId, 'card_index' => $cardIndex, 'draft_type' => $draftType], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fingerprint = hash('sha256', $messageId . '|' . $cardIndex . '|' . $draftType . '|' . $title);
        $stmt = $pdo->prepare('INSERT INTO ai_merchant_plans (public_id,merchant_user_id,agent_id,provider_id,model_id,scope,merchant_goal,status,priority,summary,prompt_fingerprint,input_context_json,raw_response_json,input_tokens,output_tokens,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$planPublicId, $merchantId, null, (int)$model['provider_id'], (int)$model['id'], $scope === 'overview' ? 'all' : $scope, $title, 'review_ready', 'medium', $reason, $fingerprint, $contextJson, json_encode(['source' => 'merchant_agent_chat_creative_draft'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 0, $merchantId]);
        $planId = (int)$pdo->lastInsertId();
        $sql = "INSERT INTO ai_merchant_plan_items (public_id,plan_id,sequence_no,action_key,target_type,target_reference,risk_level,requires_approval,confidence,title,reason,suggested_payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$itemPublicId, $planId, 1, (string)$typeInfo['action_key'], (string)$typeInfo['target_type'], $messageId, 'low', 1, 0.85, $title, $reason, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'recommended']);
        $draftRecord = ['draft_type' => $draftType, 'label' => (string)$typeInfo['label'], 'plan_id' => $planPublicId, 'item_id' => $itemPublicId, 'action_key' => (string)$typeInfo['action_key'], 'created_at' => date('c')];
        $existing[$draftKey] = $draftRecord;
        $ctx['creative_drafts'] = $existing;
        $pdo->prepare('UPDATE campaign_events SET event_context_json=? WHERE id=? LIMIT 1')->execute([json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$event['id']]);
        if (function_exists('mg_audit')) mg_audit('merchant.agent_chat_creative_draft_saved', $merchantId, ['plan_id' => $planPublicId, 'item_id' => $itemPublicId, 'draft_type' => $draftType, 'action_key' => (string)$typeInfo['action_key']]);
        if (function_exists('mg_event')) mg_event($merchantId, 'merchant.agent_chat.creative_draft_saved', ['plan_id' => $planPublicId, 'item_id' => $itemPublicId, 'draft_type' => $draftType, 'action_key' => (string)$typeInfo['action_key']]);
        $pdo->commit();
        return ['draft' => $draftRecord, 'state' => mg_ai_chat_public_state($pdo, $merchantId)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to save creative draft: ' . $error->getMessage(), 500);
    }
}
