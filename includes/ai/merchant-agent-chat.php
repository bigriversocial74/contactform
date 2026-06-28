<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-planner.php';
require_once __DIR__ . '/merchant-agent-skills.php';

function mg_ai_chat_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mg_ai_chat_clean(mixed $value, int $max = 2000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $max);
}

function mg_ai_chat_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_ai_chat_decode_agent_payload(string $text): array
{
    $raw = trim($text);
    if ($raw === '') return ['reply' => '', 'cards' => [], 'blocks' => []];
    $candidates = [$raw];
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $raw, $match)) {
        $candidates[] = trim($match[1]);
    }
    $first = strpos($raw, '{');
    $last = strrpos($raw, '}');
    if ($first !== false && $last !== false && $last > $first) {
        $candidates[] = substr($raw, $first, $last - $first + 1);
    }
    foreach ($candidates as $candidate) {
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            $reply = mg_ai_chat_clean($decoded['reply'] ?? $decoded['body'] ?? '', 6000);
            return [
                'reply' => $reply !== '' ? $reply : $raw,
                'cards' => is_array($decoded['cards'] ?? null) ? $decoded['cards'] : [],
                'blocks' => is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : [],
                'parsed' => true,
            ];
        }
    }
    return ['reply' => $raw, 'cards' => [], 'blocks' => [], 'parsed' => false];
}

function mg_ai_chat_allowed_scopes(): array
{
    return ['overview','campaigns','rewards','crm','claims','analytics','developer_api','locations','onboarding'];
}

function mg_ai_chat_allowed_links(): array
{
    return ['/merchant.php','/merchant-agent-chat.php','/merchant-automation.php','/merchant-agent-monitor.php','/merchant-agent-approvals.php','/merchant-agent-execution.php','/merchant-agent-messages.php','/merchant-campaigns.php','/merchant-reward-templates.php','/merchant-crm.php','/merchant-followups.php','/merchant-claims.php','/merchant-intelligence.php','/merchant-locations.php','/merchant-distribution.php','/account-subscriptions.php'];
}

function mg_ai_chat_infer_action_key(array $card): string
{
    $candidate = mg_ai_chat_clean($card['review_action_key'] ?? $card['action_key'] ?? '', 80);
    if ($candidate !== '' && in_array($candidate, mg_ai_merchant_allowed_actions(), true)) return $candidate;
    $type = strtolower(mg_ai_chat_clean($card['type'] ?? '', 80));
    $url = mg_ai_chat_clean($card['action_url'] ?? $card['url'] ?? '', 220);
    $text = strtolower(($type . ' ' . ($card['title'] ?? '') . ' ' . ($card['body'] ?? '') . ' ' . $url));
    if (str_contains($url, 'reward') || str_contains($text, 'reward')) return 'recommend_reward_optimization';
    if (str_contains($url, 'crm') || str_contains($url, 'followup') || str_contains($text, 'follow-up') || str_contains($text, 'follow up') || str_contains($text, 'crm')) return 'create_crm_followup_task';
    if (str_contains($url, 'claim') || str_contains($text, 'claim') || str_contains($text, 'redemption')) return 'recommend_claim_review';
    if (str_contains($url, 'distribution') || str_contains($text, 'api') || str_contains($text, 'developer')) return 'recommend_api_integration';
    if (str_contains($url, 'location') || str_contains($text, 'location') || str_contains($text, 'store')) return 'recommend_location_fix';
    if (str_contains($url, 'campaign') || str_contains($text, 'campaign') || str_contains($text, 'contest') || str_contains($text, 'social')) return 'recommend_campaign_optimization';
    if (str_contains($text, 'upgrade') || str_contains($text, 'package')) return 'recommend_package_upgrade';
    return 'create_report_snapshot';
}

