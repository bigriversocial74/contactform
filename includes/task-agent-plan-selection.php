<?php
declare(strict_types=1);

function mg_task_agent_plan_selection_text(mixed $value, int $limit = 190): string
{
    return trim(mb_substr((string)$value, 0, max(1, $limit)));
}

function mg_task_agent_plan_selection_product_snapshot(array $product): array
{
    return [
        'product_id' => (string)($product['id'] ?? ''),
        'product_version_id' => (string)($product['version_id'] ?? ''),
        'title' => mg_task_agent_plan_selection_text($product['title'] ?? 'Selected product'),
        'merchant_name' => mg_task_agent_plan_selection_text($product['merchant']['name'] ?? 'Local merchant'),
        'value_cents' => (int)($product['value_cents'] ?? 0),
        'currency' => mg_task_agent_plan_selection_text($product['currency'] ?? 'USD', 3) ?: 'USD',
        'url' => (string)($product['url'] ?? ''),
        'cover_url' => (string)($product['cover_url'] ?? ''),
        'purchase_available' => !empty($product['purchase_available']),
    ];
}

function mg_task_agent_plan_selection_card(array $selection, bool $confirmation = false): array
{
    $product = is_array($selection['product'] ?? null) ? $selection['product'] : [];
    $plan = is_array($selection['plan'] ?? null) ? $selection['plan'] : [];
    $snapshot = mg_task_agent_plan_selection_product_snapshot($product);

    if ($confirmation) {
        return [
            'type' => 'plan_product_selection',
            'title' => 'Add ' . ($snapshot['title'] ?: 'this product') . ' to ' . ((string)($plan['title'] ?? '') ?: 'the gift plan'),
            'body' => 'This updates the reviewable gift plan only. It does not add anything to the cart or purchase the product.',
            'action' => 'select_plan_product',
            'action_label' => 'Add to gift plan',
            'review_payload' => [
                'shortlist_id' => (string)($selection['shortlist_id'] ?? ''),
                'plan_id' => (string)($plan['id'] ?? ''),
            ],
            'product' => $snapshot,
            'plan' => ['id'=>(string)($plan['id'] ?? ''),'title'=>(string)($plan['title'] ?? '')],
            'approval_required' => true,
        ];
    }

    return [
        'type' => 'plan_product_selection',
        'title' => $snapshot['title'] ?: 'Selected gift product',
        'body' => 'Selected for ' . ((string)($plan['title'] ?? '') ?: 'the gift plan') . '. Review the canonical product before adding it to the cart.',
        'action' => 'cart_handoff',
        'action_label' => 'Add to cart',
        'review_payload' => [
            'shortlist_id' => (string)($selection['shortlist_id'] ?? ''),
            'plan_id' => (string)($plan['id'] ?? ''),
            'product_version_id' => $snapshot['product_version_id'],
            'quantity' => 1,
        ],
        'product' => $snapshot,
        'plan' => ['id'=>(string)($plan['id'] ?? ''),'title'=>(string)($plan['title'] ?? '')],
        'approval_required' => true,
    ];
}

function mg_task_agent_plan_selections(PDO $pdo, int $userId, int $agentId, int $limit = 20): array
{
    if (!mg_task_agent_shortlist_schema_ready($pdo)) return [];
    $stmt = $pdo->prepare("SELECT s.public_id shortlist_id,cp.public_id product_public_id,p.public_id plan_public_id,p.title plan_title
        FROM multi_agent_shortlist_items s
        INNER JOIN catalog_products cp ON cp.id=s.product_id AND cp.current_version_id=s.product_version_id AND cp.status='published'
        INNER JOIN catalog_product_versions cpv ON cpv.id=s.product_version_id AND cpv.version_status='published'
        INNER JOIN user_gifting_plans p ON p.id=s.plan_id AND p.owner_user_id=s.owner_user_id AND p.status IN ('draft','planned','ready')
        WHERE s.owner_user_id=? AND s.agent_id=? AND s.status='selected'
          AND EXISTS(SELECT 1 FROM catalog_product_version_locations cpvl
            INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
            WHERE cpvl.product_version_id=s.product_version_id AND cpvl.availability_status='available')
        ORDER BY s.selected_at DESC,s.id DESC LIMIT ".max(1,min(50,$limit)));
    $stmt->execute([$userId,$agentId]);
    $items=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        try{$product=mg_task_agent_shortlist_product_projection(mg_public_product_load($pdo,(string)$row['product_public_id'],null));}
        catch(Throwable){continue;}
        $items[]=[
            'shortlist_id'=>(string)$row['shortlist_id'],
            'plan'=>['id'=>(string)$row['plan_public_id'],'title'=>(string)$row['plan_title']],
            'product'=>$product,
        ];
    }
    return $items;
}

