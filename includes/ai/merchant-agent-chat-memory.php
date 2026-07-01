<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat.php';
require_once __DIR__ . '/merchant-agent-memory-sources.php';
require_once dirname(__DIR__) . '/merchant-agent-memory.php';
require_once dirname(__DIR__) . '/merchant-agent-policy.php';

function mg_ai_chat_memory_system_prompt(): string
{
    return mg_ai_chat_system_prompt() . "\n\n" . mg_agent_soul_prompt() . "\n\n" . mg_agent_skill_system_prompt() . "\n\nMemory, policy, and output-control guidance:\n- Use merchant_agent_memory, merchant_agent_memory_sources, and merchant_agent_policy when recommending next steps.\n- Prefer saved preferences, approved patterns, ready memory chunks, and source summaries.\n- Do not invent details from uploaded documents that are not available in ready chunks or summaries.\n- Avoid ideas similar to rejected or too-risky feedback.\n- Use only allowed action keys and avoid action keys listed in policy.\n- Cap card risk at the policy max risk level.\n- Respect agent_mode, output_type, approval_mode, enabled_skills, and active_thread exactly.\n- Return blocks for rich in-chat charts, analysis, forecasts, social campaign copy, and projects when a selected skill supports it.\n- Return cards that match output_type. Action plan needs task-like cards. Message draft needs copy-ready draft cards. Review checklist needs checklist cards. Campaign idea and social_campaign need campaign cards. Admin-ready recommendation needs review-ready cards with review_action_key and review_payload.\n- If approval_mode is review_queue, make every useful card bridge-ready with review_action_key and review_payload. Do not execute anything directly.\n- If confidence is below the policy threshold, explain uncertainty and use a low-risk review-only card.\n";
}

function mg_ai_chat_allowed_modes(): array
{
    return ['advisor','draft','review','execute_plan'];
}

function mg_ai_chat_allowed_outputs(): array
{
    return ['quick_answer','action_plan','message_draft','review_checklist','campaign_idea','social_campaign','admin_recommendation'];
}

function mg_ai_chat_allowed_approval_modes(): array
{
    return ['advisory','draft_only','review_queue'];
}

