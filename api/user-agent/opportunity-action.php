<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

mg_user_agent_api_run(static function () use ($user,$input): array {
    $pdo = mg_db();
    $userId = (int)$user['id'];
    $publicId = mg_personal_agent_text($input['opportunity_id'] ?? '',36);
    $token = mg_personal_agent_text($input['attribution_token'] ?? '',64);
    $action = strtolower(mg_personal_agent_text($input['action'] ?? '',50));
    if ($action === '') throw new InvalidArgumentException('Opportunity action is required.');

    if (in_array($action,['save','unsave','hide','restore'],true)) {
        if ($publicId === '') throw new InvalidArgumentException('Opportunity identifier is required.');
        $state = match ($action) {
            'save' => 'saved',
            'hide' => 'hidden',
            default => 'active',
        };
        $item = mg_personal_agent_opportunity_change_state($pdo,$userId,$publicId,$state);
        mg_audit('user_agent.opportunity_' . $action,'personal_agent_opportunity',['opportunity_id'=>$publicId,'state'=>$state],$userId);
        return ['opportunity'=>$item,'action'=>$action];
    }

    $row = mg_personal_agent_opportunity_find($pdo,$userId,$publicId,$token);
    $eventType = match ($action) {
        'view' => 'recommendation_viewed',
        'buy_self','open_product','open_campaign','view_merchant' => 'action_clicked',
        'send_gift' => 'gift_started',
        'join_campaign' => 'campaign_join_started',
        'followup' => 'followup_requested',
        'cart_added' => 'cart_added',
        'checkout_started' => 'checkout_started',
        'purchase_completed' => 'purchase_completed',
        'campaign_join_completed' => 'campaign_join_completed',
        default => throw new InvalidArgumentException('Unsupported opportunity action.'),
    };
    $metadata = [
        'action_type'=>$action,
        'order_public_id'=>$input['order_public_id'] ?? null,
        'campaign_public_id'=>$input['campaign_public_id'] ?? null,
        'product_version_public_id'=>$input['product_version_public_id'] ?? null,
        'page_path'=>mg_personal_agent_text($input['page_path'] ?? '',500),
        'referrer_path'=>mg_personal_agent_text($input['referrer_path'] ?? '',500),
        'client_context'=>is_array($input['client_context'] ?? null) ? $input['client_context'] : [],
    ];
    $idempotency = mg_personal_agent_nullable_text($input['idempotency_key'] ?? null,190);
    $event = mg_personal_agent_opportunity_event($pdo,$row,$eventType,$metadata,$idempotency);
    mg_audit('user_agent.opportunity_action','personal_agent_opportunity',[
        'opportunity_id'=>(string)$row['public_id'],'event_type'=>$eventType,'action_type'=>$action,
        'entity_type'=>(string)$row['entity_type'],'entity_id'=>(string)$row['entity_public_id'],
    ],$userId);
    mg_event('user_agent.opportunity.' . $eventType,[
        'opportunity_id'=>(string)$row['public_id'],'merchant_user_id'=>$row['merchant_user_id'] ? (int)$row['merchant_user_id'] : null,
        'entity_type'=>(string)$row['entity_type'],'entity_id'=>(string)$row['entity_public_id'],'action_type'=>$action,
    ],$userId);
    return ['opportunity'=>mg_personal_agent_opportunity_public($row),'event'=>$event,'action'=>$action];
});
