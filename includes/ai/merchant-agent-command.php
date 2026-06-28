<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat.php';

function mg_agent_cmd_is_super_admin(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('super_admin', $roles, true);
}

function mg_agent_cmd_event(PDO $pdo, int $merchantId, string $type, array $ctx): string
{
    $id = mg_ai_chat_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$id, $merchantId, null, null, $type, json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return $id;
}

function mg_agent_cmd_latest_event(PDO $pdo, int $merchantId, string $type): array
{
    $stmt = $pdo->prepare('SELECT event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$merchantId, $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return [];
    $ctx = mg_ai_chat_json($row['event_context_json'] ?? null);
    $ctx['created_at'] = $row['created_at'] ?? null;
    return $ctx;
}

function mg_agent_cmd_mode_catalog(): array
{
    return [
        ['key' => 'overview', 'label' => 'Mission Control', 'scope' => 'overview', 'placeholder' => 'Ask for the best next move across the merchant account...', 'prompts' => ['Generate today\'s mission brief.', 'What needs review first?', 'Show the highest-impact next step.']],
        ['key' => 'campaigns', 'label' => 'Campaign Agent', 'scope' => 'campaigns', 'placeholder' => 'Ask about campaigns, contests, QR drops, or launch ideas...', 'prompts' => ['Review campaign health.', 'Create a 3-part campaign plan.', 'Find a stalled campaign.']],
        ['key' => 'rewards', 'label' => 'Reward Agent', 'scope' => 'rewards', 'placeholder' => 'Ask about reward templates, offers, and readiness...', 'prompts' => ['Score reward readiness.', 'Suggest a better reward offer.', 'Find reward template gaps.']],
        ['key' => 'crm', 'label' => 'CRM Agent', 'scope' => 'crm', 'placeholder' => 'Ask about follow-ups, customers, and retention...', 'prompts' => ['Find CRM follow-up opportunities.', 'Create a follow-up plan.', 'Find high-intent contacts.']],
        ['key' => 'claims', 'label' => 'Claims Agent', 'scope' => 'claims', 'placeholder' => 'Ask about claim activity, verification, and operations...', 'prompts' => ['Review claim operations.', 'Find claim follow-up needs.', 'Create an operations checklist.']],
        ['key' => 'analytics', 'label' => 'Analytics Agent', 'scope' => 'analytics', 'placeholder' => 'Ask about trends, scores, and reporting...', 'prompts' => ['Build an analytics brief.', 'Score campaign health.', 'What metric should I watch?']],
        ['key' => 'developer_api', 'label' => 'Developer API Agent', 'scope' => 'developer_api', 'placeholder' => 'Ask about integrations, distribution API, and developer workflows...', 'prompts' => ['Review API opportunities.', 'Create an integration checklist.', 'Find developer launch gaps.']],
    ];
}

function mg_agent_cmd_demo_state(): array
{
    return [
        'enabled' => true,
        'label' => 'Demo Merchant',
        'mission' => ['Launch a referral campaign', 'Improve reward readiness', 'Create CRM follow-ups'],
        'health_scores' => [
            ['label' => 'Campaign Health', 'score' => 72],
            ['label' => 'Reward Readiness', 'score' => 81],
            ['label' => 'CRM Follow-Up Strength', 'score' => 44],
            ['label' => 'Claims Operations', 'score' => 90],
        ],
        'timeline' => [
            ['title' => 'Agent reviewed campaign performance', 'status' => 'insight', 'time' => 'Today'],
            ['title' => 'Merchant sent reward idea to review', 'status' => 'review', 'time' => 'Today'],
            ['title' => 'Draft follow-up task prepared', 'status' => 'draft', 'time' => 'Yesterday'],
        ],
        'notifications' => [
            ['title' => 'Campaign issue detected', 'body' => 'Referral CTA is weak; review recommended.', 'level' => 'medium'],
            ['title' => 'CRM opportunity', 'body' => '12 contacts are ready for follow-up.', 'level' => 'low'],
        ],
    ];
}

function mg_agent_cmd_health_scores(PDO $pdo, int $merchantId): array
{
    $pending = 0;
    $executed = 0;
    $chat = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND i.status IN ('recommended','deferred','failed')");
        $stmt->execute([$merchantId]);
        $pending = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND i.status='executed'");
        $stmt->execute([$merchantId]);
        $executed = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant')");
        $stmt->execute([$merchantId]);
        $chat = (int) $stmt->fetchColumn();
    } catch (Throwable) {}
    return [
        ['label' => 'Campaign Health', 'score' => max(35, min(94, 70 + $executed * 3 - $pending * 2))],
        ['label' => 'Reward Readiness', 'score' => max(35, min(96, 76 + $executed * 2 - $pending))],
        ['label' => 'CRM Follow-Up Strength', 'score' => max(25, min(92, 54 + $chat - $pending * 2))],
        ['label' => 'Claims Operations', 'score' => max(45, min(98, 86 - $pending))],
    ];
}

