<?php
declare(strict_types=1);

require_once __DIR__ . '/personal-gifting-agent.php';
require_once __DIR__ . '/task-agent-context.php';
require_once __DIR__ . '/task-agent-memory.php';
require_once __DIR__ . '/task-agent-shortlist.php';
require_once __DIR__ . '/task-agent-plan-selection.php';
require_once __DIR__ . '/task-agent-delivery-preparation.php';
require_once __DIR__ . '/task-agent-intent-router.php';
require_once __DIR__ . '/task-agent-shortlist-router.php';
require_once __DIR__ . '/task-agent-plan-selection-router.php';
require_once __DIR__ . '/task-agent-delivery-router.php';
require_once __DIR__ . '/task-agent-ai-synthesis.php';
require_once dirname(__DIR__) . '/api/agents/_agent.php';

function mg_multi_agent_runtime_require_schema(PDO $pdo): void
{
    foreach (['multi_agent_threads','multi_agent_messages','multi_agent_memory','multi_agent_onboarding','multi_agent_drafts'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if (!(bool)$stmt->fetchColumn()) {
            throw new RuntimeException('Multi-agent runtime migration is required.');
        }
    }
}

function mg_multi_agent_runtime_template(array $agent): array
{
    $config = json_decode((string)($agent['config_json'] ?? ''), true);
    $key = is_array($config) ? (string)($config['template_key'] ?? '') : '';
    $templates = function_exists('mg_multi_agent_templates') ? mg_multi_agent_templates() : [];
    $template = is_array($templates[$key] ?? null) ? $templates[$key] : [];
    $template['key'] = $key;
    return $template;
}

function mg_multi_agent_runtime_thread(PDO $pdo, array $agent, int $userId, string $publicId = ''): array
{
    if ($publicId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM multi_agent_threads WHERE public_id=? AND agent_id=? AND owner_user_id=? AND status='active' LIMIT 1");
        $stmt->execute([$publicId, (int)$agent['id'], $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    $stmt = $pdo->prepare("SELECT * FROM multi_agent_threads WHERE agent_id=? AND owner_user_id=? AND status='active' ORDER BY id LIMIT 1");
    $stmt->execute([(int)$agent['id'], $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO multi_agent_threads(public_id,agent_id,owner_user_id,title,status,created_at,updated_at) VALUES (?,?,?,'Agent conversation','active',NOW(),NOW())")
        ->execute([$publicId, (int)$agent['id'], $userId]);
    $stmt = $pdo->prepare('SELECT * FROM multi_agent_threads WHERE id=?');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return (array)$stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_multi_agent_runtime_messages(PDO $pdo, int $userId, int $agentId, int $threadId, int $limit = 60): array
{
    $stmt = $pdo->prepare(
        'SELECT public_id,role,body,cards_json,context_json,model_key,created_at '
        . 'FROM multi_agent_messages WHERE owner_user_id=? AND agent_id=? AND thread_id=? '
        . 'ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
    );
    $stmt->execute([$userId, $agentId, $threadId]);

    return array_map(static function (array $row): array {
        $row['cards'] = $row['cards_json'] ? json_decode((string)$row['cards_json'], true) : [];
        $row['context'] = $row['context_json'] ? json_decode((string)$row['context_json'], true) : [];
        unset($row['cards_json'], $row['context_json']);
        return $row;
    }, array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)));
}

function mg_multi_agent_runtime_store(
    PDO $pdo,
    int $userId,
    int $agentId,
    int $threadId,
    string $role,
    string $body,
    array $cards = [],
    array $context = [],
    string $model = ''
): array {
    $publicId = mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO multi_agent_messages(public_id,thread_id,agent_id,owner_user_id,role,body,cards_json,context_json,model_key,created_at) '
        . 'VALUES (?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $publicId,
        $threadId,
        $agentId,
        $userId,
        $role,
        $body,
        $cards ? json_encode($cards, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        $model ?: null,
    ]);

    $pdo->prepare('UPDATE multi_agent_threads SET last_message_at=NOW(),updated_at=NOW() WHERE id=? AND owner_user_id=?')
        ->execute([$threadId, $userId]);

    return [
        'public_id' => $publicId,
        'role' => $role,
        'body' => $body,
        'cards' => $cards,
        'context' => $context,
        'model_key' => $model,
        'created_at' => gmdate('Y-m-d H:i:s'),
    ];
}

function mg_multi_agent_runtime_memory(PDO $pdo, int $userId, int $agentId): array
{
    return mg_task_agent_memory_list($pdo, $userId, $agentId, 50);
}

function mg_multi_agent_runtime_onboarding(PDO $pdo, int $userId, int $agentId): array
{
    $stmt = $pdo->prepare('SELECT status,current_step,answers_json,completed_at,updated_at FROM multi_agent_onboarding WHERE owner_user_id=? AND agent_id=? LIMIT 1');
    $stmt->execute([$userId, $agentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['status'=>'not_started','current_step'=>null,'answers'=>[],'completed_at'=>null];
    $row['answers'] = $row['answers_json'] ? json_decode((string)$row['answers_json'], true) : [];
    unset($row['answers_json']);
    return $row;
}

function mg_multi_agent_runtime_context(PDO $pdo, int $userId, array $agent, array $template): array
{
    $agentId = (int)$agent['id'];
    $memory = mg_task_agent_memory_list($pdo, $userId, $agentId, 50);
    $shortlist = mg_task_agent_shortlist_list($pdo, $userId, $agentId, 20);
    $selections = mg_task_agent_plan_selections($pdo, $userId, $agentId, 20);
    $delivery = mg_task_agent_delivery_preparations($pdo, $userId, $agentId, 20);

    $context = [
        'agent' => [
            'id' => (string)$agent['public_id'],
            'name' => (string)$agent['name'],
            'template_key' => (string)($template['key'] ?? ''),
        ],
        'memory' => $memory,
        'memory_for_model' => mg_task_agent_memory_for_model($memory),
        'shortlist' => $shortlist,
        'shortlist_for_model' => mg_task_agent_shortlist_for_model($shortlist),
        'plan_selections' => $selections,
        'plan_selections_for_model' => mg_task_agent_plan_selection_for_model($selections),
        'delivery_preparations' => $delivery,
        'delivery_preparations_for_model' => mg_task_agent_delivery_for_model($delivery),
        'delivery_schema_ready' => mg_task_agent_delivery_schema_ready($pdo),
        'onboarding' => mg_multi_agent_runtime_onboarding($pdo, $userId, $agentId),
    ];

    if (($template['key'] ?? '') === 'birthday_occasion') {
        $snapshot = mg_task_agent_context_snapshot($pdo, $userId, 90);
        $snapshot['summary']['shortlisted_products'] = count($shortlist);
        $snapshot['summary']['selected_plan_products'] = count($selections);
        $snapshot['summary']['delivery_preparations'] = count($delivery);
        $context['system_snapshot'] = $snapshot;
        $context['upcoming_dates'] = $snapshot['upcoming'];
        $context['active_plans'] = $snapshot['plans'];
        $context['scheduled_reminders'] = $snapshot['reminders'];
    }

    return $context;
}

function mg_multi_agent_runtime_model_context(array $context, string $message = ''): array
{
    return array_merge(
        mg_task_agent_model_context($message, $context),
        mg_task_agent_shortlist_model_context($context),
        mg_task_agent_plan_selection_model_context($context),
        mg_task_agent_delivery_model_context($context)
    );
}

function mg_multi_agent_runtime_fallback(array $agent, array $template, string $message, array $context): array
{
    if (($template['key'] ?? '') === 'birthday_occasion') {
        $next = ($context['upcoming_dates'] ?? [])[0] ?? null;
        $reply = 'I can search local gifts, manage a shortlist, attach a reviewed product to a gift plan, prepare a cart handoff, create recipient permission requests, and manage prepare-only send-later checkpoints without using AI.';
        if (is_array($next)) {
            $reply .= ' Your next saved occasion is ' . (string)($next['label'] ?? 'an occasion')
                . ' on ' . (string)($next['event_date'] ?? 'the saved date') . '.';
        }
        return [
            'reply' => $reply,
            'cards' => [[
                'type' => 'question',
                'title' => 'Choose a deterministic action',
                'body' => 'Name a recipient, shortlisted product, editable gift plan, or send-later preparation.',
                'action' => 'seed_prompt',
                'prompt' => 'Show my delivery preparation status.',
                'review_payload' => [],
            ]],
        ];
    }

    return [
        'reply' => 'Add the recipient, goal, budget, timing, or merchant context needed for a reviewable plan.',
        'cards' => [],
    ];
}

function mg_multi_agent_runtime_chat(PDO $pdo, int $userId, array $agent, array $input): array
{
    mg_multi_agent_runtime_require_schema($pdo);
    if (($agent['lifecycle_status'] ?? '') !== 'active') throw new RuntimeException('Agent is not active.');
    if (($agent['runtime_status'] ?? '') === 'paused') throw new RuntimeException('Agent is paused. Resume it before chatting.');

    $message = mg_personal_agent_text($input['message'] ?? '', 3000);
    if ($message === '') throw new InvalidArgumentException('Enter a message for this agent.');

    $thread = mg_multi_agent_runtime_thread(
        $pdo,
        $agent,
        $userId,
        mg_personal_agent_text($input['thread_id'] ?? '', 80)
    );
    $template = mg_multi_agent_runtime_template($agent);
    $context = mg_multi_agent_runtime_context($pdo, $userId, $agent, $template);
    $userMessage = mg_multi_agent_runtime_store(
        $pdo,
        $userId,
        (int)$agent['id'],
        (int)$thread['id'],
        'user',
        $message,
        [],
        $context
    );
    $history = mg_multi_agent_runtime_messages($pdo, $userId, (int)$agent['id'], (int)$thread['id'], 8);

    $route = mg_task_agent_delivery_route($message, $context, $template)
        ?? mg_task_agent_plan_selection_route($message, $context, $template)
        ?? mg_task_agent_shortlist_route($message, $context, $template)
        ?? mg_task_agent_route($message, $context, $template);

    $result = is_array($route['result'] ?? null) ? $route['result'] : null;
    $responseSource = (string)($route['response_source'] ?? 'safe_fallback');
    $aiReason = (string)($route['ai_reason'] ?? '');
    $tool = (string)($route['tool'] ?? '');
    $toolInput = is_array($route['tool_input'] ?? null) ? $route['tool_input'] : [];
    $modelKey = '';
    $creditAfter = null;
    $tokens = ['input'=>0,'output'=>0,'total'=>0];

    if (!$result && $tool === 'discover_products') {
        $result = mg_task_agent_discover_products($pdo, $userId, (int)$agent['id'], $toolInput);
        $responseSource = 'system_query';
        $aiReason = '';
    }

    if (!$result && $aiReason !== '') {
        try {
            $synthesis = mg_task_agent_ai_synthesis(
                $pdo,
                $userId,
                $agent,
                $template,
                $context,
                $history,
                $message,
                $aiReason,
                mg_personal_agent_text($input['model_id'] ?? '', 80),
                (string)$thread['public_id']
            );
            if ($synthesis) {
                $result = $synthesis['result'];
                $modelKey = (string)$synthesis['model_key'];
                $tokens = $synthesis['tokens'];
                $creditAfter = $synthesis['credits'];
                $responseSource = 'anthropic';
            }
        } catch (Throwable $error) {
            $responseSource = 'safe_fallback';
            mg_security_log('warning', 'multi_agent.ai_fallback', 'Specialized agent used safe fallback.', [
                'exception_type' => $error::class,
                'ai_reason' => $aiReason,
            ], $userId);
        }
    }

    if (!$result) {
        $result = mg_multi_agent_runtime_fallback($agent, $template, $message, $context);
    }

    $assistant = mg_multi_agent_runtime_store(
        $pdo,
        $userId,
        (int)$agent['id'],
        (int)$thread['id'],
        'assistant',
        (string)$result['reply'],
        is_array($result['cards'] ?? null) ? $result['cards'] : [],
        $context,
        $modelKey
    );

    mg_audit('multi_agent.chat_completed', 'agent', [
        'agent_id' => (string)$agent['public_id'],
        'thread_id' => (string)$thread['public_id'],
        'response_source' => $responseSource,
        'model_key' => $modelKey ?: $responseSource,
        'ai_reason' => $aiReason,
        'tool' => $tool,
        'used_ai' => $modelKey !== '',
        'ai_tokens_total' => $tokens['total'],
    ], $userId);

    return [
        'thread' => ['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],
        'user_message' => $userMessage,
        'assistant_message' => $assistant,
        'used_ai' => $modelKey !== '',
        'response_source' => $responseSource,
        'ai_reason' => $aiReason,
        'model_key' => $modelKey,
        'ai_tokens_used' => $tokens,
        'ai_credits' => $creditAfter ?? mg_ai_credit_snapshot($pdo, $userId, 'anthropic'),
    ];
}
