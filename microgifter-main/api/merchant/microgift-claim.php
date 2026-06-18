<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_claim_operations.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.location_claim.execute');
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();

try {
    $result = mg_claim_execute_operation($pdo,(int)$user['id'],(int)$user['id'],$input);

    $pdo->beginTransaction();
    $instance=mg_microgift_load_instance($pdo,trim((string)($input['instance_id']??'')));
    $redemptionStmt=$pdo->prepare('SELECT id,merchant_user_id,location_id FROM microgift_redemptions WHERE public_id=? LIMIT 1');
    $redemptionStmt->execute([(string)($result['redemption_id']??'')]);
    $redemption=$redemptionStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance,[
        'redemption_id'=>(int)($redemption['id']??0)?:null,
        'merchant_user_id'=>(int)($redemption['merchant_user_id']??0)?:null,
        'location_id'=>(int)($redemption['location_id']??0)?:null,
        'can_tip'=>1,
    ]);
    $pdo->commit();

    mg_audit('merchant.microgift_claim.completed','microgift_redemption',[
        'redemption_id'=>$result['redemption_id'] ?? null,
        'correlation_id'=>$result['correlation_id'] ?? null,
    ],(int)$user['id']);
    mg_ok($result);
} catch (MgLocationClaimAuthorityException|MgMicrogiftLifecycleException $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    $status = $error->resultCode === 'rate_limited' ? 429 : 422;
    mg_fail('Unable to complete merchant claim.',$status,['reason'=>$error->resultCode]);
} catch (InvalidArgumentException $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to complete merchant claim.',409);
}
