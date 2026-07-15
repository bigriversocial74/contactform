<?php
declare(strict_types=1);

function mg_personal_agent_thread(PDO $pdo, int $userId, string $threadPublicId = '', array $context = []): array
{
    mg_personal_agent_require_schema($pdo);
    if ($threadPublicId !== '') {
        $stmt=$pdo->prepare('SELECT id,public_id,title,selected_context_type,selected_context_public_id,last_message_at,cleared_at,created_at,updated_at FROM user_agent_threads WHERE owner_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$userId,$threadPublicId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (($context['type'] ?? 'none') !== 'none') {
                $pdo->prepare('UPDATE user_agent_threads SET selected_context_type=?,selected_context_public_id=?,updated_at=NOW() WHERE id=?')
                    ->execute([$context['type'],$context['id'] ?: null,(int)$row['id']]);
                $row['selected_context_type']=$context['type'];
                $row['selected_context_public_id']=$context['id'];
            }
            return ['internal_id'=>(int)$row['id'],'id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'context_type'=>(string)$row['selected_context_type'],'context_id'=>(string)($row['selected_context_public_id'] ?? '')];
        }
    }
    $publicId=mg_public_uuid();
    $type=(string)($context['type'] ?? 'none');
    if (!in_array($type,['none','contact','linked_user','list','plan'],true)) $type='none';
    $contextId=(string)($context['id'] ?? '');
    $pdo->prepare("INSERT INTO user_agent_threads (public_id,owner_user_id,title,selected_context_type,selected_context_public_id,created_at,updated_at)
        VALUES (?,?,'Personal gifting conversation',?,?,NOW(),NOW())")->execute([$publicId,$userId,$type,$contextId!==''?$contextId:null]);
    return ['internal_id'=>(int)$pdo->lastInsertId(),'id'=>$publicId,'title'=>'Personal gifting conversation','context_type'=>$type,'context_id'=>$contextId];
}

function mg_personal_agent_messages(PDO $pdo, int $userId, int $threadId, int $limit = 30): array
{
    $stmt=$pdo->prepare("SELECT public_id,role,body,cards_json,context_json,model_key,created_at
        FROM user_agent_messages WHERE owner_user_id=? AND thread_id=? ORDER BY id DESC LIMIT " . max(1,min(60,$limit)));
    $stmt->execute([$userId,$threadId]);
    $rows=array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'role'=>(string)$row['role'],'body'=>(string)$row['body'],
        'cards'=>mg_personal_agent_json($row['cards_json'] ?? null),'context'=>mg_personal_agent_json($row['context_json'] ?? null),
        'model_key'=>(string)($row['model_key'] ?? ''),'created_at'=>$row['created_at'],
    ],$rows);
}

function mg_personal_agent_store_message(PDO $pdo, int $userId, int $threadId, string $role, string $body, array $cards = [], array $context = [], string $modelKey = ''): array
{
    if (!in_array($role,['user','assistant','system'],true)) $role='assistant';
    $publicId=mg_public_uuid();
    $stmt=$pdo->prepare("INSERT INTO user_agent_messages
        (public_id,thread_id,owner_user_id,role,body,cards_json,context_json,model_key,created_at)
        VALUES (?,?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$publicId,$threadId,$userId,$role,$body,$cards?mg_personal_agent_json_encode($cards):null,$context?mg_personal_agent_json_encode($context):null,$modelKey?:null]);
    $pdo->prepare('UPDATE user_agent_threads SET last_message_at=NOW(),updated_at=NOW() WHERE id=? AND owner_user_id=?')->execute([$threadId,$userId]);
    return ['id'=>$publicId,'role'=>$role,'body'=>$body,'cards'=>$cards,'context'=>$context,'model_key'=>$modelKey,'created_at'=>gmdate('Y-m-d H:i:s')];
}

