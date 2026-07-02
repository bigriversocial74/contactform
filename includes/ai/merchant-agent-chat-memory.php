<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat.php';
require_once __DIR__ . '/merchant-agent-memory-sources.php';
require_once __DIR__ . '/merchant-agent-feed-context.php';
require_once __DIR__ . '/merchant-agent-campaign-recipes.php';
require_once dirname(__DIR__) . '/merchant-agent-memory.php';
require_once dirname(__DIR__) . '/merchant-agent-policy.php';

function mg_ai_chat_memory_system_prompt(): string
{
    return mg_ai_chat_system_prompt() . "\n\n" . mg_agent_soul_prompt() . "\n\n" . mg_agent_skill_system_prompt() . "\n\nMemory, policy, creative-mode, campaign-recipe, model-routing, and output-control guidance:\n- Use merchant_agent_memory, merchant_agent_memory_sources, merchant_feed_posts, campaign_recipe_catalog, and merchant_agent_policy when recommending next steps.\n- This merchant agent chat is optimized for practical merchant marketing creative work, not deep database analysis by default.\n- Prefer saved brand voice, campaign style, customer tone, default offer type, business goals, local market notes, ready memory chunks, products, rewards, feed posts, and campaign basics.\n- Treat campaign_recipe_catalog as modular building blocks. Mix campaign types, reward types, channels, social/feed interactions, newsletters, contests, QR/drop mechanics, follow-up messages, and reward ideas based on the merchant task and available data.\n- Prefer current executable campaign types and reward types when creating review-ready drafts. Suggested campaign/reward types may be used as strategic labels in payloads until product UI support is added.\n- For campaign ideas, social posts, offer copy, SMS/email/newsletter drafts, loyalty/reward wording, contests, QR drops, flash drops, social engagement prompts, event promos, and local marketing concepts, keep the response creative, ready-to-use, and concise.\n- For campaign packages, include recommended_campaign_type, recommended_reward_type, recipe_key, channel_package, why_this_recipe, draft_artifacts, suggested reward mechanics, and review_payload.\n- Do not request or analyze heavy database metrics unless context_profile is data_analysis or the merchant explicitly asks for performance, ROI, claims, redemption, sales, conversion, reports, analytics, trends, or diagnostics.\n- When context_profile is creative_marketing or quick_copy, use the lightweight operating snapshot as background only; do not over-explain metrics.\n- Do not invent details from uploaded documents that are not available in ready chunks or summaries.\n- Avoid ideas similar to rejected or too-risky feedback.\n- Use only allowed action keys and avoid action keys listed in policy.\n- Cap card risk at the policy max risk level.\n- Respect agent_mode, output_type, approval_mode, enabled_skills, active_thread, context_profile, model_routing, and campaign_recipe_catalog exactly.\n- Return blocks for rich in-chat charts only when context_profile is data_analysis or a selected skill strongly supports it.\n- Return cards that match output_type. Action plan needs task-like cards. Message draft needs copy-ready draft cards. Review checklist needs checklist cards. Campaign idea and social_campaign need campaign cards. Admin-ready recommendation needs review-ready cards with review_action_key and review_payload.\n- If approval_mode is review_queue, make every useful card bridge-ready with review_action_key and review_payload. Do not execute anything directly.\n- If confidence is below the policy threshold, explain uncertainty and use a low-risk review-only card.\n";
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

function mg_ai_chat_creative_model_blocklist_sql(): string
{
    return "LOWER(m.model_key) NOT LIKE '%opus%' AND LOWER(m.model_key) NOT LIKE '%fable%'";
}

function mg_ai_chat_model_family(array $row): string
{
    $name = strtolower((string)($row['model_key'] ?? '') . ' ' . (string)($row['display_name'] ?? ''));
    if (str_contains($name, 'haiku')) return 'haiku';
    if (str_contains($name, 'sonnet')) return 'sonnet';
    if (str_contains($name, 'opus')) return 'opus';
    if (str_contains($name, 'fable')) return 'fable';
    return 'other';
}

function mg_ai_chat_model_route(string $contextProfile, string $outputType, string $message): array
{
    $haystack = strtolower($message . ' ' . $contextProfile . ' ' . $outputType);
    if ($contextProfile === 'data_analysis') {
        return ['task' => 'data_analysis', 'preferred_family' => 'sonnet', 'reason' => 'Deep database, analytics, ROI, claims, reports, or diagnostic request.'];
    }
    if (in_array($outputType, ['message_draft','quick_answer'], true) || preg_match('/\b(sms|caption|captions|subject line|headline|rewrite|short|quick|tagline|cta)\b/i', $haystack)) {
        return ['task' => 'quick_copy', 'preferred_family' => 'haiku', 'reason' => 'Short copy, quick answer, SMS, caption, headline, CTA, or rewrite request.'];
    }
    return ['task' => 'creative_marketing', 'preferred_family' => 'sonnet', 'reason' => 'Campaign strategy, social campaign, email, offer, reward, or local promotion request.'];
}

function mg_ai_chat_enabled_model_candidates(PDO $pdo): array
{
    $block = mg_ai_chat_creative_model_blocklist_sql();
    $stmt = $pdo->prepare("SELECT m.*, p.provider_key, p.display_name provider_name, p.env_var_name, p.enabled provider_enabled
        FROM ai_models m
        INNER JOIN ai_providers p ON p.id=m.provider_id
        WHERE p.provider_key='anthropic' AND p.enabled=1 AND m.enabled=1 AND {$block}
        ORDER BY m.is_default DESC, m.sort_order ASC, m.display_name ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $candidates = [];
    foreach ($rows as $row) {
        if (!mg_ai_env_configured((string)$row['env_var_name'])) continue;
        $row['_family'] = mg_ai_chat_model_family($row);
        $candidates[] = $row;
    }
    return $candidates;
}

function mg_ai_chat_select_merchant_model(PDO $pdo, string $preferredModelKey = '', string $contextProfile = 'creative_marketing', string $outputType = 'action_plan', string $message = ''): array
{
    $block = mg_ai_chat_creative_model_blocklist_sql();
    $route = mg_ai_chat_model_route($contextProfile, $outputType, $message);
    if ($preferredModelKey !== '') {
        $stmt = $pdo->prepare("SELECT m.*, p.provider_key, p.display_name provider_name, p.env_var_name, p.enabled provider_enabled
            FROM ai_models m
            INNER JOIN ai_providers p ON p.id=m.provider_id
            WHERE p.provider_key='anthropic' AND p.enabled=1 AND m.enabled=1 AND {$block} AND m.model_key=?
            LIMIT 1");
        $stmt->execute([$preferredModelKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && mg_ai_env_configured((string)$row['env_var_name'])) {
            $row['_family'] = mg_ai_chat_model_family($row);
            $row['_routing'] = $route + ['selected_family' => $row['_family'], 'selected_by' => 'explicit_preference'];
            return $row;
        }
    }
    $candidates = mg_ai_chat_enabled_model_candidates($pdo);
    if ($candidates === []) mg_fail('No enabled Sonnet/Haiku Claude model is available for merchant agent chat. Opus and Fable are intentionally excluded for this chat agent.', 503);
    $selected = null;
    foreach ($candidates as $candidate) {
        if (($candidate['_family'] ?? '') === ($route['preferred_family'] ?? '')) {
            $selected = $candidate;
            break;
        }
    }
    if ($selected === null) $selected = $candidates[0];
    $selected['_routing'] = $route + [
        'selected_family' => (string)($selected['_family'] ?? 'other'),
        'selected_by' => (($selected['_family'] ?? '') === ($route['preferred_family'] ?? '')) ? 'task_family_match' : 'admin_default_fallback',
        'candidate_count' => count($candidates),
    ];
    return $selected;
}

function mg_ai_chat_needs_deep_context(string $message, string $scope, string $outputType): bool
{
    if (in_array($scope, ['claims','analytics','developer_api'], true)) return true;
    if ($outputType === 'admin_recommendation') return true;
    $haystack = strtolower($message . ' ' . $scope . ' ' . $outputType);
    foreach (['analytics','analysis','analyze','metric','metrics','performance','roi','conversion','redemption','claim','claims','sales','revenue','report','trend','compare','diagnostic','database','chart','forecast','projection'] as $keyword) {
        if (str_contains($haystack, $keyword)) return true;
    }
    return false;
}

function mg_ai_chat_context_profile(string $message, string $scope, string $outputType): string
{
    if (mg_ai_chat_needs_deep_context($message, $scope, $outputType)) return 'data_analysis';
    if (in_array($outputType, ['message_draft','quick_answer'], true)) return 'quick_copy';
    if (in_array($outputType, ['campaign_idea','social_campaign'], true)) return 'creative_marketing';
    $haystack = strtolower($message);
    foreach (['caption','copy','headline','subject line','sms','email','newsletter','contest','giveaway','qr','drop','flash','engagement','post','creative','promo','promotion','campaign','offer','loyalty','reward','event','flyer','ad'] as $keyword) {
        if (str_contains($haystack, $keyword)) return 'creative_marketing';
    }
    return 'creative_marketing';
}

function mg_ai_chat_lightweight_context(array $context, string $profile): array
{
    if ($profile === 'data_analysis') return $context;
    $sections = is_array($context['sections'] ?? null) ? $context['sections'] : [];
    $keep = ['workspace','locations','agents','reward_templates','campaigns'];
    $filtered = [];
    foreach ($keep as $key) {
        if (!isset($sections[$key])) continue;
        $filtered[$key] = $sections[$key];
        if (isset($filtered[$key]['data']['items']) && is_array($filtered[$key]['data']['items'])) {
            $filtered[$key]['data']['items'] = array_slice($filtered[$key]['data']['items'], 0, 12);
        } elseif (isset($filtered[$key]['data']) && is_array($filtered[$key]['data']) && array_is_list($filtered[$key]['data'])) {
            $filtered[$key]['data'] = array_slice($filtered[$key]['data'], 0, 12);
        }
    }
    $context['sections'] = $filtered;
    $context['context_profile'] = $profile;
    $context['context_note'] = 'Lightweight creative context: database-heavy claim, wallet, contact, event, and payment sections were intentionally omitted unless the merchant asks for analysis.';
    return $context;
}

function mg_ai_chat_output_instruction(string $mode, string $outputType, string $approvalMode): array
{
    $instructions = [
        'quick_answer' => 'Return one concise answer and up to two insight cards. Use blocks only if a selected skill makes the answer clearer.',
        'action_plan' => 'Return a practical action plan with two to four task cards. Include chart or project blocks only when useful.',
        'message_draft' => 'Return a copy-ready customer or merchant message draft. Include a card with the draft text in review_payload.draft_body and action_key create_message_draft.',
        'review_checklist' => 'Return a checklist of items the merchant should review. Each card should be a checklist item with a safe review action.',
        'campaign_idea' => 'Return one campaign recipe package with recommended_campaign_type, recommended_reward_type, recipe_key, channel package, newsletter/social/SMS/QR or contest artifacts when useful, and launch steps. Keep it merchant-ready and approval-first.',
        'social_campaign' => 'Use the social_campaign_advisor skill and campaign_recipe_catalog. Return channel-specific copy, social interaction prompts, audience, CTA, offer angle, feed-post angle, suggested reward, and a review-ready campaign card.',
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
            'title' => 'Create a campaign recipe draft',
            'body' => 'Turn the merchant request into a reviewable campaign package with a suggested campaign type, reward type, channel mix, and launch steps.',
            'action_label' => 'Open campaigns',
            'action_url' => '/merchant-campaigns.php',
            'review_action_key' => 'create_campaign_draft',
            'risk_level' => 'medium',
            'review_payload' => $basePayload + ['campaign_goal' => $message, 'recipe_engine_used' => true],
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
        if (($outputType === 'campaign_idea' || $outputType === 'social_campaign') && empty($card['review_payload']['recipe_engine_used'])) {
            $payload = mg_ai_chat_json($card['review_payload'] ?? []);
            $payload['recipe_engine_used'] = true;
            $card['review_payload'] = $payload;
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
    $contextProfile = mg_ai_chat_context_profile($message, $scope, $outputType);
    $deepContext = $contextProfile === 'data_analysis';
    $effectiveDays = $deepContext ? $days : min($days, 45);
    $controlInstructions = mg_ai_chat_output_instruction($mode, $outputType, $approvalMode);
    $recipeCatalog = mg_ai_chat_campaign_recipe_prompt_context();
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $skillKeys = mg_agent_skill_keys($input['skill_keys'] ?? null);
    if ($outputType === 'social_campaign' && !in_array('social_campaign_advisor', $skillKeys, true)) $skillKeys[] = 'social_campaign_advisor';
    $model = mg_ai_chat_select_merchant_model($pdo, mg_ai_chat_clean($input['model_key'] ?? '', 120), $contextProfile, $outputType, $message);
    $modelRoute = is_array($model['_routing'] ?? null) ? $model['_routing'] : mg_ai_chat_model_route($contextProfile, $outputType, $message);
    $provider = mg_ai_merchant_provider($pdo, (int)$model['provider_id']);
    mg_ai_enforce_rate_limits($pdo, $provider, $model, $merchantId, null);
    $history = array_slice(mg_ai_chat_recent_messages($pdo, $merchantId, $deepContext ? 12 : 6, $threadId), $deepContext ? -12 : -6);
    $context = mg_ai_merchant_context($pdo, $user, ['scope' => $scope === 'overview' ? 'all' : $scope, 'days' => $effectiveDays, 'merchant_goal' => $message]);
    $context = mg_ai_chat_lightweight_context($context, $contextProfile);
    $memory = mg_agent_memory_prompt_context($pdo, $merchantId);
    $memorySources = mg_agent_memory_source_prompt_context($pdo, $merchantId);
    $feedPosts = mg_ai_chat_feed_posts_context($pdo, $merchantId, $deepContext ? 12 : 8);
    $policy = mg_agent_policy_prompt_context($pdo, $merchantId);
    $profile = mg_agent_profile($pdo, $merchantId);
    $request = [
        'model' => (string)$model['model_key'],
        'max_tokens' => max(512, min($deepContext ? 2600 : 1800, (int)($input['max_tokens'] ?? ($deepContext ? 1600 : 1200)))),
        'temperature' => $deepContext ? 0.25 : 0.55,
        'system' => mg_ai_chat_memory_system_prompt(),
        'messages' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'merchant_message' => $message,
                    'scope' => $scope,
                    'review_window_days' => $effectiveDays,
                    'agent_mode' => $mode,
                    'output_type' => $outputType,
                    'approval_mode' => $approvalMode,
                    'context_profile' => $contextProfile,
                    'deep_database_context' => $deepContext,
                    'output_controls' => $controlInstructions,
                    'campaign_recipe_catalog' => $recipeCatalog,
                    'model_policy' => 'Merchant agent chat uses task routing across admin-enabled Anthropic Sonnet/Haiku-class models. Opus and Fable are intentionally excluded from this chat agent.',
                    'model_routing' => $modelRoute,
                    'allowed_action_urls' => mg_ai_chat_allowed_links(),
                    'recent_chat_history' => $history,
                    'merchant_operating_snapshot' => $context,
                    'merchant_agent_memory' => $memory,
                    'merchant_agent_memory_sources' => $memorySources,
                    'merchant_feed_posts' => $feedPosts,
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
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, null, 'completed', $rawResponse, ['source' => 'merchant_agent_chat', 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'context_profile' => $contextProfile, 'deep_database_context' => $deepContext, 'effective_days' => $effectiveDays, 'model_policy' => 'task_router_sonnet_haiku_only', 'model_route_task' => $modelRoute['task'] ?? '', 'model_preferred_family' => $modelRoute['preferred_family'] ?? '', 'model_selected_family' => $modelRoute['selected_family'] ?? ($model['_family'] ?? ''), 'model_selected_by' => $modelRoute['selected_by'] ?? '', 'model_route_reason' => $modelRoute['reason'] ?? '', 'recipe_engine_used' => true, 'recipe_count' => count($recipeCatalog['recipes'] ?? []), 'campaign_type_count' => count($recipeCatalog['current_campaign_types'] ?? []) + count($recipeCatalog['suggested_campaign_types'] ?? []), 'reward_type_count' => count($recipeCatalog['current_reward_types'] ?? []) + count($recipeCatalog['suggested_reward_types'] ?? []), 'memory_used' => true, 'memory_sources_used' => true, 'feed_posts_used' => true, 'feed_post_count' => count($feedPosts['items'] ?? []), 'policy_used' => true, 'skills' => $skillKeys, 'thread_id' => $threadId, 'query_preview' => mg_ai_chat_clean($message, 220)]);
        $pdo->beginTransaction();
        $meta = ['scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'context_profile' => $contextProfile, 'deep_database_context' => $deepContext, 'model_routing' => $modelRoute, 'recipe_engine_used' => true, 'thread_public_id' => $threadId, 'skills' => $skillKeys, 'agent_name' => $profile['agent_name'] ?? 'Merchant Agent'];
        $userId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], $meta);
        $assistantId = mg_ai_chat_record_message($pdo, $merchantId, 'assistant', $reply, $cards, $meta + ['blocks' => $blocks, 'model' => (string)$model['model_key'], 'memory_snapshot' => $memory, 'memory_sources_snapshot' => $memorySources, 'feed_posts_snapshot' => $feedPosts, 'policy_snapshot' => $policy, 'campaign_recipe_snapshot' => $recipeCatalog]);
        $pdo->commit();
        if ($approvalMode === 'review_queue') {
            mg_ai_chat_auto_bridge_cards($pdo, $user, $assistantId, $cards);
        }
        return [
            'user_message' => ['id' => $userId, 'role' => 'user', 'body' => $message, 'cards' => [], 'blocks' => [], 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'context_profile' => $contextProfile, 'thread_public_id' => $threadId, 'created_at' => date('c')],
            'assistant_message' => ['id' => $assistantId, 'role' => 'assistant', 'body' => $reply, 'cards' => mg_ai_chat_recent_messages($pdo, $merchantId, 1, $threadId)[0]['cards'] ?? $cards, 'blocks' => $blocks, 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'context_profile' => $contextProfile, 'thread_public_id' => $threadId, 'model' => (string)$model['model_key'], 'model_routing' => $modelRoute, 'recipe_engine_used' => true, 'created_at' => date('c')],
            'state' => mg_ai_chat_public_state($pdo, $merchantId) + ['memory' => mg_agent_memory_summary($pdo, $merchantId), 'memory_sources' => mg_agent_memory_sources($pdo, $merchantId, 20), 'feed_posts' => $feedPosts, 'policy' => $policy, 'campaign_recipes' => $recipeCatalog],
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $merchantId, null, 'failed', [], ['source' => 'merchant_agent_chat', 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'context_profile' => $contextProfile, 'deep_database_context' => $deepContext, 'error' => $error->getMessage(), 'model_policy' => 'task_router_sonnet_haiku_only', 'model_route_task' => $modelRoute['task'] ?? '', 'model_preferred_family' => $modelRoute['preferred_family'] ?? '', 'model_selected_family' => $modelRoute['selected_family'] ?? ($model['_family'] ?? ''), 'model_selected_by' => $modelRoute['selected_by'] ?? '', 'recipe_engine_used' => true, 'memory_used' => true, 'memory_sources_used' => true, 'feed_posts_used' => true, 'policy_used' => true, 'skills' => $skillKeys, 'thread_id' => $threadId, 'query_preview' => mg_ai_chat_clean($message, 220)]);
        mg_security_log('error', 'merchant.agent_chat.failed', 'Merchant agent chat failed.', ['exception_class' => $error::class, 'scope' => $scope, 'mode' => $mode, 'output_type' => $outputType, 'approval_mode' => $approvalMode, 'context_profile' => $contextProfile], $merchantId);
        mg_fail('Unable to run merchant agent chat: ' . $error->getMessage(), 500);
    }
}
