<?php
declare(strict_types=1);

function mg_personal_agent_recovery_card(array $opportunity, string $mode = 'continue'): array
{
    $entityType = mg_personal_agent_opportunity_entity_type($opportunity['entity_type'] ?? 'product');
    $public = isset($opportunity['attribution_token']) ? mg_personal_agent_opportunity_public($opportunity) : $opportunity;
    $token = (string)($public['attribution_token'] ?? '');
    $destination = (string)($public['destination_url'] ?? '');
    $title = (string)($public['title'] ?? 'Saved opportunity');
    $context = is_array($public['source_context'] ?? null) ? $public['source_context'] : [];
    $primaryAction = $entityType === 'campaign' ? 'join_campaign' : ($entityType === 'merchant' ? 'view_merchant' : 'buy_self');
    $primaryLabel = $entityType === 'campaign' ? 'Continue campaign' : ($entityType === 'merchant' ? 'View merchant' : 'Continue purchase');
    $url = mg_personal_agent_opportunity_url($destination,$token,$primaryAction);
    $actions = [
        ['key'=>$primaryAction,'label'=>$primaryLabel,'url'=>$url,'primary'=>true],
        ['key'=>'remind','label'=>'Remind me','url'=>''],
        ['key'=>'save','label'=>(string)($public['state'] ?? '') === 'saved' ? 'Saved' : 'Save','url'=>''],
        ['key'=>'hide','label'=>'Hide','url'=>''],
    ];
    if ($entityType === 'product') {
        array_splice($actions,1,0,[['key'=>'send_gift','label'=>'Send as a gift','url'=>$url . (str_contains($url,'?') ? '&' : '?') . 'purchase_intent=gift']]);
    }
    $body = match ($mode) {
        'saved' => 'Saved by your Personal Agent so you can return when the timing is right.',
        'resume' => 'You started an action for this opportunity but have not completed it.',
        'recent' => 'One of your most recent Personal Agent recommendations.',
        'reminder' => 'A follow-up reminder is scheduled for this opportunity.',
        default => 'Continue this Personal Agent opportunity.',
    };
    return [
        'type'=>'marketplace_' . $entityType,
        'result_kind'=>$entityType,
        'id'=>(string)($public['entity_id'] ?? ''),
        'eyebrow'=>ucfirst(str_replace('_',' ',$entityType)),
        'title'=>$title,
        'body'=>$body,
        'image_url'=>'',
        'image_alt'=>$title,
        'price'=>(string)($context['price'] ?? ''),
        'merchant_name'=>(string)($context['merchant_name'] ?? ''),
        'url'=>$url,
        'url_label'=>$primaryLabel,
        'secondary_url'=>'',
        'secondary_label'=>'',
        'meta'=>array_values(array_filter([
            !empty($context['merchant_name']) ? ['label'=>'Merchant','value'=>(string)$context['merchant_name']] : null,
            !empty($public['updated_at']) ? ['label'=>'Last activity','value'=>(string)$public['updated_at']] : null,
        ])),
        'action'=>'open_marketplace_result',
        'action_label'=>$primaryLabel,
        'risk_level'=>'low',
        'opportunity_id'=>(string)($public['id'] ?? ''),
        'attribution_token'=>$token,
        'opportunity_state'=>(string)($public['state'] ?? 'active'),
        'merchant_user_id'=>$public['merchant_user_id'] ?? null,
        'actions'=>$actions,
    ];
}

