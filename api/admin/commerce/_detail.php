<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_detail_order.php';
require_once __DIR__ . '/_detail_finance.php';
require_once __DIR__ . '/_detail_lifecycle.php';

function mg_admin_commerce_fact(string $label, mixed $value, string $kind = 'text'): array
{
    return ['label'=>$label,'value'=>$value,'kind'=>$kind];
}

function mg_admin_commerce_timeline_item(string $time,string $type,string $label,?string $status=null,?string $description=null,string $source='system'): array
{
    return ['occurred_at'=>$time,'event_type'=>$type,'label'=>$label,'status'=>$status,'description'=>$description,'source'=>$source];
}

function mg_admin_commerce_capabilities(array $actor,string $type,array $entity): array
{
    return [
        'manage_cases'=>mg_admin_commerce_has($actor,'admin.commerce.cases.manage'),
        'reverse_tip'=>$type==='tip'&&(string)($entity['status']??'')==='posted'&&mg_admin_commerce_has($actor,'admin.commerce.tips.reverse'),
    ];
}

function mg_admin_commerce_detail(PDO $pdo,array $actor,string $type,string $reference): array
{
    $detail=match($type){
        'order'=>mg_admin_commerce_order_detail($pdo,$reference),
        'refund'=>mg_admin_commerce_refund_detail($pdo,$reference),
        'dispute'=>mg_admin_commerce_dispute_detail($pdo,$reference),
        'subscription'=>mg_admin_commerce_subscription_detail($pdo,$reference),
        'tip'=>mg_admin_commerce_tip_detail($pdo,$reference),
        'microgift'=>mg_admin_commerce_microgift_detail($pdo,$reference),
    };
    $detail['cases']=mg_admin_commerce_cases($pdo,$type,$reference);
    foreach($detail['cases'] as &$case)$case['events']=mg_admin_commerce_case_events($pdo,(int)$case['id']);
    unset($case);
    $detail['capabilities']=mg_admin_commerce_capabilities($actor,$type,$detail['entity']);
    return $detail;
}