function mg_personal_agent_normalize_cards(mixed $cards, array $context): array
{
    if (!is_array($cards)) return [];
    $allowed=['save_draft_plan','create_reminder','open_list','open_contact','none'];
    $out=[];
    foreach ($cards as $card) {
        if (!is_array($card)) continue;
        $title=mg_personal_agent_text($card['title'] ?? '',140);
        $body=mg_personal_agent_text($card['body'] ?? $card['description'] ?? '',700);
        if ($title==='' && $body==='') continue;
        $action=mg_personal_agent_text($card['action'] ?? 'none',40);
        if (!in_array($action,$allowed,true)) $action='none';
        $risk=strtolower(mg_personal_agent_text($card['risk_level'] ?? 'low',20));
        if (!in_array($risk,['low','medium'],true)) $risk='medium';
        $payload=is_array($card['review_payload'] ?? null)?$card['review_payload']:[];
        $safePayload=[
            'title'=>mg_personal_agent_text($payload['title'] ?? $title,190),
            'occasion_type'=>mg_personal_agent_text($payload['occasion_type'] ?? 'general',64),
            'occasion_label'=>mg_personal_agent_text($payload['occasion_label'] ?? '',160),
            'target_date'=>mg_personal_agent_text($payload['target_date'] ?? '',10),
            'budget_min'=>is_numeric($payload['budget_min'] ?? null)?(float)$payload['budget_min']:null,
            'budget_max'=>is_numeric($payload['budget_max'] ?? null)?(float)$payload['budget_max']:null,
            'currency'=>mg_personal_agent_currency($payload['currency'] ?? 'USD'),
            'notes'=>mg_personal_agent_text($payload['notes'] ?? $body,2000),
            'context_type'=>$context['type'] ?? 'none',
            'context_id'=>$context['id'] ?? '',
            'source'=>'agent',
        ];
        $out[]=[
            'type'=>mg_personal_agent_text($card['type'] ?? 'recommendation',40) ?: 'recommendation',
            'title'=>$title !== '' ? $title : 'Gift recommendation',
            'body'=>$body,
            'reason'=>mg_personal_agent_text($card['reason'] ?? '',400),
            'timing'=>mg_personal_agent_text($card['timing'] ?? '',120),
            'warning'=>mg_personal_agent_text($card['warning'] ?? '',300),
            'action'=>$action,
            'action_label'=>mg_personal_agent_text($card['action_label'] ?? ($action==='save_draft_plan'?'Save draft plan':($action==='create_reminder'?'Create reminder':'')),80),
            'risk_level'=>$risk,
            'review_payload'=>$safePayload,
        ];
        if (count($out)>=4) break;
    }
    return $out;
}

function mg_personal_agent_system_prompt(): string
{
    return <<<'PROMPT'
You are Microgifter's Personal Gifting Agent for an individual customer.

Your job is to help the customer remember important dates, understand selected contacts or lists, create thoughtful gift recommendations, and prepare approval-first draft plans and reminders.

Hard privacy and safety rules:
- Never expose or repeat full phone numbers, email addresses, street addresses, claim codes, payment details, passwords, tokens, or private profile fields.
- Use only the context supplied in this request. Never infer private facts.
- Do not purchase, send, schedule, charge, claim, redeem, or transfer anything.
- Every action is advisory or a reviewable draft. State that clearly.
- Avoid manipulative, discriminatory, or sensitive-personal-data recommendations.
- Respect allergies, restrictions, budgets, timing, and relationship context.
- Prefer local merchant categories and practical timing, but do not invent merchant inventory.
- Return valid JSON only, with no markdown fences and no prose outside JSON.

Return:
{
  "reply": "concise helpful response",
  "cards": [
    {
      "type": "recommendation|plan|reminder|warning|next_step",
      "title": "short title",
      "body": "specific recommendation",
      "reason": "why this fits the provided context",
      "timing": "when to act",
      "warning": "optional constraint",
      "action": "save_draft_plan|create_reminder|open_list|open_contact|none",
      "action_label": "optional button label",
      "risk_level": "low|medium",
      "review_payload": {
        "title": "draft plan title",
        "occasion_type": "birthday|anniversary|holiday|thank_you|recognition|general",
        "occasion_label": "human label",
        "target_date": "YYYY-MM-DD or empty",
        "budget_min": 0,
        "budget_max": 0,
        "currency": "USD",
        "notes": "approval-first draft notes"
      }
    }
  ]
}
PROMPT;
}

