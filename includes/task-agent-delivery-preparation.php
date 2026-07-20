<?php
declare(strict_types=1);

require_once __DIR__ . '/personal-agent/workflows.php';
require_once __DIR__ . '/task-agent-plan-selection.php';

function mg_task_agent_delivery_text(mixed $value, int $limit = 190): string
{
    return trim(mb_substr((string)$value, 0, max(1, $limit)));
}

function mg_task_agent_delivery_schema_ready(PDO $pdo): bool
{
    foreach (['user_gifting_schedules','user_recipient_data_requests','multi_agent_shortlist_items'] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_task_agent_delivery_require_schema(PDO $pdo): void
{
    if (!mg_task_agent_delivery_schema_ready($pdo)) {
        throw new RuntimeException('Task Agent Phase 3 delivery-preparation migrations are required.');
    }
}

function mg_task_agent_delivery_plan_record(
    PDO $pdo,
    int $userId,
    int $agentId,
    string $planPublicId,
    bool $forUpdate = false
): array {
    mg_task_agent_delivery_require_schema($pdo);
    $planPublicId = mg_task_agent_delivery_text($planPublicId, 80);
    if ($planPublicId === '') throw new InvalidArgumentException('A selected gift plan is required.');

    $sql = "SELECT p.id plan_id,p.public_id plan_public_id,p.title plan_title,p.target_date,p.status plan_status,
        p.user_contact_id,p.contact_user_id,p.list_id,
        c.public_id contact_public_id,c.display_name contact_name,
        CASE WHEN c.address_line_1 IS NOT NULL AND c.city IS NOT NULL AND c.state_region IS NOT NULL AND c.postal_code IS NOT NULL THEN 1 ELSE 0 END has_delivery_address,
        CASE WHEN c.gift_preferences IS NOT NULL OR c.interests IS NOT NULL OR c.preferred_categories IS NOT NULL THEN 1 ELSE 0 END has_gift_preferences,
        pp.public_id linked_user_public_id,COALESCE(pp.display_name,u.display_name,u.full_name) linked_user_name,
        l.public_id list_public_id,l.name list_name,
        s.public_id shortlist_id,cp.public_id product_public_id,cpv.public_id product_version_public_id
        FROM user_gifting_plans p
        INNER JOIN multi_agent_shortlist_items s ON s.plan_id=p.id AND s.owner_user_id=p.owner_user_id
          AND s.agent_id=? AND s.status='selected'
        INNER JOIN catalog_products cp ON cp.id=s.product_id AND cp.current_version_id=s.product_version_id AND cp.status='published'
        INNER JOIN catalog_product_versions cpv ON cpv.id=s.product_version_id AND cpv.version_status='published'
        LEFT JOIN user_contacts c ON c.id=p.user_contact_id AND c.owner_user_id=p.owner_user_id
        LEFT JOIN users u ON u.id=p.contact_user_id AND u.status='active'
        LEFT JOIN public_profiles pp ON pp.user_id=u.id
        LEFT JOIN user_contact_lists l ON l.id=p.list_id AND l.owner_user_id=p.owner_user_id
        WHERE p.owner_user_id=? AND p.public_id=? AND p.status IN ('draft','planned','ready')
          AND EXISTS(SELECT 1 FROM catalog_product_version_locations cpvl
            INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
            WHERE cpvl.product_version_id=s.product_version_id AND cpvl.availability_status='available')
        LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agentId, $userId, $planPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Selected gift plan not found or no longer delivery-ready.');
    return $row;
}

function mg_task_agent_delivery_schedule_for_plan(PDO $pdo, int $userId, int $planId): ?array
{
    if (!mg_task_agent_delivery_schema_ready($pdo)) return null;
    $stmt = $pdo->prepare("SELECT public_id,scheduled_for,timezone,status,execution_mode,approval_required,prepared_at,completed_at,cancelled_at,created_at,updated_at
        FROM user_gifting_schedules WHERE owner_user_id=? AND plan_id=? AND status<>'cancelled'
        ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $planId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
        'id'=>(string)$row['public_id'],
        'scheduled_for'=>(string)$row['scheduled_for'],
        'timezone'=>(string)$row['timezone'],
        'status'=>(string)$row['status'],
        'execution_mode'=>(string)$row['execution_mode'],
        'approval_required'=>(bool)$row['approval_required'],
        'prepared_at'=>$row['prepared_at'] ?: null,
        'completed_at'=>$row['completed_at'] ?: null,
        'cancelled_at'=>$row['cancelled_at'] ?: null,
        'created_at'=>$row['created_at'],
        'updated_at'=>$row['updated_at'],
    ];
}

function mg_task_agent_delivery_record_projection(PDO $pdo, int $userId, array $row): array
{
    $product = mg_task_agent_shortlist_product_projection(
        mg_public_product_load($pdo, (string)$row['product_public_id'], null)
    );
    $recipientType = 'none';
    $recipientId = '';
    $recipientName = '';
    if (!empty($row['contact_public_id'])) {
        $recipientType = 'contact';
        $recipientId = (string)$row['contact_public_id'];
        $recipientName = (string)($row['contact_name'] ?? 'Saved contact');
    } elseif (!empty($row['linked_user_public_id'])) {
        $recipientType = 'linked_user';
        $recipientId = (string)$row['linked_user_public_id'];
        $recipientName = (string)($row['linked_user_name'] ?? 'Connected recipient');
    } elseif (!empty($row['list_public_id'])) {
        $recipientType = 'list';
        $recipientId = (string)$row['list_public_id'];
        $recipientName = (string)($row['list_name'] ?? 'Recipient list');
    }

    return [
        'plan'=>[
            'id'=>(string)$row['plan_public_id'],
            'title'=>(string)$row['plan_title'],
            'target_date'=>$row['target_date'] ?: null,
            'status'=>(string)$row['plan_status'],
        ],
        'shortlist_id'=>(string)$row['shortlist_id'],
        'product'=>$product,
        'recipient'=>[
            'type'=>$recipientType,
            'id'=>$recipientId,
            'name'=>$recipientName,
            'linked_user'=>$recipientType === 'linked_user',
        ],
        'readiness'=>[
            'selected_product'=>true,
            'recipient_identified'=>$recipientType !== 'none',
            'delivery_address_available'=>(bool)$row['has_delivery_address'],
            'gift_preferences_available'=>(bool)$row['has_gift_preferences'],
            'address_value_exposed'=>false,
        ],
        'schedule'=>mg_task_agent_delivery_schedule_for_plan($pdo, $userId, (int)$row['plan_id']),
    ];
}

function mg_task_agent_delivery_preparations(PDO $pdo, int $userId, int $agentId, int $limit = 20): array
{
    if (!mg_task_agent_delivery_schema_ready($pdo)) return [];
    $stmt = $pdo->prepare("SELECT p.public_id
        FROM user_gifting_plans p
        INNER JOIN multi_agent_shortlist_items s ON s.plan_id=p.id AND s.owner_user_id=p.owner_user_id
          AND s.agent_id=? AND s.status='selected'
        WHERE p.owner_user_id=? AND p.status IN ('draft','planned','ready')
        ORDER BY COALESCE(s.selected_at,p.updated_at) DESC,p.id DESC LIMIT ".max(1,min(50,$limit)));
    $stmt->execute([$agentId,$userId]);
    $items=[];
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $planPublicId){
        try{
            $row=mg_task_agent_delivery_plan_record($pdo,$userId,$agentId,(string)$planPublicId);
            $items[]=mg_task_agent_delivery_record_projection($pdo,$userId,$row);
        }catch(Throwable){
            continue;
        }
    }
    return $items;
}

function mg_task_agent_delivery_card(array $preparation, bool $reviewSchedule = false, array $scheduleDraft = []): array
{
    $plan=is_array($preparation['plan']??null)?$preparation['plan']:[];
    $product=is_array($preparation['product']??null)?$preparation['product']:[];
    $recipient=is_array($preparation['recipient']??null)?$preparation['recipient']:[];
    $schedule=is_array($preparation['schedule']??null)?$preparation['schedule']:null;
    $title=(string)($product['title']??'Selected gift');
    $body='Prepared for '.((string)($recipient['name']??'the selected recipient')).' through '.((string)($plan['title']??'the gift plan')).'.';

    $card=[
        'type'=>'delivery_preparation',
        'title'=>$title,
        'body'=>$body,
        'plan'=>$plan,
        'product'=>mg_task_agent_plan_selection_product_snapshot($product),
        'recipient'=>[
            'type'=>(string)($recipient['type']??'none'),
            'name'=>(string)($recipient['name']??''),
            'linked_user'=>!empty($recipient['linked_user']),
        ],
        'readiness'=>is_array($preparation['readiness']??null)?$preparation['readiness']:[],
        'schedule'=>$schedule,
        'approval_required'=>true,
        'execution_mode'=>'prepare_only',
    ];

    if($reviewSchedule){
        $card['action']='create_delivery_schedule';
        $card['action_label']='Create send-later preparation';
        $card['review_payload']=[
            'plan_id'=>(string)($plan['id']??''),
            'scheduled_for'=>(string)($scheduleDraft['scheduled_for']??''),
            'timezone'=>(string)($scheduleDraft['timezone']??'UTC'),
        ];
        return $card;
    }

    if($schedule){
        $card['action']='manage_delivery_schedule';
        $card['action_label']='Manage preparation';
        $card['review_payload']=['schedule_id'=>(string)$schedule['id'],'plan_id'=>(string)($plan['id']??'')];
    }else{
        $card['action']='seed_prompt';
        $card['action_label']='Choose send-later time';
        $card['prompt']='Prepare '.((string)($plan['title']??'this gift plan')).' for YYYY-MM-DD at 09:00 UTC.';
        $card['review_payload']=[];
    }
    return $card;
}

function mg_task_agent_delivery_request_card(array $preparation, array $scopes): array
{
    $recipient=is_array($preparation['recipient']??null)?$preparation['recipient']:[];
    $labels=[];
    foreach($scopes as $scope){
        $labels[] = match($scope){
            'profile.address'=>'delivery address',
            'profile.gift_preferences'=>'gift preferences',
            'profile.birthdate'=>'birthday',
            default=>'gifting information',
        };
    }
    return [
        'type'=>'recipient_permission_request',
        'title'=>'Request '.implode(' and ',$labels),
        'body'=>'The recipient chooses exactly what to share and can revoke access later. No address value is shown in agent chat.',
        'action'=>'create_recipient_request',
        'action_label'=>'Send permission request',
        'review_payload'=>[
            'plan_id'=>(string)($preparation['plan']['id']??''),
            'scopes'=>array_values($scopes),
            'message'=>'Please share the selected information so I can prepare your Microgifter gift.',
        ],
        'recipient'=>['name'=>(string)($recipient['name']??''),'linked_user'=>!empty($recipient['linked_user'])],
        'approval_required'=>true,
    ];
}

function mg_task_agent_delivery_parse_datetime(string $message): ?array
{
    if(!preg_match('/\b(20\d{2}-\d{2}-\d{2})(?:[ T]+(\d{1,2}:\d{2}))?(?:\s+([A-Za-z_]+\/[A-Za-z_]+|UTC))?\b/',$message,$match)) return null;
    $date=$match[1];
    $time=$match[2]??'09:00';
    $timezone=$match[3]??'UTC';
    try{
        $zone=new DateTimeZone($timezone);
        $local=new DateTimeImmutable($date.' '.$time,$zone);
    }catch(Throwable){
        throw new InvalidArgumentException('Choose a valid date, time, and timezone.');
    }
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $utc=$local->setTimezone(new DateTimeZone('UTC'));
    if($utc <= $now) throw new InvalidArgumentException('Send-later preparation must be scheduled in the future.');
    if($utc > $now->modify('+2 years')) throw new InvalidArgumentException('Send-later preparation cannot be more than two years away.');
    return ['scheduled_for'=>$utc->format('Y-m-d H:i:s'),'timezone'=>$timezone];
}

function mg_task_agent_delivery_create_schedule(PDO $pdo,int $userId,int $agentId,array $input): array
{
    $planId=mg_task_agent_delivery_text($input['plan_id']??'',80);
    mg_task_agent_delivery_plan_record($pdo,$userId,$agentId,$planId);
    $scheduledFor=mg_personal_agent_datetime($input['scheduled_for']??'');
    $timezone=mg_task_agent_delivery_text($input['timezone']??'UTC',64)?:'UTC';
    try{$zone=new DateTimeZone($timezone);$local=new DateTimeImmutable($scheduledFor,new DateTimeZone('UTC'));$local->setTimezone($zone);}catch(Throwable){throw new InvalidArgumentException('Choose a valid timezone.');}
    if(strtotime($scheduledFor)<=time())throw new InvalidArgumentException('Send-later preparation must be scheduled in the future.');
    if(strtotime($scheduledFor)>strtotime('+2 years'))throw new InvalidArgumentException('Send-later preparation cannot be more than two years away.');
    $existing=mg_task_agent_delivery_schedule_for_plan($pdo,$userId,(int)mg_task_agent_delivery_plan_record($pdo,$userId,$agentId,$planId)['plan_id']);
    if($existing && !in_array((string)$existing['status'],['completed','cancelled'],true))throw new RuntimeException('This gift plan already has an active send-later preparation.');
    $schedule=mg_personal_workflows_create_schedule($pdo,$userId,['plan_id'=>$planId,'scheduled_for'=>$scheduledFor,'timezone'=>$timezone]);
    mg_audit('multi_agent.delivery_schedule_created','gifting_plan',['agent_id'=>$agentId,'plan_id'=>$planId,'schedule_id'=>$schedule['id'],'execution_mode'=>'prepare_only','used_ai'=>false],$userId);
    return $schedule;
}

function mg_task_agent_delivery_update_schedule(PDO $pdo,int $userId,int $agentId,string $scheduleId,string $action): array
{
    mg_task_agent_delivery_require_schema($pdo);
    $allowed=['approve','pause','resume','prepare','cancel'];
    if(!in_array($action,$allowed,true))throw new InvalidArgumentException('That delivery-preparation action is not allowed.');
    $stmt=$pdo->prepare("SELECT s.public_id,p.public_id plan_public_id
        FROM user_gifting_schedules s
        INNER JOIN user_gifting_plans p ON p.id=s.plan_id AND p.owner_user_id=s.owner_user_id
        INNER JOIN multi_agent_shortlist_items ms ON ms.plan_id=p.id AND ms.owner_user_id=p.owner_user_id
          AND ms.agent_id=? AND ms.status='selected'
        WHERE s.owner_user_id=? AND s.public_id=? LIMIT 1");
    $stmt->execute([$agentId,$userId,mg_task_agent_delivery_text($scheduleId,80)]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Delivery preparation not found for this agent.');
    $schedule=mg_personal_workflows_update_schedule($pdo,$userId,(string)$row['public_id'],$action);
    mg_audit('multi_agent.delivery_schedule_updated','gifting_plan',['agent_id'=>$agentId,'plan_id'=>(string)$row['plan_public_id'],'schedule_id'=>(string)$row['public_id'],'action'=>$action,'commerce_executed'=>false,'used_ai'=>false],$userId);
    return $schedule;
}

function mg_task_agent_delivery_create_recipient_request(PDO $pdo,int $userId,int $agentId,array $input): array
{
    $planId=mg_task_agent_delivery_text($input['plan_id']??'',80);
    $row=mg_task_agent_delivery_plan_record($pdo,$userId,$agentId,$planId);
    if(empty($row['linked_user_public_id']))throw new RuntimeException('Recipient permission requests require a mutually connected Microgifter user.');
    $scopes=mg_personal_workflows_request_scopes($input['scopes']??[]);
    if($scopes===[])throw new InvalidArgumentException('Choose address, preferences, or birthday information to request.');
    $message=mg_task_agent_delivery_text($input['message']??'Please share the selected information so I can prepare your Microgifter gift.',1000);
    $request=mg_personal_workflows_create_data_request($pdo,$userId,[
        'context_type'=>'linked_user',
        'context_id'=>(string)$row['linked_user_public_id'],
        'scopes'=>$scopes,
        'message'=>$message,
    ]);
    mg_audit('multi_agent.recipient_permission_requested','gifting_plan',['agent_id'=>$agentId,'plan_id'=>$planId,'request_id'=>$request['id'],'scopes'=>$scopes,'used_ai'=>false],$userId);
    return $request;
}

function mg_task_agent_delivery_for_model(array $items): array
{
    return array_map(static function(array $item):array{
        return [
            'plan_title'=>(string)($item['plan']['title']??''),
            'product_title'=>(string)($item['product']['title']??''),
            'recipient_type'=>(string)($item['recipient']['type']??'none'),
            'recipient_name'=>(string)($item['recipient']['name']??''),
            'selected_product'=>!empty($item['readiness']['selected_product']),
            'recipient_identified'=>!empty($item['readiness']['recipient_identified']),
            'delivery_address_available'=>!empty($item['readiness']['delivery_address_available']),
            'gift_preferences_available'=>!empty($item['readiness']['gift_preferences_available']),
            'schedule_status'=>(string)($item['schedule']['status']??''),
            'scheduled_for'=>(string)($item['schedule']['scheduled_for']??''),
            'execution_mode'=>'prepare_only',
        ];
    },array_slice($items,0,8));
}
