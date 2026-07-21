<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/bundles/_provider_reversal.php';
require_once dirname(__DIR__) . '/api/bundles/_release_readiness.php';

$pdo=mg_db();
$limit=max(1,min(25,(int)($argv[1]??10)));
mg_bundle_reversal_assert_execution_allowed();
mg_bundle_release_assert_runtime_allowed($pdo, 'reversal');
$processed=$succeeded=$failed=0;
while($processed<$limit){
    $adjustment=null;$transfer=null;
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->query("SELECT a.*,t.public_id transfer_public_id,t.provider_transfer_reference,t.amount_cents transfer_amount_cents,t.settlement_id,t.transfer_status,t.provider_key,t.provider_account_reference FROM gift_bundle_settlement_adjustments a INNER JOIN gift_bundle_settlement_transfers t ON t.id=a.transfer_id WHERE a.adjustment_type IN ('reversal_request','reversal') AND a.adjustment_status='dispatch_pending' AND t.transfer_status IN ('succeeded','reversed') AND t.provider_transfer_reference IS NOT NULL AND (a.next_dispatch_at IS NULL OR a.next_dispatch_at<=NOW()) AND (a.dispatch_locked_at IS NULL OR a.dispatch_locked_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)) ORDER BY a.created_at ASC LIMIT 1 FOR UPDATE");
        $adjustment=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$adjustment){$pdo->commit();break;}
        $transfer=['id'=>(int)$adjustment['transfer_id'],'public_id'=>$adjustment['transfer_public_id'],'provider_transfer_reference'=>$adjustment['provider_transfer_reference'],'amount_cents'=>(int)$adjustment['transfer_amount_cents'],'settlement_id'=>(int)$adjustment['settlement_id']];
        $token=mg_public_uuid();
        $pdo->prepare("UPDATE gift_bundle_settlement_adjustments SET adjustment_status='submitted',dispatch_locked_at=NOW(),dispatch_lock_token=?,submitted_at=COALESCE(submitted_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$token,(int)$adjustment['id']]);
        $pdo->commit();
        $payload=mg_bundle_reversal_payload($adjustment,$transfer);
        $provider=mg_stripe_api_request($pdo,'POST','/v1/transfers/'.rawurlencode((string)$transfer['provider_transfer_reference']).'/reversals',$payload,'bundle-reversal-'.(string)$adjustment['public_id']);
        $pdo->beginTransaction();mg_bundle_reversal_mark_succeeded($pdo,$adjustment,$transfer,$provider);$pdo->commit();$succeeded++;
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if($adjustment){$pdo->beginTransaction();mg_bundle_reversal_mark_failed($pdo,$adjustment,$e);$pdo->commit();}
        fwrite(STDERR,'[bundle-reversal] '.$e->getMessage().PHP_EOL);$failed++;
    }
    $processed++;
}
fwrite(STDOUT,json_encode(['processed'=>$processed,'succeeded'=>$succeeded,'failed'=>$failed,'payment_mode'=>mg_payment_mode()],JSON_THROW_ON_ERROR).PHP_EOL);