function mg_task_agent_plan_selection_context_public_id(array $row): string
{
    foreach (['contact_public_id','linked_user_public_id','list_public_id'] as $key) {
        if (!empty($row[$key])) return (string)$row[$key];
    }
    return '';
}

function mg_task_agent_select_shortlist_for_plan(PDO $pdo, int $userId, int $agentId, string $shortlistId, string $planId): array
{
    mg_task_agent_shortlist_require_schema($pdo);
    $shortlistId=mg_task_agent_plan_selection_text($shortlistId,80);
    $planId=mg_task_agent_plan_selection_text($planId,80);
    if($shortlistId===''||$planId==='')throw new InvalidArgumentException('A shortlist item and gift plan are required.');

    $pdo->beginTransaction();
    try{
        $shortlistStmt=$pdo->prepare("SELECT s.id,s.public_id,s.product_id,s.product_version_id,s.recipient_context_json,cp.public_id product_public_id
            FROM multi_agent_shortlist_items s
            INNER JOIN catalog_products cp ON cp.id=s.product_id AND cp.current_version_id=s.product_version_id AND cp.status='published'
            INNER JOIN catalog_product_versions cpv ON cpv.id=s.product_version_id AND cpv.version_status='published'
            WHERE s.public_id=? AND s.owner_user_id=? AND s.agent_id=? AND s.status IN ('active','selected')
              AND EXISTS(SELECT 1 FROM catalog_product_version_locations cpvl
                INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
                WHERE cpvl.product_version_id=s.product_version_id AND cpvl.availability_status='available')
            LIMIT 1 FOR UPDATE");
        $shortlistStmt->execute([$shortlistId,$userId,$agentId]);
        $shortlist=$shortlistStmt->fetch(PDO::FETCH_ASSOC);
        if(!$shortlist)throw new RuntimeException('The shortlisted product is no longer available.');

        $planStmt=$pdo->prepare("SELECT p.id,p.public_id,p.title,p.recommendation_json,p.status,
            c.public_id contact_public_id,pp.public_id linked_user_public_id,l.public_id list_public_id
            FROM user_gifting_plans p
            LEFT JOIN user_contacts c ON c.id=p.user_contact_id AND c.owner_user_id=p.owner_user_id
            LEFT JOIN users u ON u.id=p.contact_user_id
            LEFT JOIN public_profiles pp ON pp.user_id=u.id
            LEFT JOIN user_contact_lists l ON l.id=p.list_id AND l.owner_user_id=p.owner_user_id
            WHERE p.public_id=? AND p.owner_user_id=? AND p.status IN ('draft','planned','ready') LIMIT 1 FOR UPDATE");
        $planStmt->execute([$planId,$userId]);
        $plan=$planStmt->fetch(PDO::FETCH_ASSOC);
        if(!$plan)throw new RuntimeException('Gift plan not found or no longer editable.');

        $recipient=json_decode((string)($shortlist['recipient_context_json']??''),true);
        $recipientId=is_array($recipient)?(string)($recipient['contact_id']??''):'';
        $planContextId=mg_task_agent_plan_selection_context_public_id($plan);
        if($recipientId!==''&&$planContextId!==''&&!hash_equals($recipientId,$planContextId)){
            throw new RuntimeException('The shortlisted product belongs to a different recipient context.');
        }

        $product=mg_task_agent_shortlist_product_projection(mg_public_product_load($pdo,(string)$shortlist['product_public_id'],null));
        $snapshot=mg_task_agent_plan_selection_product_snapshot($product);
        $recommendation=json_decode((string)($plan['recommendation_json']??''),true);
        if(!is_array($recommendation))$recommendation=[];
        $recommendation['selected_product']=$snapshot;
        $recommendation['selected_product_source']='task_agent_shortlist';
        $recommendation['selected_at']=gmdate('Y-m-d H:i:s');

        $pdo->prepare("UPDATE multi_agent_shortlist_items SET status='active',plan_id=NULL,selected_at=NULL,updated_at=NOW()
            WHERE owner_user_id=? AND agent_id=? AND plan_id=? AND status='selected' AND id<>?")
            ->execute([$userId,$agentId,(int)$plan['id'],(int)$shortlist['id']]);
        $pdo->prepare("UPDATE multi_agent_shortlist_items SET status='selected',plan_id=?,selected_at=NOW(),updated_at=NOW()
            WHERE id=? AND owner_user_id=? AND agent_id=?")
            ->execute([(int)$plan['id'],(int)$shortlist['id'],$userId,$agentId]);
        $pdo->prepare('UPDATE user_gifting_plans SET recommendation_json=?,updated_at=NOW() WHERE id=? AND owner_user_id=?')
            ->execute([json_encode($recommendation,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$plan['id'],$userId]);

        $pdo->commit();
        $selection=['shortlist_id'=>$shortlistId,'plan'=>['id'=>$planId,'title'=>(string)$plan['title']],'product'=>$product];
        mg_audit('multi_agent.plan_product_selected','gifting_plan',['agent_id'=>$agentId,'plan_id'=>$planId,'shortlist_id'=>$shortlistId,'product_version_id'=>$snapshot['product_version_id'],'used_ai'=>false],$userId);
        return $selection;
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_task_agent_remove_plan_selection(PDO $pdo, int $userId, int $agentId, string $shortlistId, string $planId): void
{
    mg_task_agent_shortlist_require_schema($pdo);
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare("SELECT s.id,s.product_version_id,p.id plan_internal_id,p.recommendation_json
            FROM multi_agent_shortlist_items s
            INNER JOIN user_gifting_plans p ON p.id=s.plan_id AND p.owner_user_id=s.owner_user_id
            WHERE s.public_id=? AND p.public_id=? AND s.owner_user_id=? AND s.agent_id=? AND s.status='selected' LIMIT 1 FOR UPDATE");
        $stmt->execute([$shortlistId,$planId,$userId,$agentId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Selected plan product not found.');
        $recommendation=json_decode((string)($row['recommendation_json']??''),true);
        if(!is_array($recommendation))$recommendation=[];
        unset($recommendation['selected_product'],$recommendation['selected_product_source'],$recommendation['selected_at']);
        $pdo->prepare("UPDATE multi_agent_shortlist_items SET status='active',plan_id=NULL,selected_at=NULL,updated_at=NOW() WHERE id=? AND owner_user_id=? AND agent_id=?")
            ->execute([(int)$row['id'],$userId,$agentId]);
        $pdo->prepare('UPDATE user_gifting_plans SET recommendation_json=?,updated_at=NOW() WHERE id=? AND owner_user_id=?')
            ->execute([json_encode($recommendation,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$row['plan_internal_id'],$userId]);
        $pdo->commit();
        mg_audit('multi_agent.plan_product_removed','gifting_plan',['agent_id'=>$agentId,'plan_id'=>$planId,'shortlist_id'=>$shortlistId,'used_ai'=>false],$userId);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_task_agent_plan_selection_for_model(array $selections): array
{
    return array_map(static function(array $selection):array{
        $snapshot=mg_task_agent_plan_selection_product_snapshot(is_array($selection['product']??null)?$selection['product']:[]);
        return [
            'plan_title'=>(string)($selection['plan']['title']??''),
            'product_title'=>$snapshot['title'],
            'merchant_name'=>$snapshot['merchant_name'],
            'value_cents'=>$snapshot['value_cents'],
            'currency'=>$snapshot['currency'],
        ];
    },array_slice($selections,0,8));
}