function mg_agent_cmd_timeline(PDO $pdo, int $merchantId): array
{
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type LIKE 'merchant.agent_%' ORDER BY id DESC LIMIT 8");
        $stmt->execute([$merchantId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ctx = mg_ai_chat_json($row['event_context_json'] ?? null);
            $rows[] = ['title' => (string) ($ctx['title'] ?? $ctx['body'] ?? $row['event_type']), 'status' => (string) $row['event_type'], 'time' => $row['created_at'] ?? null];
        }
    } catch (Throwable) {}
    return $rows;
}

function mg_agent_cmd_state(PDO $pdo, array $user, bool $demo = false): array
{
    if ($demo && !mg_agent_cmd_is_super_admin($user)) mg_fail('Demo data is available to super admins only.', 403);
    $merchantId = (int) $user['id'];
    $goals = mg_agent_cmd_latest_event($pdo, $merchantId, 'merchant.agent_goals.saved');
    $live = [
        'can_demo' => mg_agent_cmd_is_super_admin($user),
        'demo_mode' => $demo,
        'modes' => mg_agent_cmd_mode_catalog(),
        'goals' => $goals ?: ['primary_goal' => '', 'secondary_goal' => '', 'focus' => '', 'tone' => 'Friendly/local', 'budget' => 'Low-cost campaigns'],
        'health_scores' => mg_agent_cmd_health_scores($pdo, $merchantId),
        'timeline' => mg_agent_cmd_timeline($pdo, $merchantId),
        'notifications' => [],
    ];
    return $demo ? array_merge($live, ['demo' => mg_agent_cmd_demo_state()]) : $live;
}

function mg_agent_cmd_save_goals(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int) $user['id'];
    $goals = [
        'primary_goal' => mg_ai_chat_clean($input['primary_goal'] ?? '', 180),
        'secondary_goal' => mg_ai_chat_clean($input['secondary_goal'] ?? '', 180),
        'focus' => mg_ai_chat_clean($input['focus'] ?? '', 160),
        'tone' => mg_ai_chat_clean($input['tone'] ?? 'Friendly/local', 80),
        'budget' => mg_ai_chat_clean($input['budget'] ?? 'Low-cost campaigns', 100),
    ];
    mg_agent_cmd_event($pdo, $merchantId, 'merchant.agent_goals.saved', $goals);
    return ['goals' => $goals, 'state' => mg_agent_cmd_state($pdo, $user, false)];
}

