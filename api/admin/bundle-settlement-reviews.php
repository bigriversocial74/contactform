<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__) . '/bundles/_bundles.php';

$pdo=mg_db();
$user=mg_authenticated_user();
if(!$user || (int)($user['id']??0)<1) mg_fail('Sign in to continue.',401);
if(!mg_admin_permission_user_has($user,'commerce.manage') && !mg_admin_permission_user_has($user,'admin')) mg_fail('Admin commerce access is required.',403);
$actorId=(int)$user['id'];
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$input=$method==='POST'?mg_input():[];
$action=strtolower(trim((string)($input['action']??$_GET['action']??'queue')));

function mg_bundle_review_schema(PDO $pdo): void {
    foreach(['gift_bundle_component_settlements','gift_bundle_settlement_reviews'] as $table){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Product Bundle settlement review schema is not installed.');
    }
}

function mg_bundle_review_queue(PDO $pdo): array {
    $stmt=$pdo->query("SELECT s.public_id,s.currency,s.gross_amount_cents,s.commission_amount_cents,s.merchant_net_amount_cents,s.payable_amount_cents,s.readiness_status,s.review_status,s.review_reason,s.eligible_at,s.created_at,c.public_id component_public_id,o.public_id order_public_id,b.title bundle_title,COALESCE(ms.display_name,u.display_name,u.email) merchant_name FROM gift_bundle_component_settlements s INNER JOIN gift_bundle_order_components c ON c.id=s.component_id INNER JOIN gift_bundle_orders o ON o.id=s.bundle_order_id INNER JOIN gift_bundles b ON b.id=o.bundle_id INNER JOIN users u ON u.id=s.merchant_user_id LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived' WHERE s.readiness_status IN ('eligible','held','blocked') OR s.review_status IN ('unreviewed','held','blocked','approved','release_ready') ORDER BY FIELD(s.review_status,'unreviewed','held','blocked','approved','release_ready'),s.created_at ASC LIMIT 500");
    return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'transfer_execution_enabled'=>false];
}

function mg_bundle_review_apply(PDO $pdo,string $publicId,string $action,string $reason,int $actorId,string $idempotencyKey): array {
    $allowed=['approve','hold','block','mark_release_ready','reopen'];
    if(!in_array($action,$allowed,true)) throw new InvalidArgumentException('Unsupported review action.');
    if(in_array($action,['hold','block'],true) && trim($reason)==='') throw new InvalidArgumentException('A reason is required.');
    $stmt=$pdo->prepare('SELECT * FROM gift_bundle_component_settlements WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new InvalidArgumentException('Settlement was not found.');
    $existing=$pdo->prepare('SELECT resulting_status FROM gift_bundle_settlement_reviews WHERE idempotency_key=? LIMIT 1');
    $existing->execute([$idempotencyKey]);
    if($found=$existing->fetchColumn()) return ['status'=>$found,'idempotent'=>true];
    $next=match($action){'approve'=>'approved','hold'=>'held','block'=>'blocked','mark_release_ready'=>'release_ready','reopen'=>'unreviewed'};
    if($action==='mark_release_ready' && (string)$row['readiness_status']!=='eligible') throw new InvalidArgumentException('Only eligible settlements can be marked release ready.');
    $pdo->prepare('UPDATE gift_bundle_component_settlements SET review_status=?,review_reason=?,reviewed_by_user_id=?,reviewed_at=NOW(),release_gate_passed_at=CASE WHEN ?="release_ready" THEN NOW() ELSE NULL END,updated_at=NOW() WHERE id=?')->execute([$next,$reason!==''?$reason:null,$actorId,$next,(int)$row['id']]);
    $snapshot=json_encode(['readiness_status'=>$row['readiness_status'],'payable_amount_cents'=>(int)$row['payable_amount_cents'],'transfer_execution_enabled'=>false],JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT INTO gift_bundle_settlement_reviews (public_id,settlement_id,reviewer_user_id,action,previous_status,resulting_status,reason,review_snapshot_json,idempotency_key,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')->execute([mg_microgift_uuid(),(int)$row['id'],$actorId,$action,(string)$row['review_status'],$next,$reason!==''?$reason:null,$snapshot,$idempotencyKey]);
    return ['status'=>$next,'idempotent'=>false,'transfer_execution_enabled'=>false];
}

try{
    mg_bundle_review_schema($pdo);
    if($method==='GET' && $action==='queue') mg_ok(mg_bundle_review_queue($pdo));
    if($method==='POST' && $action==='review'){
        mg_require_csrf_for_write($input);
        $pdo->beginTransaction();
        $result=mg_bundle_review_apply($pdo,trim((string)($input['settlement_id']??'')),trim((string)($input['review_action']??'')),trim((string)($input['reason']??'')),$actorId,trim((string)($input['idempotency_key']??'')));
        $pdo->commit();
        mg_ok($result);
    }
    mg_fail('Unsupported settlement review operation.',405);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail_unexpected($e,'bundle.settlement.review.failure','Unable to process settlement review.',500,[],$actorId);}