function mg_ai_chat_control_value(array $input, string $key, array $allowed, string $fallback): string
{
    $value = strtolower(mg_ai_chat_clean($input[$key] ?? $fallback, 60));
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function mg_ai_chat_output_instruction(string $mode, string $outputType, string $approvalMode): array
{
    $instructions = [
        'quick_answer' => 'Return one concise answer and up to two insight cards. Use blocks only if a selected skill makes the answer clearer.',
        'action_plan' => 'Return a practical action plan with two to four task cards. Include chart or project blocks when useful.',
        'message_draft' => 'Return a copy-ready customer or merchant message draft. Include a card with the draft text in review_payload.draft_body and action_key create_message_draft.',
        'review_checklist' => 'Return a checklist of items the merchant should review. Each card should be a checklist item with a safe review action.',
        'campaign_idea' => 'Return one campaign idea and supporting cards for audience, reward, and launch steps.',
        'social_campaign' => 'Use the social_campaign_advisor skill. Return a social_campaign or social_posts block with channel-specific copy, audience, CTA, offer angle, and a review-ready campaign card.',
        'admin_recommendation' => 'Return admin-ready recommendation cards with review_action_key, risk_level, and review_payload so they can be added to the Agent Review queue.',
    ];
    $modeText = [
        'advisor' => 'Stay advisory and explain the recommendation before any action card.',
        'draft' => 'Prefer draftable outputs and do not imply anything was sent or published.',
        'review' => 'Focus on review and approval readiness.',
        'execute_plan' => 'Create an execution plan only; all actions still require approval before execution.',
    ];
    $approvalText = [
        'advisory' => 'Do not auto-create review items. Cards may still be bridge-ready.',
        'draft_only' => 'Prefer draft cards and draft payloads. Do not auto-submit anything.',
        'review_queue' => 'Make cards ready for review queue creation with safe review_payload details.',
    ];
    return [
        'agent_mode_instruction' => $modeText[$mode] ?? $modeText['advisor'],
        'output_instruction' => $instructions[$outputType] ?? $instructions['action_plan'],
        'approval_instruction' => $approvalText[$approvalMode] ?? $approvalText['advisory'],
    ];
}

function mg_ai_chat_fallback_card(string $message, string $scope, string $outputType, string $approvalMode): array
{
    $basePayload = ['source' => 'merchant_agent_chat', 'scope' => $scope, 'merchant_request' => $message, 'approval_mode' => $approvalMode];
    return match ($outputType) {
        'message_draft' => [
            'type' => 'next_step',
            'title' => 'Draft a merchant message',
            'body' => 'Create a safe draft based on the merchant request, then review it before sending.',
            'action_label' => 'Review drafts',
            'action_url' => '/merchant-agent-messages.php',
            'review_action_key' => 'create_message_draft',
            'risk_level' => 'low',
            'review_payload' => $basePayload + ['draft_body' => $message],
        ],
        'review_checklist' => [
            'type' => 'next_step',
            'title' => 'Review workspace checklist',
            'body' => 'Open the Agent Review queue and confirm the recommended checks before taking action.',
            'action_label' => 'Open review queue',
            'action_url' => '/merchant-agent-approvals.php',
            'review_action_key' => 'create_report_snapshot',
            'risk_level' => 'low',
            'review_payload' => $basePayload + ['checklist' => ['Review campaign status', 'Review reward and claim activity', 'Confirm follow-up opportunities']],
        ],
        'campaign_idea', 'social_campaign' => [
            'type' => 'recommendation',
            'title' => 'Create a campaign draft',
            'body' => 'Turn the merchant request into a reviewable campaign draft before launch.',
            'action_label' => 'Open campaigns',
            'action_url' => '/merchant-campaigns.php',
            'review_action_key' => 'create_campaign_draft',
            'risk_level' => 'medium',
            'review_payload' => $basePayload + ['campaign_goal' => $message],
        ],
        'admin_recommendation' => [
            'type' => 'recommendation',
            'title' => 'Create admin-ready recommendation',
            'body' => 'Package this request as a review-ready recommendation for approval.',
            'action_label' => 'Open review queue',
            'action_url' => '/merchant-agent-approvals.php',
            'review_action_key' => 'create_report_snapshot',
            'risk_level' => 'medium',
            'review_payload' => $basePayload + ['recommendation' => $message],
        ],
        default => [
            'type' => 'next_step',
            'title' => 'Create an action plan',
            'body' => 'Turn this request into a reviewable action plan with the next safe step.',
            'action_label' => 'Open Agent Review',
            'action_url' => '/merchant-agent-approvals.php',
            'review_action_key' => 'create_report_snapshot',
            'risk_level' => 'low',
            'review_payload' => $basePayload + ['plan_goal' => $message],
        ],
    };
}

function mg_ai_chat_shape_cards(array $cards, string $message, string $scope, string $mode, string $outputType, string $approvalMode): array
{
    if ($cards === []) {
        $cards[] = mg_ai_chat_fallback_card($message, $scope, $outputType, $approvalMode);
    }
    $shaped = [];
    foreach ($cards as $card) {
        if (!is_array($card)) continue;
        $card['output_type'] = $outputType;
        $card['agent_mode'] = $mode;
        $card['approval_mode'] = $approvalMode;
        if ($approvalMode === 'review_queue' || $outputType === 'admin_recommendation') {
            $card['review_action_key'] = mg_ai_chat_infer_action_key($card);
            $payload = mg_ai_chat_json($card['review_payload'] ?? []);
            $card['review_payload'] = $payload + ['source' => 'merchant_agent_chat', 'output_type' => $outputType, 'agent_mode' => $mode, 'approval_mode' => $approvalMode, 'merchant_request' => $message];
        }
        if ($outputType === 'message_draft' && empty($card['review_action_key'])) {
            $card['review_action_key'] = 'create_message_draft';
        }
        if (($outputType === 'campaign_idea' || $outputType === 'social_campaign') && empty($card['review_action_key'])) {
            $card['review_action_key'] = 'create_campaign_draft';
        }
        $shaped[] = $card;
        if (count($shaped) >= 4) break;
    }
    return mg_ai_chat_normalize_cards($shaped);
}

function mg_ai_chat_auto_bridge_cards(PDO $pdo, array $user, string $assistantId, array $cards): void
{
    foreach ($cards as $index => $card) {
        if (!is_array($card) || !empty($card['review_item_id'])) continue;
        try {
            mg_ai_chat_bridge_to_review($pdo, $user, ['message_id' => $assistantId, 'card_index' => $index]);
        } catch (Throwable) {
            continue;
        }
    }
}

function mg_ai_chat_send_with_memory(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)$user['id'];
    $message = mg_ai_chat_clean($input['message'] ?? '', 2000);
    if ($message === '') mg_fail('Enter a message for the merchant agent.', 422);
    $scope = strtolower(mg_ai_chat_clean($input['scope'] ?? 'overview', 40)) ?: 'overview';
    if (!in_array($scope, mg_ai_chat_allowed_scopes(), true)) $scope = 'overview';
    $days = max(7, min(365, (int)($input['days'] ?? 90)));
    $mode = mg_ai_chat_control_value($input, 'mode', mg_ai_chat_allowed_modes(), 'advisor');
    $outputType = mg_ai_chat_control_value($input, 'output_type', mg_ai_chat_allowed_outputs(), 'action_plan');
    $approvalMode = mg_ai_chat_control_value($input, 'approval_mode', mg_ai_chat_allowed_approval_modes(), 'advisory');
    $controlInstructions = mg_ai_chat_output_instruction($mode, $outputType, $approvalMode);
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $skillKeys = mg_agent_skill_keys($input['skill_keys'] ?? null);
    if ($outputType === 'social_campaign' && !in_array('social_campaign_advisor', $skillKeys, true)) $skillKeys[] = 'social_campaign_advisor';
    $model = mg_ai_merchant_find_anthropic_model($pdo, null);
    $provider = mg_ai_merchant_provider($pdo, (int)$model['provider_id']);
    mg_ai_enforce_rate_limits($pdo, $provider, $model, $merchantId, null);
    $history = array_slice(mg_ai_chat_recent_messages($pdo, $merchantId, 12, $threadId), -12);
    $context = mg_ai_merchant_context($pdo, $user, ['scope' => $scope === 'overview' ? 'all' : $scope, 'days' => $days, 'merchant_goal' => $message]);
    $memory = mg_agent_memory_prompt_context($pdo, $merchantId);
    $memorySources = mg_agent_memory_source_prompt_context($pdo, $merchantId);
    $policy = mg_agent_policy_prompt_context($pdo, $merchantId);
    $profile = mg_agent_profile($pdo, $merchantId);
    $request = [
        'model' => (string)$model['model_key'],
        'max_tokens' => max(512, min(2600, (int)($input['max_tokens'] ?? 1600))),
        'temperature' => 0.25,
        'system' => mg_ai_chat_memory_system_prompt(),
        'messages' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'merchant_message' => $message,
                    'scope' => $scope,
                    'review_window_days' => $days,
                    'agent_mode' => $mode,
                    'output_type' => $outputType,
                    'approval_mode' => $approvalMode,
                    'output_controls' => $controlInstructions,
                    'allowed_action_urls' => mg_ai_chat_allowed_links(),
                    'recent_chat_history' => $history,
                    'merchant_operating_snapshot' => $context,
                    'merchant_agent_memory' => $memory,
                    'merchant_agent_memory_sources' => $memorySources,
                    'merchant_agent_policy' => $policy,
                    'agent_profile' => $profile,
                    'active_thread' => $thread,
                    'enabled_skills' => mg_agent_skill_prompt_context($skillKeys),
                    'bridge_instruction' => 'When useful, include review_action_key and review_payload so the merchant can send a card to the Agent Review Queue. If approval_mode is review_queue, every useful card should be review-ready.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]],
        ]],
    ];
    try {
        $rawResponse = mg_anthropic_messages($request);
        $text = mg_anthropic_text_from_response($rawResponse);
        try { $decoded = mg_anthropic_extract_json_object($text); } catch (Throwable) { $decoded = ['reply' => $text, 'cards' => [], 'blocks' => []]; }
        $reply = mg_ai_chat_clean($decoded['reply'] ?? $text, 6000);
        if ($reply === '') $reply = 'I reviewed the merchant workspace and created the safest reviewable next step.';
        $cards = mg_ai_chat_shape_cards(mg_ai_chat_normalize_cards($decoded['cards'] ?? []), $message, $scope, $mode, $outputType, $approvalMode);
        $blocks = mg_agent_chat_normalize_blocks($decoded['blocks'] ?? []);
        if ($blocks === []) $blocks = mg_agent_skill_fallback_blocks($message, $skillKeys, $context);
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, null, 'completed', $rawResponse, ['source' => 'merchant_agent_chat', 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'memory_used' => true, 'memory_sources_used' => true, 'policy_used' => true, 'skills' => $skillKeys, 'thread_id' => $threadId]);
        $pdo->beginTransaction();
        $meta = ['scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'thread_public_id' => $threadId, 'skills' => $skillKeys, 'agent_name' => $profile['agent_name'] ?? 'Merchant Agent'];
        $userId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], $meta);
        $assistantId = mg_ai_chat_record_message($pdo, $merchantId, 'assistant', $reply, $cards, $meta + ['blocks' => $blocks, 'model' => (string)$model['model_key'], 'memory_snapshot' => $memory, 'memory_sources_snapshot' => $memorySources, 'policy_snapshot' => $policy]);
        $pdo->commit();
        if ($approvalMode === 'review_queue') {
            mg_ai_chat_auto_bridge_cards($pdo, $user, $assistantId, $cards);
        }
        return [
            'user_message' => ['id' => $userId, 'role' => 'user', 'body' => $message, 'cards' => [], 'blocks' => [], 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'thread_public_id' => $threadId, 'created_at' => date('c')],
            'assistant_message' => ['id' => $assistantId, 'role' => 'assistant', 'body' => $reply, 'cards' => mg_ai_chat_recent_messages($pdo, $merchantId, 1, $threadId)[0]['cards'] ?? $cards, 'blocks' => $blocks, 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'thread_public_id' => $threadId, 'model' => (string)$model['model_key'], 'created_at' => date('c')],
            'state' => mg_ai_chat_public_state($pdo, $merchantId) + ['memory' => mg_agent_memory_summary($pdo, $merchantId), 'memory_sources' => mg_agent_memory_sources($pdo, $merchantId, 20), 'policy' => $policy],
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, null, 'failed', [], ['source' => 'merchant_agent_chat', 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'error' => $error->getMessage(), 'memory_used' => true, 'memory_sources_used' => true, 'policy_used' => true, 'skills' => $skillKeys, 'thread_id' => $threadId]);
        mg_security_log('error', 'merchant.agent_chat.failed', 'Merchant agent chat failed.', ['exception_class' => $error::class, 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode], $merchantId);
        mg_fail('Unable to run merchant agent chat: ' . $error->getMessage(), 500);
    }
}
