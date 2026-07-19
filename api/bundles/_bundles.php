<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/payments/_commissions.php';

final class MgBundleException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409) { parent::__construct($message); }
}

function mg_bundle_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    $stmt=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_bundle_require_schema(PDO $pdo): void
{
    foreach (['gift_bundles','gift_bundle_components','gift_bundle_participants','gift_bundle_audit_log'] as $table) {
        if (!mg_bundle_table_exists($pdo,$table)) throw new MgBundleException('Product Bundle setup is incomplete. Import database/20260719_product_bundles_foundation_builder_v1.sql, then retry.',409);
    }
    mg_commission_require_schema($pdo);
}

function mg_bundle_text(mixed $value, int $max=190): string
{
    $value=trim((string)$value);
    if (mb_strlen($value)>$max) throw new InvalidArgumentException('Bundle input is too long.');
    return $value;
}

function mg_bundle_slug(string $value): string
{
    $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$value) ?? '', '-'));
    return $slug !== '' ? mb_substr($slug,0,190) : 'bundle-' . substr(str_replace('-','',mg_public_uuid()),0,12);
}

function mg_bundle_json(array $value): string
{
    return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}

function mg_bundle_audit(PDO $pdo,int $bundleId,int $actorId,string $event,string $entityType,string $entityPublicId='',array $data=[]): void
{
    $pdo->prepare('INSERT INTO gift_bundle_audit_log (public_id,bundle_id,actor_user_id,event_type,entity_type,entity_public_id,event_data,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$bundleId,$actorId,$event,$entityType,$entityPublicId ?: null,$data ? mg_bundle_json($data) : null]);
}

function mg_bundle_owned(PDO $pdo,string $publicId,int $merchantId,bool $forUpdate=false): array
{
    $stmt=$pdo->prepare('SELECT * FROM gift_bundles WHERE public_id=? AND owner_merchant_user_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([$publicId,$merchantId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new MgBundleException('Bundle not found.',404);
    return $row;
}

function mg_bundle_commission_quote(PDO $pdo,int $merchantId,int $amountCents,array $bundle,array $accepted=[]): array
{
    if($amountCents<1) throw new InvalidArgumentException('Component amount must be at least one cent.');
    $mode=(string)($bundle['commission_mode']??'merchant_default');
    if($mode==='custom_participant_rates' && isset($accepted['commission_rate_bps'])) {
        $bps=mg_commission_normalize_bps($accepted['commission_rate_bps'],'Accepted participant commission');
        $source='accepted_bundle_participant';
    } elseif($mode==='bundle_starting_rate') {
        $bps=mg_commission_normalize_bps($bundle['starting_commission_bps']??null,'Bundle starting commission');
        $source='bundle_starting_rate';
    } else {
        $resolved=mg_commission_resolve_merchant_rate($pdo,$merchantId,['initialize'=>true]);
        $bps=(int)$resolved['commission_rate_bps'];
        $source=(string)$resolved['rate_source'];
    }
    $commission=intdiv(($amountCents*$bps)+5000,10000);
    return ['commission_rate_bps'=>$bps,'commission_amount_cents'=>$commission,'merchant_net_amount_cents'=>$amountCents-$commission,'commission_source'=>$source,'commission_rule_version'=>MG_COMMISSION_RULE_VERSION];
}

function mg_bundle_publish_validation(PDO $pdo,array $bundle): array
{
    $errors=[];
    $components=$pdo->prepare('SELECT c.*,p.status product_status,v.version_status FROM gift_bundle_components c INNER JOIN catalog_products p ON p.id=c.product_id INNER JOIN catalog_product_versions v ON v.id=c.product_version_id WHERE c.bundle_id=? ORDER BY c.display_order,c.id');
    $components->execute([(int)$bundle['id']]);
    $rows=$components->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows) $errors[]='Add at least one component.';
    foreach($rows as $row){
        if((string)$row['product_status']!=='published'||(string)$row['version_status']!=='published') $errors[]='Every component must use a published product version.';
        if((int)$row['customer_amount_cents']<1) $errors[]='Every component needs a valid amount.';
        if(trim((string)$row['claim_policy'])==='') $errors[]='Every component needs a claim policy.';
        if(trim((string)$row['settlement_policy'])==='') $errors[]='Every component needs a settlement policy.';
        if((int)$row['merchant_user_id']!==(int)$bundle['owner_merchant_user_id']) {
            $check=$pdo->prepare("SELECT id FROM gift_bundle_participants WHERE bundle_id=? AND merchant_user_id=? AND terms_version=? AND invitation_status='accepted' LIMIT 1");
            $check->execute([(int)$bundle['id'],(int)$row['merchant_user_id'],(int)$bundle['terms_version']]);
            if(!$check->fetchColumn()) $errors[]='Every participating merchant must accept the current terms version.';
        }
    }
    if(trim((string)$bundle['title'])==='') $errors[]='Bundle title is required.';
    if(trim((string)($bundle['cover_asset_url']??''))==='') $errors[]='Cover image is required.';
    if($bundle['commission_mode']==='bundle_starting_rate' && $bundle['starting_commission_bps']===null) $errors[]='Bundle starting commission is required.';
    return array_values(array_unique($errors));
}
