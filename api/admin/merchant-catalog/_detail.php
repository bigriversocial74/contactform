<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_detail_workspace.php';
require_once __DIR__ . '/_detail_store.php';
require_once __DIR__ . '/_detail_catalog.php';

function mg_admin_mc_fact(string $label,mixed $value,string $kind='text'): array{return ['label'=>$label,'value'=>$value,'kind'=>$kind];}

function mg_admin_mc_detail(PDO $pdo,array $actor,string $type,string $reference): array
{
    $detail=match($type){
        'workspace'=>mg_admin_mc_workspace_detail($pdo,$reference),
        'storefront'=>mg_admin_mc_storefront_detail($pdo,$reference),
        'product'=>mg_admin_mc_product_detail($pdo,$reference),
        'asset'=>mg_admin_mc_asset_detail($pdo,$reference),
    };
    $detail['events']=mg_admin_mc_events($pdo,$type,$reference);
    $detail['capabilities']=[
        'manage_merchants'=>mg_admin_mc_has($actor,'admin.merchants.manage'),
        'manage_catalog'=>mg_admin_mc_has($actor,'admin.catalog.manage'),
    ];
    return $detail;
}