function mg_ai_chat_normalize_cards(mixed $cards): array
{
    if (!is_array($cards)) return [];
    $allowedLinks = mg_ai_chat_allowed_links();
    $out = [];
    foreach ($cards as $card) {
        if (!is_array($card)) continue;
        $title = mg_ai_chat_clean($card['title'] ?? '', 120);
        $body = mg_ai_chat_clean($card['body'] ?? $card['description'] ?? '', 500);
        if ($title === '' && $body === '') continue;
        $url = mg_ai_chat_clean($card['action_url'] ?? $card['url'] ?? '', 220);
        if ($url !== '' && !in_array($url, $allowedLinks, true)) $url = '';
        $risk = strtolower(mg_ai_chat_clean($card['risk_level'] ?? 'low', 20));
        if (!in_array($risk, ['low','medium','high','critical'], true)) $risk = 'low';
        $out[] = [
            'type' => mg_ai_chat_clean($card['type'] ?? 'recommendation', 40) ?: 'recommendation',
            'title' => $title !== '' ? $title : 'Agent recommendation',
            'body' => $body,
            'action_label' => mg_ai_chat_clean($card['action_label'] ?? ($url !== '' ? 'Open' : ''), 60),
            'action_url' => $url,
            'review_action_key' => mg_ai_chat_infer_action_key($card),
            'review_payload' => mg_ai_chat_json($card['review_payload'] ?? $card['suggested_payload'] ?? []),
            'risk_level' => $risk,
            'review_plan_id' => mg_ai_chat_clean($card['review_plan_id'] ?? '', 80),
            'review_item_id' => mg_ai_chat_clean($card['review_item_id'] ?? '', 80),
            'bridgeable' => empty($card['review_item_id']),
        ];
        if (count($out) >= 4) break;
    }
    return $out;
}

