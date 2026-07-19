<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

$pdo=mg_db();
$user=mg_authenticated_user();
if(!$user || (int)($user['id']??0)<1) mg_fail('Sign in to continue.',401);
if(!mg_admin_permission_user_has($user,'commerce.manage') && !mg_admin_permission_user_has($user,'admin')) mg_fail('Admin commerce access is required.',403);
$actorId=(int)$user['id'];
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$input=$method==='POST'?mg_input():[];
$action=strtolower(trim((string)($input['action']??$_GET['action']??'queue')));

function mg_bundle_transfer_execution_enabled(): bool
{
    return filter_var((string)(getenv('MG_BUNDLE_TRANSFER_EXECUTION_ENABLED')?:''),FILTER_VALIDATE_BOOL);
}

function mg_bundle_transfer_schema(PDO $pdo): void
{
    foreach(['gift_bundle_component_settlements','gift_bundle_settlement_events','gift_bundle_settlement_transfers','payment_provider_accounts'] as $table){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Product Bundle transfer schema is not installed.');
    }
}

function mg_bundle_transfer_queue(PDO $pdo): array
{
    $stmt=$pdo->prepare("SELECT s.public_id settlement_public_id,s.currency,s.payable_amount_cents,s.readiness_status,s.eligible_at,s.created_at,
        c.public_id component_public_id,o.public_id order_public_id,b.title bundle_title,
        COALESCE(ms.display_name,u.display_name,u.email) merchant_name,
        p.provider_account_reference,p.status account_status,p.payouts_enabled,p.charges_enabled,
        t.public_id transfer_public_id,t.provider_transfer_reference,t.transfer_status,t.failure_code,t.failure_message,t.created_at transfer_created_at
        FROM gift_bundle_component_settlements s
        INNER JOIN gift_bundle_order_components c ON c.id=s.component_id
        INNER JOIN gift_bundle_orders o ON o.id=s.bundle_order_id
        INNER JOIN gift_bundles b ON b.id=o.bundle_id
        INNER JOIN users u ON u.id=s.merchant_user_id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
        LEFT JOIN payment_provider_accounts p ON p.merchant_user_id=s.merchant_user_id AND p.provider_key='stripe' AND p.mode=?
        LEFT JOIN gift_bundle_settlement_transfers t ON t.settlement_id=s.id
        WHERE s.readiness_status IN ('eligible','released')
          AND EXISTS (SELECT 1 FROM gift_bundle_settlement_events e WHERE e.settlement_id=s.id AND e.event_type='admin_review_mark_release_ready')
        ORDER BY s.created_at ASC LIMIT 500");
    $stmt->execute([mg_payment_mode()]);
    return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'transfer_execution_enabled'=>mg_bundle_transfer_execution_enabled(),'payment_mode'=>mg_payment_mode()];
}