function mg_agent_cmd_package_payload(string $actionKey, string $packageTitle, string $itemTitle, string $reason): array
{
    $base = ['source' => 'agent_draft_package', 'package_title' => $packageTitle, 'title' => $itemTitle, 'reason' => $reason];
    if ($actionKey === 'create_campaign_draft') {
        return array_merge($base, [
            'campaign_type' => 'referral_reward',
            'description' => 'Agent-created campaign draft package for merchant review.',
            'form_headline' => $itemTitle,
            'form_description' => 'Join this local campaign and claim the reward after merchant approval.',
            'success_message' => 'Thanks — your reward request has been prepared.',
            'quantity_limit' => 100,
            'per_user_limit' => 1,
            'agent_discoverable' => true,
        ]);
    }
    if ($actionKey === 'create_reward_template_draft') {
        return array_merge($base, [
            'reward_type' => 'discount',
            'value_type' => 'percent',
            'value_percent' => 10,
            'description' => 'Agent-created reward template draft for campaign testing.',
            'redemption_instructions' => 'Merchant reviews and edits redemption terms before launch.',
            'expiration_rule' => 'after_claim',
            'expiration_days' => 30,
            'per_user_limit' => 1,
            'agent_discoverable' => true,
            'agent_summary' => 'A low-risk draft reward for local customer engagement.',
            'agent_categories' => ['local', 'referral', 'engagement'],
            'agent_use_cases' => ['campaign test', 'CRM follow-up'],
            'agent_add_to_wallet_allowed' => true,
            'agent_gift_send_allowed' => true,
        ]);
    }
    return array_merge($base, [
        'recommended_next_action' => 'Create a CRM follow-up task for merchant review.',
        'task_title' => $itemTitle,
        'due_in_days' => 3,
        'priority' => 'medium',
        'followup_type' => 'campaign_package',
    ]);
}

function mg_agent_cmd_create_package(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int) $user['id'];
    $title = mg_ai_chat_clean($input['title'] ?? 'Agent draft package', 180);
    $scope = mg_ai_chat_clean($input['scope'] ?? 'campaigns', 40) ?: 'campaigns';
    $model = mg_ai_chat_catalog_model($pdo, '');
    $items = [
        ['create_campaign_draft', 'Campaign draft', 'Create a campaign draft for merchant review.'],
        ['create_reward_template_draft', 'Reward template draft', 'Create a reward template draft for merchant review.'],
        ['create_crm_followup_task', 'CRM follow-up task', 'Create a customer follow-up task for merchant review.'],
    ];
    try {
        $pdo->beginTransaction();
        $planPublicId = mg_ai_chat_uuid();
        $stmt = $pdo->prepare('INSERT INTO ai_merchant_plans (public_id,merchant_user_id,agent_id,provider_id,model_id,scope,merchant_goal,status,priority,summary,prompt_fingerprint,input_context_json,raw_response_json,input_tokens,output_tokens,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$planPublicId, $merchantId, null, (int) $model['provider_id'], (int) $model['id'], $scope, $title, 'review_ready', 'medium', $title, hash('sha256', $title . microtime(true)), json_encode(['source' => 'agent_draft_package'], JSON_UNESCAPED_SLASHES), json_encode(['source' => 'agent_draft_package'], JSON_UNESCAPED_SLASHES), 0, 0, $merchantId]);
        $planId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare('INSERT INTO ai_merchant_plan_items (public_id,plan_id,sequence_no,action_key,target_type,target_reference,risk_level,requires_approval,confidence,title,reason,suggested_payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'recommended\',NOW(),NOW())');
        $created = [];
        foreach ($items as $idx => $item) {
            $itemId = mg_ai_chat_uuid();
            $payload = mg_agent_cmd_package_payload($item[0], $title, $item[1], $item[2]);
            $itemStmt->execute([$itemId, $planId, $idx + 1, $item[0], 'agent_package', $planPublicId, 'low', 1, 0.82, $item[1], $item[2], json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $created[] = ['id' => $itemId, 'title' => $item[1], 'action_key' => $item[0]];
        }
        mg_agent_cmd_event($pdo, $merchantId, 'merchant.agent_package.created', ['title' => $title, 'plan_id' => $planPublicId, 'items' => $created]);
        $pdo->commit();
        return ['plan_id' => $planPublicId, 'items' => $created, 'state' => mg_agent_cmd_state($pdo, $user, false)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to create draft package: ' . $error->getMessage(), 500);
    }
}

function mg_agent_cmd_daily_briefing(PDO $pdo, array $user, array $input): array
{
    $brief = mg_ai_chat_send($pdo, $user, ['message' => 'Generate a daily merchant briefing with three priorities, two opportunities, and one review item.', 'scope' => 'overview', 'days' => (int) ($input['days'] ?? 90)]);
    mg_agent_cmd_event($pdo, (int) $user['id'], 'merchant.agent_briefing.created', ['title' => 'Daily Agent Briefing', 'message_id' => $brief['assistant_message']['id'] ?? null]);
    return $brief;
}