function mg_personal_agent_recovery_intent_items(PDO $pdo, int $userId, string $intent, int $limit = 6): array
{
    $limit = max(1,min(10,$limit));
    if ($intent === 'saved') return mg_personal_agent_opportunity_list($pdo,$userId,'saved',$limit);
    if ($intent === 'resume') {
        $context = mg_personal_agent_recovery_context($pdo,$userId);
        return array_slice($context['unfinished_opportunities'] ?? [],0,$limit);
    }
    $stmt = $pdo->prepare("SELECT * FROM personal_agent_opportunities WHERE user_id=? AND state<>'hidden' ORDER BY COALESCE(last_action_at,updated_at) DESC,id DESC LIMIT {$limit}");
    $stmt->execute([$userId]);
    return array_map('mg_personal_agent_opportunity_public',$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_personal_agent_recovery_persist_response(PDO $pdo, int $userId, array &$result, string $body, array $cards, string $intent): void
{
    $messageId = (string)($result['assistant_message']['id'] ?? '');
    $result['assistant_message']['body'] = $body;
    $result['assistant_message']['cards'] = $cards;
    $result['recovery_intent'] = $intent;
    if ($messageId === '') return;
    try {
        $pdo->prepare("UPDATE user_agent_messages SET body=?,cards_json=?,context_json=JSON_SET(COALESCE(context_json,JSON_OBJECT()),'$.recovery_intent',?) WHERE owner_user_id=? AND public_id=? AND role='assistant'")
            ->execute([$body,$cards !== [] ? mg_personal_agent_json_encode($cards) : null,$intent,$userId,$messageId]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning','user_agent.recovery_response_persist_failed','A deterministic opportunity recovery response could not be persisted.',['exception_type'=>$error::class],$userId);
        }
    }
}

function mg_personal_agent_recovery_start_response(PDO $pdo, int $userId, array $input, string $intent): array
{
    mg_personal_agent_require_schema($pdo);
    $message = mg_personal_agent_text($input['message'] ?? '',2000);
    if ($message === '') throw new InvalidArgumentException('Enter a message for the Personal Gifting Agent.');
    $context = mg_personal_agent_resolve_context($pdo,$userId,(string)($input['context_type'] ?? 'none'),(string)($input['context_id'] ?? ''));
    $thread = mg_personal_agent_thread($pdo,$userId,mg_personal_agent_text($input['thread_id'] ?? '',80),$context);
    $publicContext = mg_personal_agent_public_context($context);
    $userMessage = mg_personal_agent_store_message($pdo,$userId,(int)$thread['internal_id'],'user',$message,[],$publicContext);
    $assistant = mg_personal_agent_store_message($pdo,$userId,(int)$thread['internal_id'],'assistant','Reviewing your saved opportunity activity…',[],array_merge($publicContext,['recovery_intent'=>$intent]));
    mg_audit('user_agent.recovery_command','user_agent_thread',['thread_id'=>$thread['id'],'intent'=>$intent],$userId);
    return [
        'thread'=>['id'=>$thread['id'],'title'=>$thread['title']],
        'user_message'=>$userMessage,
        'assistant_message'=>$assistant,
        'context'=>$publicContext,
        'used_ai'=>false,
        'model_key'=>'deterministic_recovery',
    ];
}

function mg_personal_agent_chat_with_recovery_response(PDO $pdo, int $userId, array $input): array
{
    $message = mg_personal_agent_text($input['message'] ?? '',2000);
    $intent = mg_personal_agent_recovery_intent($message);
    if ($intent === '' || !mg_personal_agent_recovery_schema_ready($pdo)) {
        return mg_personal_agent_chat_with_opportunity_attribution($pdo,$userId,$input);
    }
    $result = mg_personal_agent_recovery_start_response($pdo,$userId,$input,$intent);
    $threadId = (string)($result['thread']['id'] ?? '');

    if ($intent === 'remind') {
        $opportunity = mg_personal_agent_recovery_latest_opportunity($pdo,$userId,$threadId,'recent');
        if ($opportunity === []) $opportunity = mg_personal_agent_recovery_latest_opportunity($pdo,$userId,'','saved');
        if ($opportunity === []) $opportunity = mg_personal_agent_recovery_latest_opportunity($pdo,$userId,'','recent');
        if ($opportunity === []) {
            mg_personal_agent_recovery_persist_response($pdo,$userId,$result,'I do not have a recent opportunity to attach that reminder to yet. Save or open a recommendation, then ask me again.',[],$intent);
            return $result;
        }
        $preferences = mg_personal_agent_recovery_preferences($pdo,$userId);
        $when = mg_personal_agent_recovery_parse_remind_at($message,(string)$preferences['timezone']);
        $followup = mg_personal_agent_recovery_schedule($pdo,$opportunity,'manual',$when,[
            'source'=>'agent_chat','thread_id'=>$threadId,'request'=>mb_substr($message,0,500),
        ],'manual:' . (string)$opportunity['public_id'] . ':' . $when->setTimezone(new DateTimeZone('UTC'))->format('YmdHi'));
        $body = 'Reminder scheduled for ' . $when->format('M j, Y \a\t g:i A T') . ' for “' . (string)$opportunity['title'] . '”. You can snooze, dismiss, or mute it from Reminders.';
        $card = mg_personal_agent_recovery_card($opportunity,'reminder');
        $card['followup_id'] = (string)($followup['id'] ?? '');
        mg_personal_agent_recovery_persist_response($pdo,$userId,$result,$body,[$card],$intent);
        return $result;
    }

    $items = mg_personal_agent_recovery_intent_items($pdo,$userId,$intent,6);
    if ($items === []) {
        $empty = match ($intent) {
            'saved' => 'You do not have any saved Personal Agent opportunities yet.',
            'resume' => 'I did not find an unfinished cart, checkout, gift, or campaign action.',
            default => 'I did not find a recent opportunity to reopen.',
        };
        mg_personal_agent_recovery_persist_response($pdo,$userId,$result,$empty,[],$intent);
        return $result;
    }

    if ($intent === 'similar') {
        $source = $items[0];
        $query = trim((string)($source['title'] ?? 'local gift'));
        $card = mg_personal_agent_recovery_card($source,'recent');
        $card['title'] = 'Find options similar to ' . $query;
        $card['body'] = 'Use this recent recommendation as the starting point for another local option.';
        $searchUrl = '/discover.php?q=' . rawurlencode($query);
        $card['url'] = mg_personal_agent_opportunity_url($searchUrl,(string)$source['attribution_token'],'find_similar');
        $card['actions'] = [
            ['key'=>'open_product','label'=>'Find similar','url'=>$card['url'],'primary'=>true],
            ['key'=>'remind','label'=>'Remind me','url'=>''],
            ['key'=>'hide','label'=>'Hide','url'=>''],
        ];
        mg_personal_agent_recovery_persist_response($pdo,$userId,$result,'I used your most recent opportunity as the starting point. Here is a direct search for similar local options.',[$card],$intent);
        return $result;
    }

    $mode = $intent === 'resume' ? 'resume' : ($intent === 'saved' ? 'saved' : 'recent');
    $cards = array_map(static fn(array $item): array => mg_personal_agent_recovery_card($item,$mode),$items);
    $body = match ($intent) {
        'saved' => 'Here are your saved Personal Agent opportunities. Nothing will be purchased or sent without your review.',
        'resume' => 'Here are the unfinished opportunities I found. You can continue, schedule a reminder, or dismiss them.',
        default => 'Here are your most recent Personal Agent opportunities.',
    };
    mg_personal_agent_recovery_persist_response($pdo,$userId,$result,$body,$cards,$intent);
    return $result;
}