function mg_personal_agent_model(PDO $pdo, int $userId, string $requestedPublicId = ''): ?array
{
    $settings=mg_personal_agent_settings($pdo,$userId);
    $preferred=$requestedPublicId !== '' ? $requestedPublicId : (string)$settings['preferred_model_id'];
    $baseSql="SELECT m.*,p.provider_key,p.display_name provider_name,p.env_var_name,p.enabled provider_enabled,
        p.rate_limit_per_minute,p.rate_limit_per_hour,p.rate_limit_per_day,p.user_rate_limit_per_hour,p.user_rate_limit_per_day,
        p.agent_rate_limit_per_hour,p.agent_rate_limit_per_day
        FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id
        WHERE m.enabled=1 AND p.enabled=1 AND p.provider_key='anthropic'
        AND LOWER(m.model_key) NOT LIKE '%opus%' AND LOWER(m.model_key) NOT LIKE '%fable%'
        AND m.model_key NOT IN ('claude-3-5-haiku-latest','claude-3-5-haiku-20241022')";
    if ($preferred !== '') {
        $stmt=$pdo->prepare($baseSql.' AND m.public_id=? LIMIT 1');
        $stmt->execute([$preferred]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && mg_ai_env_configured((string)$row['env_var_name'])) return $row;
    }
    $orderSql=mg_personal_agent_model_order_sql();
    $rows=$pdo->query($baseSql.' ORDER BY '.$orderSql.',m.is_default DESC,m.sort_order,m.display_name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        if (mg_ai_env_configured((string)$row['env_var_name'])) return $row;
    }
    return null;
}

function mg_personal_agent_fallback(string $message, array $context, array $dashboard): array
{
    $name=(string)($context['name'] ?? '');
    $settings=$dashboard['settings'] ?? [];
    $events=$dashboard['upcoming_dates'] ?? [];
    $event=null;
    foreach ($events as $candidate) {
        if (($context['type'] ?? '') === 'contact' && ($candidate['contact_id'] ?? '') === ($context['id'] ?? '')) {
            $event=$candidate;
            break;
        }
    }
    $subject=$name !== '' ? $name : 'your recipient';
    $targetDate=(string)($event['event_date'] ?? '');
    $occasion=(string)($event['label'] ?? 'gift');
    $min=$context['details']['budget_min'] ?? $settings['default_budget_min'] ?? null;
    $max=$context['details']['budget_max'] ?? $settings['default_budget_max'] ?? null;
    $budgetText=($min!==null||$max!==null) ? ' within the saved budget' : '';
    $reply='I can help turn this into an approval-first gift plan for ' . $subject . $budgetText . '. Nothing will be purchased or sent without your review.';
    if ($targetDate !== '') $reply .= ' The next saved occasion is ' . $occasion . ' on ' . $targetDate . '.';
    return [
        'reply'=>$reply,
        'cards'=>[[
            'type'=>'plan',
            'title'=>'Draft a thoughtful plan for ' . $subject,
            'body'=>'Use the saved relationship, preferences, timing, and budget to compare a few local gift categories before choosing a product.',
            'reason'=>'A draft keeps the recommendation reviewable and avoids acting on incomplete information.',
            'timing'=>$targetDate !== '' ? 'Review before ' . $targetDate : 'Review when ready',
            'warning'=>'Confirm current preferences and restrictions before purchase.',
            'action'=>'save_draft_plan',
            'action_label'=>'Save draft plan',
            'risk_level'=>'low',
            'review_payload'=>[
                'title'=>ucfirst($occasion) . ' plan for ' . $subject,
                'occasion_type'=>strtolower(preg_replace('/[^a-z0-9]+/','_', $occasion) ?? 'general'),
                'occasion_label'=>$occasion,
                'target_date'=>$targetDate,
                'budget_min'=>$min,
                'budget_max'=>$max,
                'currency'=>$settings['default_currency'] ?? 'USD',
                'notes'=>'Approval-first draft generated from the selected personal gifting context.',
            ],
        ]],
    ];
}

