<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_golden_path_integrity.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$locationPublicId=strtolower(trim((string)($input['location_id']??'')));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($actionItemId===''||$locationPublicId===''||$idempotencyKey==='')mg_fail('Action Center item, location, and idempotency key are required.',422);
if(strlen($locationPublicId)!==36||!preg_match('/^[a-f0-9-]{36}$/',$locationPublicId))mg_fail('Invalid merchant location.',422);

try{
    $pdo->beginTransaction();

    $stmt=$pdo->prepare("SELECT ac.folder,ac.state,ac.user_id,i.*,v.product_id,cp.public_id catalog_product_id,cp.merchant_user_id,ml.id location_internal_id,ml.public_id location_public_id,ml.name location_name
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
        INNER JOIN catalog_products cp ON cp.id=v.product_id
        INNER JOIN merchant_workspaces mw ON mw.merchant_user_id=cp.merchant_user_id
        INNER JOIN merchant_locations ml ON ml.workspace_id=mw.id AND ml.public_id=? AND ml.status='active'
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1");
    $stmt->execute([$locationPublicId,$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Redeemable Action Center item or merchant location not found.');
    if((int)$instance['owner_user_id']!==(int)$user['id'])throw new RuntimeException('You do not own this Microgift.');
    if(!in_array((string)$instance['status'],['claimed','redeemable'],true))throw new RuntimeException('Microgift is not redeemable.');
    if((int)$instance['merchant_user_id']<1)throw new RuntimeException('Redeemable merchant catalog was not found.');
    if(!mg_microgift_integrity_location_allowed($instance,$locationPublicId))throw new RuntimeException('Microgift is not eligible at this location.');

    $result=mg_microgift_redeem($pdo,(int)$user['id'],[
        'instance_id'=>(string)$instance['public_id'],
        'idempotency_key'=>$idempotencyKey,
        'source_reference'=>$actionItemId,
        'merchant_user_id'=>(int)$instance['merchant_user_id'],
        'location_reference'=>$locationPublicId,
        'metadata'=>[
            'action_item_id'=>$actionItemId,
            'catalog_product_id'=>(string)$instance['catalog_product_id'],
            'location_name'=>(string)$instance['location_name'],
        ],
    ]);

    $redemptionInternalId=null;
    if(!empty($result['redemption_id'])){
        $redemptionStmt=$pdo->prepare('SELECT id FROM microgift_redemptions WHERE public_id=? LIMIT 1');
        $redemptionStmt->execute([(string)$result['redemption_id']]);
        $redemptionInternalId=(int)($redemptionStmt->fetchColumn()?:0)?:null;
    }

    $reloaded=mg_microgift_load_instance($pdo,(string)$result['instance_id']);
    $projection=mg_action_center_project_lifecycle($pdo,$reloaded,[
        'redemption_id'=>$redemptionInternalId,
        'merchant_user_id'=>(int)$instance['merchant_user_id'],
        'location_id'=>(int)$instance['location_internal_id'],
        'can_tip'=>1,
    ]);
    $pdo->commit();

    mg_audit('action_center.microgift_redeemed','microgift_instance',[
        'instance_id'=>$result['instance_id'],
        'redemption_id'=>$result['redemption_id']??null,
        'action_item_id'=>$actionItemId,
        'location_id'=>$locationPublicId,
        'merchant_user_id'=>(int)$instance['merchant_user_id'],
        'duplicate'=>$result['duplicate']??false,
    ],(int)$user['id']);
    mg_ok($result+['location_id'=>$locationPublicId,'location_name'=>(string)$instance['location_name'],'action_center'=>$projection],!empty($result['duplicate'])?'Existing redemption result returned.':'Microgift redeemed.',!empty($result['duplicate'])?200:201);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.redeem_failed','Action Center redeem failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to redeem this Microgift.',500);
}
