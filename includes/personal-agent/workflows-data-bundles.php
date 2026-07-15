<?php
declare(strict_types=1);

function mg_personal_workflows_bundles(PDO $pdo, int $userId): array
{
    mg_personal_workflows_require_schema($pdo);
    $stmt = $pdo->prepare("SELECT b.*,p.public_id plan_public_id,p.title plan_title,l.public_id list_public_id,l.name list_name,
        c.public_id contact_public_id,c.display_name contact_name,u.public_id linked_public_id,COALESCE(pp.display_name,u.display_name,u.full_name) linked_name
        FROM user_gift_bundles b
        LEFT JOIN user_gifting_plans p ON p.id=b.plan_id AND p.owner_user_id=b.owner_user_id
        LEFT JOIN user_contact_lists l ON l.id=b.list_id AND l.owner_user_id=b.owner_user_id
        LEFT JOIN user_contacts c ON c.id=b.user_contact_id AND c.owner_user_id=b.owner_user_id
        LEFT JOIN users u ON u.id=b.contact_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=b.contact_user_id
        WHERE b.owner_user_id=? ORDER BY FIELD(b.status,'ready','draft','archived'),b.updated_at DESC");
    $stmt->execute([$userId]);
    $bundles=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $itemStmt=$pdo->prepare("SELECT bi.public_id,bi.item_type,bi.custom_label,bi.quantity,bi.unit_value_cents,bi.currency,bi.notes,bi.sort_order,bi.status,
            cp.public_id product_public_id,cpv.title product_title,COALESCE(pp.display_name,u.display_name,u.full_name) merchant_name
            FROM user_gift_bundle_items bi
            LEFT JOIN catalog_products cp ON cp.id=bi.catalog_product_id
            LEFT JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
            LEFT JOIN users u ON u.id=cp.merchant_user_id
            LEFT JOIN public_profiles pp ON pp.user_id=cp.merchant_user_id
            WHERE bi.bundle_id=? AND bi.status<>'removed' ORDER BY bi.sort_order,bi.id");
        $itemStmt->execute([(int)$row['id']]);
        $items=array_map(static fn(array $item): array => [
            'id'=>(string)$item['public_id'],
            'item_type'=>(string)$item['item_type'],
            'label'=>(string)($item['product_title'] ?: $item['custom_label'] ?: 'Bundle item'),
            'product_id'=>(string)($item['product_public_id'] ?? ''),
            'merchant_name'=>(string)($item['merchant_name'] ?? ''),
            'quantity'=>(int)$item['quantity'],
            'unit_value'=>mg_personal_workflows_money($item['unit_value_cents'] !== null ? (int)$item['unit_value_cents'] : null),
            'currency'=>(string)$item['currency'],
            'notes'=>(string)($item['notes'] ?? ''),
            'status'=>(string)$item['status'],
        ],$itemStmt->fetchAll(PDO::FETCH_ASSOC));
        $context=['type'=>'none','id'=>'','name'=>''];
        if (!empty($row['contact_public_id'])) $context=['type'=>'contact','id'=>(string)$row['contact_public_id'],'name'=>(string)$row['contact_name']];
        elseif (!empty($row['linked_public_id'])) $context=['type'=>'linked_user','id'=>(string)$row['linked_public_id'],'name'=>(string)$row['linked_name']];
        elseif (!empty($row['list_public_id'])) $context=['type'=>'list','id'=>(string)$row['list_public_id'],'name'=>(string)$row['list_name']];
        $total=0;
        foreach($items as $item) $total += (int)round(($item['unit_value'] ?? 0)*100) * max(1,(int)$item['quantity']);
        $bundles[]=[
            'id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'description'=>(string)($row['description'] ?? ''),
            'target_budget'=>mg_personal_workflows_money($row['target_budget_cents'] !== null ? (int)$row['target_budget_cents'] : null),
            'estimated_total'=>mg_personal_workflows_money($total),'currency'=>(string)$row['currency'],'status'=>(string)$row['status'],
            'approval_required'=>(bool)$row['approval_required'],'context'=>$context,'plan'=>!empty($row['plan_public_id'])?['id'=>(string)$row['plan_public_id'],'title'=>(string)$row['plan_title']]:null,
            'items'=>$items,'created_at'=>$row['created_at'],'updated_at'=>$row['updated_at'],
        ];
    }
    return $bundles;
}

function mg_personal_workflows_eligible_gifts(PDO $pdo, int $userId): array
{
    if (!mg_personal_agent_table_exists($pdo,'gifts')) return [];
    $stmt=$pdo->prepare("SELECT g.id internal_id,g.public_id,g.sender_user_id,g.recipient_user_id,g.recipient_name,g.title,g.status,g.expires_at,g.sent_at,g.delivered_at,
        gc.status claim_status,gc.expires_at claim_expires_at,
        COALESCE(spp.display_name,su.display_name,su.full_name,'Sender') sender_name,
        COALESCE(rpp.display_name,ru.display_name,ru.full_name,g.recipient_name) recipient_display_name
        FROM gifts g
        LEFT JOIN gift_claims gc ON gc.gift_id=g.id
        INNER JOIN users su ON su.id=g.sender_user_id
        LEFT JOIN public_profiles spp ON spp.user_id=g.sender_user_id
        LEFT JOIN users ru ON ru.id=g.recipient_user_id
        LEFT JOIN public_profiles rpp ON rpp.user_id=g.recipient_user_id
        WHERE (g.sender_user_id=? OR g.recipient_user_id=?) AND g.status IN ('sent','delivered','claimed')
        ORDER BY g.updated_at DESC LIMIT 100");
    $stmt->execute([$userId,$userId]);
    return array_map(static function(array $row) use($userId): array {
        $isSender=(int)$row['sender_user_id']===$userId;
        $kind=(string)($row['claim_status'] ?? '')==='verified' ? 'redemption' : 'claim';
        $target=$isSender ? (int)($row['recipient_user_id'] ?? 0) : $userId;
        return [
            'id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'status'=>(string)$row['status'],
            'claim_status'=>(string)($row['claim_status'] ?? ''),'reminder_kind'=>$kind,'target_user_available'=>$target>0,
            'role'=>$isSender?'sender':'recipient','sender_name'=>(string)$row['sender_name'],'recipient_name'=>(string)$row['recipient_display_name'],
            'expires_at'=>$row['claim_expires_at'] ?: ($row['expires_at'] ?: null),'sent_at'=>$row['sent_at'] ?: null,'delivered_at'=>$row['delivered_at'] ?: null,
        ];
    },$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_workflows_lifecycle_reminders(PDO $pdo, int $userId): array
{
    mg_personal_workflows_require_schema($pdo);
    $stmt=$pdo->prepare("SELECT r.public_id,r.reminder_kind,r.remind_at,r.status,r.message,r.sent_at,r.created_at,
        g.public_id gift_public_id,g.title gift_title,g.status gift_status,
        COALESCE(pp.display_name,u.display_name,u.full_name,'Recipient') target_name
        FROM user_gift_lifecycle_reminders r INNER JOIN gifts g ON g.id=r.gift_id
        INNER JOIN users u ON u.id=r.target_user_id LEFT JOIN public_profiles pp ON pp.user_id=r.target_user_id
        WHERE r.owner_user_id=? ORDER BY FIELD(r.status,'scheduled','draft','sent','dismissed','cancelled'),r.remind_at,r.id");
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'kind'=>(string)$row['reminder_kind'],'remind_at'=>(string)$row['remind_at'],'status'=>(string)$row['status'],
        'message'=>(string)($row['message'] ?? ''),'sent_at'=>$row['sent_at'] ?: null,'gift'=>['id'=>(string)$row['gift_public_id'],'title'=>(string)$row['gift_title'],'status'=>(string)$row['gift_status']],
        'target_name'=>(string)$row['target_name'],'created_at'=>$row['created_at'],
    ],$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_workflows_dashboard(PDO $pdo, int $userId): array
{
    mg_personal_workflows_require_schema($pdo);
    $schedules=mg_personal_workflows_schedules($pdo,$userId,'all');
    $programs=mg_personal_workflows_recurring_programs($pdo,$userId,'all');
    $groups=mg_personal_workflows_group_gifts($pdo,$userId);
    $requests=mg_personal_workflows_requests($pdo,$userId);
    $bundles=mg_personal_workflows_bundles($pdo,$userId);
    $lifecycle=mg_personal_workflows_lifecycle_reminders($pdo,$userId);
    return [
        'summary'=>[
            'scheduled'=>count(array_filter($schedules,static fn(array $row):bool=>in_array($row['status'],['draft','approved','prepared'],true))),
            'recurring'=>count(array_filter($programs,static fn(array $row):bool=>in_array($row['status'],['draft','active','paused'],true))),
            'group_gifts'=>count(array_filter($groups['owned'],static fn(array $row):bool=>in_array($row['status'],['draft','open','locked'],true))),
            'incoming_requests'=>count(array_filter($requests['incoming'],static fn(array $row):bool=>$row['status']==='pending')),
            'bundles'=>count(array_filter($bundles,static fn(array $row):bool=>$row['status']!=='archived')),
            'lifecycle_reminders'=>count(array_filter($lifecycle,static fn(array $row):bool=>in_array($row['status'],['draft','scheduled'],true))),
        ],
        'schedules'=>$schedules,
        'recurring_programs'=>$programs,
        'group_gifts'=>$groups,
        'requests'=>$requests,
        'bundles'=>$bundles,
        'catalog'=>mg_personal_workflows_catalog($pdo,40),
        'eligible_gifts'=>mg_personal_workflows_eligible_gifts($pdo,$userId),
        'lifecycle_reminders'=>$lifecycle,
    ];
}