function mg_personal_agent_chat(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_require_schema($pdo);
    $message=mg_personal_agent_text($input['message'] ?? '',2000);
    if ($message==='') throw new InvalidArgumentException('Enter a message for the Personal Gifting Agent.');
    $context=mg_personal_agent_resolve_context($pdo,$userId,(string)($input['context_type'] ?? 'none'),(string)($input['context_id'] ?? ''));
    $thread=mg_personal_agent_thread($pdo,$userId,mg_personal_agent_text($input['thread_id'] ?? '',80),$context);
    $publicContext=mg_personal_agent_public_context($context);
    $aiContext=mg_personal_agent_ai_context($context);
    $userMessage=mg_personal_agent_store_message($pdo,$userId,$thread['internal_id'],'user',$message,[],$publicContext);
    $dashboard=mg_personal_agent_dashboard($pdo,$userId);
    $memory=array_slice($dashboard['memory'] ?? [],0,30);
    $history=mg_personal_agent_messages($pdo,$userId,$thread['internal_id'],14);
    $model=mg_personal_agent_model($pdo,$userId,mg_personal_agent_text($input['model_id'] ?? '',80));
    $result=null;
    $modelKey='';
    if ($model) {
        try {
            $provider=$model;
            $provider['id']=(int)$model['provider_id'];
            mg_ai_enforce_rate_limits($pdo,$provider,$model,$userId,null);
            $messages=[];
            foreach ($history as $item) {
                if (!in_array($item['role'],['user','assistant'],true)) continue;
                $messages[]=['role'=>$item['role'],'content'=>mb_substr((string)$item['body'],0,4000)];
            }
            $contextPayload=[
                'selected_context'=>$aiContext,
                'upcoming_dates'=>array_slice($dashboard['upcoming_dates'] ?? [],0,20),
                'active_plans'=>array_slice(array_values(array_filter($dashboard['plans'] ?? [],static fn(array $plan): bool=>in_array($plan['status'],['draft','planned','ready'],true))),0,20),
                'scheduled_reminders'=>array_slice($dashboard['reminders'] ?? [],0,20),
                'agent_memory'=>array_map(static fn(array $item): array=>['category'=>$item['category'],'title'=>$item['title'],'value'=>$item['value']],$memory),
                'settings'=>$dashboard['settings'] ?? [],
            ];
            $payload=[
                'model'=>(string)$model['model_key'],
                'max_tokens'=>max(500,min(1800,(int)($model['max_output_tokens'] ?? 1200))),
                'temperature'=>0.3,
                'system'=>mg_personal_agent_system_prompt() . "\n\nCustomer-safe context JSON:\n" . mg_personal_agent_json_encode($contextPayload),
                'messages'=>$messages,
            ];
            $response=mg_anthropic_messages($payload);
            $decoded=mg_anthropic_extract_json_object(mg_anthropic_text_from_response($response));
            $reply=mg_personal_agent_text($decoded['reply'] ?? '',6000);
            $cards=mg_personal_agent_normalize_cards($decoded['cards'] ?? [],$publicContext);
            if ($reply==='') throw new RuntimeException('Claude returned an empty reply.');
            $result=['reply'=>$reply,'cards'=>$cards];
            $modelKey=(string)$model['model_key'];
            mg_ai_insert_usage_event($pdo,(int)$model['provider_id'],(int)$model['id'],$userId,null,'completed',null,['source'=>'personal_gifting_agent']);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning','user_agent.ai_fallback','Personal agent AI request used the safe fallback.',['exception_type'=>get_class($error)],$userId);
            }
        }
    }
    if (!$result) $result=mg_personal_agent_fallback($message,$publicContext,$dashboard);
    $cards=mg_personal_agent_normalize_cards($result['cards'] ?? [],$publicContext);
    $assistant=mg_personal_agent_store_message($pdo,$userId,$thread['internal_id'],'assistant',(string)$result['reply'],$cards,$publicContext,$modelKey);
    mg_audit('user_agent.chat_completed','user_agent_thread',['thread_id'=>$thread['id'],'model_key'=>$modelKey ?: 'safe_fallback','context_type'=>$publicContext['type']],$userId);
    return [
        'thread'=>['id'=>$thread['id'],'title'=>$thread['title']],
        'user_message'=>$userMessage,
        'assistant_message'=>$assistant,
        'context'=>$publicContext,
        'used_ai'=>$modelKey !== '',
        'model_key'=>$modelKey,
    ];
}