function mg_ai_chat_system_prompt(): string
{
    return <<<'PROMPT'
You are Microgifter's merchant agent chat.

You answer in a chat-style feed for a local merchant using Microgifter.

Hard rules:
- You are advisory only. Do not execute actions.
- You do not issue rewards, process claims, move payments, send customer messages, alter wallet ownership, or change PPPM lifecycle state.
- You may recommend next steps, explain merchant data, create draft/review actions, and render useful blocks directly inside the chat.
- Use merchant-facing language. Be direct, specific, and brief.
- Avoid customer-level private data. Use summaries, counts, trends, and operational observations.
- Return valid JSON only. No markdown fences. No prose outside JSON. Never wrap JSON in ```json fences.

Allowed review_action_key values:
create_campaign_draft, update_campaign_draft, pause_campaign, resume_campaign, create_reward_template_draft, update_reward_template_draft, create_crm_followup_task, create_message_draft, create_report_snapshot, create_merchant_alert, recommend_package_upgrade, recommend_location_fix, recommend_api_integration, recommend_claim_review, recommend_reward_optimization, recommend_campaign_optimization

Return this JSON shape:
{
  "reply": "chat reply for the merchant",
  "blocks": [
    {
      "type": "chart|metric_grid|forecast|product_opportunity|social_campaign|social_posts|project|warning|insight",
      "title": "short block title",
      "body": "short supporting detail",
      "chart_type": "bar|line|pie",
      "data": [{"label":"Name","value":10}],
      "metrics": [{"label":"Claims","value":"42"}],
      "posts": [{"channel":"Facebook","copy":"draft post"}],
      "review_action_key": "optional allowed review action key"
    }
  ],
  "cards": [
    {
      "type": "insight|recommendation|warning|next_step",
      "title": "short card title",
      "body": "short supporting detail",
      "action_label": "optional button label",
      "action_url": "optional app URL from the allowed links list",
      "review_action_key": "optional allowed review action key",
      "risk_level": "low|medium|high|critical",
      "review_payload": {"optional":"safe draft/review payload"}
    }
  ]
}
PROMPT;
}

function mg_ai_chat_message_from_row(array $row): array
{
    $ctx = mg_ai_chat_json($row['event_context_json'] ?? null);
    $role = (string)($ctx['role'] ?? ((string)$row['event_type'] === 'merchant.agent_chat.user' ? 'user' : 'assistant'));
    $body = (string)($ctx['body'] ?? '');
    $cards = mg_ai_chat_normalize_cards($ctx['cards'] ?? []);
    $blocks = mg_agent_chat_normalize_blocks($ctx['blocks'] ?? []);
    if ($role === 'assistant') {
        $decoded = mg_ai_chat_decode_agent_payload($body);
        if (!empty($decoded['parsed'])) {
            $body = mg_ai_chat_clean($decoded['reply'] ?? $body, 6000);
            if ($cards === []) $cards = mg_ai_chat_normalize_cards($decoded['cards'] ?? []);
            if ($blocks === []) $blocks = mg_agent_chat_normalize_blocks($decoded['blocks'] ?? []);
        }
    }
    return [
        'id' => (string)$row['public_id'],
        'role' => $role,
        'body' => $body,
        'cards' => $cards,
        'blocks' => $blocks,
        'model' => (string)($ctx['model'] ?? ''),
        'scope' => (string)($ctx['scope'] ?? 'overview'),
        'thread_public_id' => (string)($ctx['thread_public_id'] ?? ''),
        'skills' => mg_agent_skill_keys($ctx['skills'] ?? null),
        'created_at' => $row['created_at'] ?? null,
    ];
}

function mg_ai_chat_recent_messages(PDO $pdo, int $merchantId, int $limit = 30, string $threadPublicId = ''): array
{
    $limit = max(1, min(60, $limit));
    $params = [$merchantId];
    $where = "merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant')";
    if ($threadPublicId !== '') {
        $where .= " AND event_context_json LIKE ?";
        $params[] = '%"thread_public_id":"' . str_replace(['%','_'], ['\\%','\\_'], $threadPublicId) . '"%';
        if (mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
            try {
                $stmt = $pdo->prepare('SELECT cleared_at FROM merchant_agent_threads WHERE merchant_user_id=? AND public_id=? LIMIT 1');
                $stmt->execute([$merchantId, $threadPublicId]);
                $clearedAt = (string)($stmt->fetchColumn() ?: '');
                if ($clearedAt !== '') {
                    $where .= ' AND created_at > ?';
                    $params[] = $clearedAt;
                }
            } catch (Throwable) {}
        }
    }
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE {$where} ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute($params);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return array_map('mg_ai_chat_message_from_row', $rows);
}

function mg_ai_chat_overview(PDO $pdo, int $merchantId): array
{
    $overview=['pending_reviews'=>0,'review_ready_plans'=>0,'executed_items'=>0,'chat_messages'=>0,'latest'=>[]];
    try {
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant')");$stmt->execute([$merchantId]);$overview['chat_messages']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM ai_merchant_plans WHERE merchant_user_id=? AND status='review_ready'");$stmt->execute([$merchantId]);$overview['review_ready_plans']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND i.status IN ('recommended','deferred','failed')");$stmt->execute([$merchantId]);$overview['pending_reviews']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND i.status='executed'");$stmt->execute([$merchantId]);$overview['executed_items']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare("SELECT i.public_id,i.title,i.action_key,i.status,i.created_at FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? ORDER BY i.id DESC LIMIT 5");$stmt->execute([$merchantId]);
        $overview['latest']=array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'action_key'=>(string)$row['action_key'],'status'=>(string)$row['status'],'created_at'=>$row['created_at']??null],$stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) { $overview['unavailable']=true; }
    return $overview;
}

function mg_ai_chat_record_message(PDO $pdo, int $merchantId, string $role, string $body, array $cards = [], array $meta = []): string
{
    $publicId=mg_ai_chat_uuid();
    $eventType=$role==='user'?'merchant.agent_chat.user':'merchant.agent_chat.assistant';
    $blocks = mg_agent_chat_normalize_blocks($meta['blocks'] ?? []);
    unset($meta['blocks']);
    $context=array_merge(['role'=>$role,'body'=>mg_ai_chat_clean($body,6000),'cards'=>mg_ai_chat_normalize_cards($cards),'blocks'=>$blocks,'source'=>'merchant_agent_chat','guardrail_applied'=>'Merchant agent chat is advisory. Review queue approval is required for workflow actions.'],$meta);
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$publicId,$merchantId,null,null,$eventType,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    return $publicId;
}

function mg_ai_chat_public_state(PDO $pdo, int $merchantId): array
{
    $profile = mg_agent_profile($pdo, $merchantId);
    $activeThread = mg_agent_active_thread($pdo, $merchantId);
    $threadId = (string)($activeThread['id'] ?? '');
    $messages = mg_ai_chat_recent_messages($pdo,$merchantId,30,$threadId);
    $cleared = !empty($activeThread['cleared_at']);
    if ($messages === [] && $threadId !== '' && !$cleared) {
        $messages = mg_ai_chat_recent_messages($pdo,$merchantId,30,'');
    }
    return [
        'overview' => mg_ai_chat_overview($pdo,$merchantId),
        'messages' => $messages,
        'quick_prompts' => [
            'Analyze my product opportunities and show a chart.',
            'Create a social campaign from my best current offer.',
            'Find claim or redemption issues.',
            'Draft a weekend campaign plan.',
            'What should I focus on today?'
        ],
        'scopes' => mg_ai_chat_allowed_scopes(),
        'agent_profile' => $profile,
        'active_thread' => $activeThread,
        'threads' => mg_agent_threads($pdo, $merchantId),
        'skills' => mg_agent_skills_public(),
    ];
}

function mg_ai_chat_catalog_model(PDO $pdo, string $preferredModelKey = ''): array
{
    if($preferredModelKey!==''){$stmt=$pdo->prepare("SELECT m.*,p.provider_key,p.display_name provider_name,p.env_var_name FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id WHERE p.provider_key='anthropic' AND p.enabled=1 AND m.enabled=1 AND m.model_key=? LIMIT 1");$stmt->execute([$preferredModelKey]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(is_array($row))return $row;}
    $stmt=$pdo->prepare("SELECT m.*,p.provider_key,p.display_name provider_name,p.env_var_name FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id WHERE p.provider_key='anthropic' AND p.enabled=1 AND m.enabled=1 AND m.model_key IN ('claude-sonnet-4-6','claude-3-5-sonnet-latest') ORDER BY (m.model_key='claude-sonnet-4-6') DESC,m.is_default DESC,m.sort_order ASC LIMIT 1");$stmt->execute();$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!is_array($row))mg_fail('Claude Sonnet is not enabled in the AI model catalog.',503);return $row;
}

function mg_ai_chat_bridge_to_review(PDO $pdo, array $user, array $input): array
{
    $merchantId=(int)$user['id'];$messageId=mg_ai_chat_clean($input['message_id']??'',80);$cardIndex=(int)($input['card_index']??-1);if($messageId===''||$cardIndex<0)mg_fail('Select an agent response card to send to review.',422);
    $stmt=$pdo->prepare("SELECT id,public_id,event_context_json FROM campaign_events WHERE merchant_user_id=? AND public_id=? AND event_type='merchant.agent_chat.assistant' LIMIT 1");$stmt->execute([$merchantId,$messageId]);$event=$stmt->fetch(PDO::FETCH_ASSOC);if(!is_array($event))mg_fail('Agent chat message was not found.',404);
    $ctx=mg_ai_chat_json($event['event_context_json']??null);$cards=mg_ai_chat_normalize_cards($ctx['cards']??[]);if(!isset($cards[$cardIndex]))mg_fail('Agent response card was not found.',404);$card=$cards[$cardIndex];if(!empty($card['review_item_id']))return ['card'=>$card,'state'=>mg_ai_chat_public_state($pdo,$merchantId)];
    $model=mg_ai_chat_catalog_model($pdo,mg_ai_chat_clean($ctx['model']??'',120));$scope=mg_ai_chat_clean($ctx['scope']??'overview',40)?:'overview';$actionKey=mg_ai_chat_infer_action_key($card);$risk=in_array($card['risk_level']??'low',['low','medium','high','critical'],true)?(string)$card['risk_level']:'low';$title=mg_ai_chat_clean($card['title']??'Agent chat recommendation',180);$reason=mg_ai_chat_clean($card['body']??'',1000);$payload=array_merge(mg_ai_chat_json($card['review_payload']??[]),['source'=>'merchant_agent_chat','source_chat_message_id'=>$messageId,'source_card_index'=>$cardIndex,'title'=>$title,'reason'=>$reason,'action_url'=>$card['action_url']??'']);
    try{$pdo->beginTransaction();$planPublicId=mg_ai_chat_uuid();$itemPublicId=mg_ai_chat_uuid();$contextJson=json_encode(['source'=>'merchant_agent_chat','chat_message_id'=>$messageId,'card_index'=>$cardIndex,'card'=>$card],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$fingerprint=hash('sha256',$messageId.'|'.$cardIndex.'|'.$title.'|'.$actionKey);
        $stmt=$pdo->prepare('INSERT INTO ai_merchant_plans (public_id,merchant_user_id,agent_id,provider_id,model_id,scope,merchant_goal,status,priority,summary,prompt_fingerprint,input_context_json,raw_response_json,input_tokens,output_tokens,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');$stmt->execute([$planPublicId,$merchantId,null,(int)$model['provider_id'],(int)$model['id'],$scope==='overview'?'all':$scope,$title,'review_ready','medium',$reason!==''?$reason:$title,$fingerprint,$contextJson,json_encode(['source'=>'merchant_agent_chat_bridge'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),0,0,$merchantId]);$planId=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare('INSERT INTO ai_merchant_plan_items (public_id,plan_id,sequence_no,action_key,target_type,target_reference,risk_level,requires_approval,confidence,title,reason,suggested_payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'recommended\',NOW(),NOW())');$stmt->execute([$itemPublicId,$planId,1,$actionKey,'agent_chat_card',$messageId,$risk,1,0.8,$title,$reason,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        $cards[$cardIndex]['review_plan_id']=$planPublicId;$cards[$cardIndex]['review_item_id']=$itemPublicId;$cards[$cardIndex]['bridgeable']=false;$ctx['cards']=$cards;$pdo->prepare('UPDATE campaign_events SET event_context_json=? WHERE id=? LIMIT 1')->execute([json_encode($ctx,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$event['id']]);
        if(function_exists('mg_audit'))mg_audit('merchant.agent_chat_sent_to_review',$merchantId,['plan_id'=>$planPublicId,'item_id'=>$itemPublicId,'action_key'=>$actionKey]);if(function_exists('mg_event'))mg_event($merchantId,'merchant.agent_chat.sent_to_review',['plan_id'=>$planPublicId,'item_id'=>$itemPublicId,'action_key'=>$actionKey]);
        $pdo->commit();return ['plan_id'=>$planPublicId,'item_id'=>$itemPublicId,'card'=>$cards[$cardIndex],'state'=>mg_ai_chat_public_state($pdo,$merchantId)];
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to send agent card to review: '.$error->getMessage(),500);}
}

function mg_ai_chat_send(PDO $pdo, array $user, array $input): array
{
    $merchantId=(int)$user['id'];$message=mg_ai_chat_clean($input['message']??'',2000);if($message==='')mg_fail('Enter a message for the merchant agent.',422);$scope=strtolower(mg_ai_chat_clean($input['scope']??'overview',40))?:'overview';if(!in_array($scope,mg_ai_chat_allowed_scopes(),true))$scope='overview';$days=max(7,min(365,(int)($input['days']??90)));
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $skillKeys = mg_agent_skill_keys($input['skill_keys'] ?? null);
    $model=mg_ai_merchant_find_anthropic_model($pdo,null);$provider=mg_ai_merchant_provider($pdo,(int)$model['provider_id']);mg_ai_enforce_rate_limits($pdo,$provider,$model,$merchantId,null);
    $history=array_slice(mg_ai_chat_recent_messages($pdo,$merchantId,12,$threadId),-12);$context=mg_ai_merchant_context($pdo,$user,['scope'=>$scope==='overview'?'all':$scope,'days'=>$days,'merchant_goal'=>$message]);
    $request=['model'=>(string)$model['model_key'],'max_tokens'=>max(512,min(2600,(int)($input['max_tokens']??1600))),'temperature'=>0.25,'system'=>mg_ai_chat_system_prompt()."\n\n".mg_agent_soul_prompt()."\n\n".mg_agent_skill_system_prompt(),'messages'=>[['role'=>'user','content'=>[['type'=>'text','text'=>json_encode(['merchant_message'=>$message,'scope'=>$scope,'review_window_days'=>$days,'allowed_action_urls'=>mg_ai_chat_allowed_links(),'recent_chat_history'=>$history,'merchant_operating_snapshot'=>$context,'enabled_skills'=>mg_agent_skill_prompt_context($skillKeys),'agent_profile'=>mg_agent_profile($pdo,$merchantId),'active_thread'=>$thread,'bridge_instruction'=>'When useful, include review_action_key and review_payload so the merchant can send a card to the Agent Review Queue.'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]]]]];
    try{$rawResponse=mg_anthropic_messages($request);$text=mg_anthropic_text_from_response($rawResponse);$decoded=mg_ai_chat_decode_agent_payload($text);$reply=mg_ai_chat_clean($decoded['reply']??$text,6000);if($reply==='')$reply='I reviewed the merchant workspace. I do not have a safe recommendation to create yet.';$cards=mg_ai_chat_normalize_cards($decoded['cards']??[]);$blocks=mg_agent_chat_normalize_blocks($decoded['blocks']??[]);if($blocks===[])$blocks=mg_agent_skill_fallback_blocks($message,$skillKeys,$context);mg_ai_merchant_record_usage_event($pdo,(int)$provider['id'],(int)$model['id'],$merchantId,null,'completed',$rawResponse,['source'=>'merchant_agent_chat','scope'=>$scope,'skills'=>$skillKeys,'thread_id'=>$threadId]);
        $pdo->beginTransaction();$meta=['scope'=>$scope,'thread_public_id'=>$threadId,'skills'=>$skillKeys];$userId=mg_ai_chat_record_message($pdo,$merchantId,'user',$message,[],$meta);$assistantId=mg_ai_chat_record_message($pdo,$merchantId,'assistant',$reply,$cards,$meta+['blocks'=>$blocks,'model'=>(string)$model['model_key']]);$pdo->commit();return ['user_message'=>['id'=>$userId,'role'=>'user','body'=>$message,'cards'=>[],'blocks'=>[],'scope'=>$scope,'thread_public_id'=>$threadId,'created_at'=>date('c')],'assistant_message'=>['id'=>$assistantId,'role'=>'assistant','body'=>$reply,'cards'=>$cards,'blocks'=>$blocks,'scope'=>$scope,'thread_public_id'=>$threadId,'model'=>(string)$model['model_key'],'created_at'=>date('c')],'state'=>mg_ai_chat_public_state($pdo,$merchantId)];
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_ai_merchant_record_usage_event($pdo,(int)$provider['id'],(int)$model['id'],$merchantId,null,'failed',[],['source'=>'merchant_agent_chat','scope'=>$scope,'error'=>$error->getMessage()]);mg_security_log('error','merchant.agent_chat.failed','Merchant agent chat failed.',['exception_class'=>$error::class,'scope'=>$scope],$merchantId);mg_fail('Unable to run merchant agent chat: '.$error->getMessage(),500);}
}