function mg_bundle_transfer_prepare(PDO $pdo,string $settlementPublicId,int $actorId,string $idempotencyKey): array
{
    if($idempotencyKey==='') throw new InvalidArgumentException('An idempotency key is required.');
    $stmt=$pdo->prepare("SELECT s.*,p.provider_account_reference,p.status account_status,p.payouts_enabled,p.charges_enabled
        FROM gift_bundle_component_settlements s
        LEFT JOIN payment_provider_accounts p ON p.merchant_user_id=s.merchant_user_id AND p.provider_key='stripe' AND p.mode=?
        WHERE s.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([mg_payment_mode(),$settlementPublicId]);
    $settlement=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$settlement) throw new InvalidArgumentException('Settlement was not found.');
    if((string)$settlement['readiness_status']!=='eligible') throw new InvalidArgumentException('Only eligible settlements may be transferred.');
    if((int)$settlement['payable_amount_cents']<1) throw new InvalidArgumentException('Settlement has no payable amount.');
    $ready=$pdo->prepare("SELECT 1 FROM gift_bundle_settlement_events WHERE settlement_id=? AND event_type='admin_review_mark_release_ready' LIMIT 1");
    $ready->execute([(int)$settlement['id']]);
    if(!$ready->fetchColumn()) throw new InvalidArgumentException('Settlement has not passed the release-ready review gate.');
    if(trim((string)($settlement['provider_account_reference']??''))==='' || (string)($settlement['account_status']??'')!=='active' || (int)($settlement['payouts_enabled']??0)!==1){
        throw new InvalidArgumentException('Merchant Stripe account is not payout-ready.');
    }
    $existing=$pdo->prepare('SELECT * FROM gift_bundle_settlement_transfers WHERE settlement_id=? OR idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$settlement['id'],$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        if((int)$row['settlement_id']!==(int)$settlement['id']) throw new RuntimeException('Transfer idempotency key is bound to another settlement.');
        return ['transfer'=>$row,'duplicate'=>true];
    }
    $publicId=mg_public_uuid();
    $snapshot=json_encode(['settlement_public_id'=>$settlementPublicId,'merchant_user_id'=>(int)$settlement['merchant_user_id'],'destination'=>(string)$settlement['provider_account_reference'],'amount_cents'=>(int)$settlement['payable_amount_cents'],'currency'=>(string)$settlement['currency'],'mode'=>mg_payment_mode()],JSON_THROW_ON_ERROR);
    $pdo->prepare("INSERT INTO gift_bundle_settlement_transfers
        (public_id,settlement_id,merchant_user_id,provider_key,provider_account_reference,amount_cents,currency,transfer_status,idempotency_key,request_snapshot_json,initiated_by_user_id,created_at,updated_at)
        VALUES (?,?,?,'stripe',?,?,?,'created',?,?,?,NOW(),NOW())")
        ->execute([$publicId,(int)$settlement['id'],(int)$settlement['merchant_user_id'],(string)$settlement['provider_account_reference'],(int)$settlement['payable_amount_cents'],strtoupper((string)$settlement['currency']),$idempotencyKey,$snapshot,$actorId]);
    $transfer=$pdo->query('SELECT * FROM gift_bundle_settlement_transfers WHERE id='.(int)$pdo->lastInsertId())->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("INSERT INTO gift_bundle_settlement_events (public_id,settlement_id,actor_user_id,event_type,idempotency_key,event_data,created_at) VALUES (?,?,?,?,?,?,NOW())")
        ->execute([mg_public_uuid(),(int)$settlement['id'],$actorId,'stripe_transfer_queued','bundle-transfer-queued-'.(int)$transfer['id'],json_encode(['transfer_public_id'=>$publicId,'amount_cents'=>(int)$transfer['amount_cents'],'currency'=>(string)$transfer['currency']],JSON_THROW_ON_ERROR)]);
    return ['transfer'=>$transfer,'duplicate'=>false];
}

try{
    mg_bundle_transfer_schema($pdo);
    if($method==='GET' && $action==='queue') mg_ok(mg_bundle_transfer_queue($pdo));
    if($method==='POST' && $action==='queue_transfer'){
        mg_require_csrf_for_write($input);
        if(!mg_bundle_transfer_execution_enabled()) mg_fail('Bundle transfer execution is disabled.',503);
        if(trim((string)($input['confirmation']??''))!=='RELEASE') mg_fail('Type RELEASE to confirm transfer queuing.',422);
        $pdo->beginTransaction();
        $result=mg_bundle_transfer_prepare($pdo,trim((string)($input['settlement_id']??'')),$actorId,trim((string)($input['idempotency_key']??'')));
        $pdo->commit();
        mg_ok(['transfer'=>$result['transfer'],'idempotent'=>$result['duplicate'],'provider_dispatch_required'=>true]);
    }
    mg_fail('Unsupported transfer operation.',405);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail_unexpected($e,'bundle.transfer.failure','Unable to process the bundle transfer request.',500,[],$actorId);}
