<?php
declare(strict_types=1);

require_once __DIR__.'/personal-gifting-agent.php';
require_once __DIR__.'/task-agent-context.php';
require_once __DIR__.'/task-agent-memory.php';
require_once __DIR__.'/task-agent-shortlist.php';
require_once __DIR__.'/task-agent-intent-router.php';
require_once dirname(__DIR__).'/api/agents/_agent.php';

function mg_multi_agent_runtime_require_schema(PDO $pdo): void
{
    foreach (['multi_agent_threads','multi_agent_messages','multi_agent_memory','multi_agent_onboarding','multi_agent_drafts'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if (!(bool)$stmt->fetchColumn()) throw new RuntimeException('Multi-agent runtime migration is required.');
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

function mg_multi_agent_runtime_prompt(array $agent, array $template): string
{
    $name = (string)($agent['name'] ?? 'Specialized Agent');
    $key = (string)($template['key'] ?? '');
    $mission = match ($key) {
        'birthday_occasion' => 'Help the customer compare shortlisted gift options, explain fit, or write a thoughtful message using the supplied recipient and occasion context.',
        'local_shopping' => 'Help the customer compare shortlisted local products, services, experiences, and creative work using supplied recipient, occasion, budget, and location constraints. Never invent inventory.',
        'merchant_campaign' => 'Help an authorized merchant compare campaign options or improve draft language. Never publish or change merchant data.',
        default => 'Help the user synthesize the focused context supplied for this agent.',
    };
    return "You are Microgifter's {$name}. {$mission}\n\nRules:\n"
        ."- Keep this agent's context separate from every other agent.\n"
        ."- Never reveal secrets, payment data, claim codes, direct contact details, or hidden profile data.\n"
        ."- Use only supplied permission-safe context, sanitized agent memory, and the current published-product shortlist.\n"
        ."- Clearly disclose missing information and do not infer private facts.\n"
        ."- Do not purchase, publish, message, schedule, charge, claim, redeem, transfer, shortlist, or save data.\n"
        ."- All write actions are handled by deterministic server controls after user approval.\n"
        ."- Keep the answer compact and specific.\n"
        ."- Return JSON only: {\"reply\":\"helpful response\",\"cards\":[{\"type\":\"recommendation|question|warning\",\"title\":\"short title\",\"body\":\"specific content\",\"action\":\"none|seed_prompt\",\"prompt\":\"optional follow-up\"}]}";
}

function mg_multi_agent_runtime_thread(PDO $pdo, array $agent, int $userId, string $publicId = ''): array
{
    if ($publicId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM multi_agent_threads WHERE public_id=? AND agent_id=? AND owner_user_id=? AND status='active' LIMIT 1");
        $stmt->execute([$publicId,(int)$agent['id'],$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    $existing = $pdo->prepare("SELECT * FROM multi_agent_threads WHERE agent_id=? AND owner_user_id=? AND status='active' ORDER BY id LIMIT 1");
    $existing->execute([(int)$agent['id'],$userId]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
    $id = mg_public_uuid();
    $pdo->prepare("INSERT INTO multi_agent_threads(public_id,agent_id,owner_user_id,title,status,created_at,updated_at) VALUES (?,?,?,'Agent conversation','active',NOW(),NOW())")
        ->execute([$id,(int)$agent['id'],$userId]);
    $stmt = $pdo->prepare('SELECT * FROM multi_agent_threads WHERE id=?');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return (array)$stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_multi_agent_runtime_messages(PDO $pdo, int $userId, int $agentId, int $threadId, int $limit = 60): array
{
    $stmt = $pdo->prepare('SELECT public_id,role,body,cards_json,context_json,model_key,created_at FROM multi_agent_messages WHERE owner_user_id=? AND agent_id=? AND thread_id=? ORDER BY id DESC LIMIT '.max(1,min(100,$limit)));
    $stmt->execute([$userId,$agentId,$threadId]);
    return array_map(static function(array $row): array {
        $row['cards'] = $row['cards_json'] ? json_decode((string)$row['cards_json'], true) : [];
        $row['context'] = $row['context_json'] ? json_decode((string)$row['context_json'], true) : [];
        unset($row['cards_json'],$row['context_json']);
        return $row;
    }, array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)));
}

function mg_multi_agent_runtime_store(PDO $pdo, int $userId, int $agentId, int $threadId, string $role, string $body, array $cards = [], array $context = [], string $model = ''): array
{
    $publicId = mg_public_uuid();
    $pdo->prepare('INSERT INTO multi_agent_messages(public_id,thread_id,agent_id,owner_user_id,role,body,cards_json,context_json,model_key,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$publicId,$threadId,$agentId,$userId,$role,$body,$cards?json_encode($cards,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,$context?json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,$model?:null]);
    $pdo->prepare('UPDATE multi_agent_threads SET last_message_at=NOW(),updated_at=NOW() WHERE id=? AND owner_user_id=?')->execute([$threadId,$userId]);
    return ['public_id'=>$publicId,'role'=>$role,'body'=>$body,'cards'=>$cards,'context'=>$context,'model_key'=>$model,'created_at'=>gmdate('Y-m-d H:i:s')];
}

function mg_multi_agent_runtime_memory(PDO $pdo, int $userId, int $agentId): array
{
    return mg_task_agent_memory_list($pdo,$userId,$agentId,50);
}

function mg_multi_agent_runtime_onboarding(PDO $pdo, int $userId, int $agentId): array
{
    $stmt = $pdo->prepare('SELECT status,current_step,answers_json,completed_at,updated_at FROM multi_agent_onboarding WHERE owner_user_id=? AND agent_id=? LIMIT 1');
    $stmt->execute([$userId,$agentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['status'=>'not_started','current_step'=>null,'answers'=>[],'completed_at'=>null];
    $row['answers'] = $row['answers_json'] ? json_decode((string)$row['answers_json'], true) : [];
    unset($row['answers_json']);
    return $row;
}

function mg_multi_agent_runtime_context(PDO $pdo, int $userId, array $agent, array $template): array
{
    $agentId = (int)$agent['id'];
    $memory = mg_task_agent_memory_list($pdo,$userId,$agentId,50);
    $shortlist = mg_task_agent_shortlist_list($pdo,$userId,$agentId,20);
    $context = [
        'agent' => ['id'=>(string)$agent['public_id'],'name'=>(string)$agent['name'],'template_key'=>(string)($template['key']??'')],
        'memory' => $memory,
        'memory_for_model' => mg_task_agent_memory_for_model($memory),
        'shortlist' => $shortlist,
        'shortlist_for_model' => mg_task_agent_shortlist_for_model($shortlist),
        'onboarding' => mg_multi_agent_runtime_onboarding($pdo,$userId,$agentId),
    ];
    if (($template['key'] ?? '') === 'birthday_occasion') {
        $snapshot = mg_task_agent_context_snapshot($pdo,$userId,90);
        $snapshot['summary']['shortlisted_products'] = count($shortlist);
        $context['system_snapshot'] = $snapshot;
        $context['upcoming_dates'] = $snapshot['upcoming'];
        $context['active_plans'] = $snapshot['plans'];
        $context['scheduled_reminders'] = $snapshot['reminders'];
    }
    return $context;
}

function mg_multi_agent_runtime_model_context(array $context, string $message = ''): array
{
    return mg_task_agent_model_context($message,$context);
}

function mg_multi_agent_runtime_fallback(array $agent, array $template, string $message, array $context): array
{
    if (($template['key'] ?? '') === 'birthday_occasion') {
        $dates = $context['upcoming_dates'] ?? [];
        $next = $dates[0] ?? null;
        $reply = 'I can answer saved-data questions, search current local gifts, manage a shortlist, prepare a reviewable plan or reminder, or save a safe preference without using AI.';
        if (is_array($next)) $reply .= ' Your next saved occasion is '.(string)($next['label']??'an occasion').' on '.(string)($next['event_date']??'the saved date').'.';
        return ['reply'=>$reply,'cards'=>[[
            'type'=>'question',
            'title'=>'Choose a deterministic action',
            'body'=>'Name a saved recipient and ask to find local gifts, show the shortlist, review context, prepare a plan, create a reminder, or recall saved memory.',
            'action'=>'seed_prompt',
            'prompt'=>'Find local gifts for ',
            'review_payload'=>[],
        ]]];
    }
    return ['reply'=>'Add the recipient, goal, budget, timing, or merchant context needed for a reviewable plan.','cards'=>[]];
}

function mg_multi_agent_runtime_chat(PDO $pdo, int $userId, array $agent, array $input): array
{
    mg_multi_agent_runtime_require_schema($pdo);
    if (($agent['lifecycle_status'] ?? '') !== 'active') throw new RuntimeException('Agent is not active.');
    if (($agent['runtime_status'] ?? '') === 'paused') throw new RuntimeException('Agent is paused. Resume it before chatting.');

    $message = mg_personal_agent_text($input['message'] ?? '',3000);
    if ($message === '') throw new InvalidArgumentException('Enter a message for this agent.');

    $thread = mg_multi_agent_runtime_thread($pdo,$agent,$userId,mg_personal_agent_text($input['thread_id'] ?? '',80));
    $template = mg_multi_agent_runtime_template($agent);
    $context = mg_multi_agent_runtime_context($pdo,$userId,$agent,$template);
    $userMessage = mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'user',$message,[],$context);
    $history = mg_multi_agent_runtime_messages($pdo,$userId,(int)$agent['id'],(int)$thread['id'],8);

    $route = mg_task_agent_route($message,$context,$template);
    $result = is_array($route['result'] ?? null) ? $route['result'] : null;
    $responseSource = (string)($route['response_source'] ?? 'safe_fallback');
    $aiReason = (string)($route['ai_reason'] ?? '');
    $tool = (string)($route['tool'] ?? '');
    $toolInput = is_array($route['tool_input'] ?? null) ? $route['tool_input'] : [];
    $modelKey = '';
    $creditAfter = null;
    $tokens = ['input'=>0,'output'=>0,'total'=>0];

    if (!$result && $tool === 'discover_products') {
        $result = mg_task_agent_discover_products($pdo,$userId,(int)$agent['id'],$toolInput);
        $responseSource = 'system_query';
        $aiReason = '';
    }

    if (!$result && $aiReason !== '') {
        $packageContext = mg_ai_credit_package_context($pdo,$userId);
        if (mg_personal_agent_ai_package_eligible($packageContext)) {
            $model = mg_personal_agent_model($pdo,$userId,mg_personal_agent_text($input['model_id'] ?? '',80));
            if ($model) {
                $maxOutput = max(350,min(900,(int)($model['max_output_tokens'] ?? 700)));
                mg_ai_credit_preflight($pdo,$userId,'anthropic',$maxOutput,'specialized_agent');
                try {
                    $messages = [];
                    foreach ($history as $item) {
                        if (!in_array($item['role'] ?? '',['user','assistant'],true)) continue;
                        $messages[] = ['role'=>$item['role'],'content'=>mb_substr((string)($item['body'] ?? ''),0,1400)];
                    }
                    $safeContext = mg_multi_agent_runtime_model_context($context,$message);
                    $response = mg_anthropic_messages([
                        'model'=>(string)$model['model_key'],
                        'max_tokens'=>$maxOutput,
                        'temperature'=>0.2,
                        'system'=>mg_multi_agent_runtime_prompt($agent,$template)."\n\nAI call reason: {$aiReason}\n\nPermission-safe focused context JSON:\n".json_encode($safeContext,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                        'messages'=>$messages,
                    ]);
                    $decoded = mg_anthropic_extract_json_object(mg_anthropic_text_from_response($response));
                    $reply = mg_personal_agent_text($decoded['reply'] ?? '',4000);
                    if ($reply === '') throw new RuntimeException('AI returned an empty reply.');
                    $result = ['reply'=>$reply,'cards'=>mg_task_agent_sanitize_model_cards(is_array($decoded['cards'] ?? null)?$decoded['cards']:[])];
                    $modelKey = (string)$model['model_key'];
                    $responseSource = 'anthropic';
                    $raw = mg_anthropic_last_response();
                    $usage = is_array($raw['usage'] ?? null) ? $raw['usage'] : [];
                    $tokens = [
                        'input'=>(int)($usage['input_tokens'] ?? 0),
                        'output'=>(int)($usage['output_tokens'] ?? 0),
                        'total'=>(int)($usage['input_tokens'] ?? 0)+(int)($usage['output_tokens'] ?? 0),
                    ];
                    $creditAfter = mg_ai_credit_consume(
                        $pdo,$userId,(int)$model['provider_id'],(int)$model['id'],'anthropic',
                        $tokens['input'],$tokens['output'],'specialized_agent',(string)($raw['id'] ?? ''),
                        ['agent_id'=>(string)$agent['public_id'],'thread_id'=>(string)$thread['public_id'],'ai_reason'=>$aiReason]
                    );
                } catch (Throwable $e) {
                    $responseSource = 'safe_fallback';
                    mg_security_log('warning','multi_agent.ai_fallback','Specialized agent used safe fallback.',['exception_type'=>$e::class,'ai_reason'=>$aiReason],$userId);
                }
            }
        }
    }

    if (!$result) $result = mg_multi_agent_runtime_fallback($agent,$template,$message,$context);
    $assistant = mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'assistant',(string)$result['reply'],is_array($result['cards'] ?? null)?$result['cards']:[],$context,$modelKey);
    mg_audit('multi_agent.chat_completed','agent',[
        'agent_id'=>(string)$agent['public_id'],
        'thread_id'=>(string)$thread['public_id'],
        'response_source'=>$responseSource,
        'model_key'=>$modelKey ?: $responseSource,
        'ai_reason'=>$aiReason,
        'tool'=>$tool,
        'used_ai'=>$modelKey !== '',
        'ai_tokens_total'=>$tokens['total'],
    ],$userId);

    return [
        'thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],
        'user_message'=>$userMessage,
        'assistant_message'=>$assistant,
        'used_ai'=>$modelKey !== '',
        'response_source'=>$responseSource,
        'ai_reason'=>$aiReason,
        'model_key'=>$modelKey,
        'ai_tokens_used'=>$tokens,
        'ai_credits'=>$creditAfter ?? mg_ai_credit_snapshot($pdo,$userId,'anthropic'),
    ];
}
