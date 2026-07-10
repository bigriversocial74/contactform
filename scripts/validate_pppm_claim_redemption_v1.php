<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
    $value=file_get_contents($root.'/'.$path);
    if(!is_string($value))throw new RuntimeException('Missing '.$path);
    return $value;
};

$claimAuthority=$read('api/microgifts/_claim_authority.php');
$claimRoute=$read('api/microgifts/claim.php');
$actionClaim=$read('api/account/action-center-claim.php');
$customerRedeem=$read('api/microgifts/redeem.php');
$atomic=$read('api/microgifts/_atomic_merchant_redemption.php');
$location=$read('api/microgifts/_location_claim_authority.php');
$reconcile=$read('api/microgifts/_redemption_reconciliation.php');
$merchant=$read('api/merchant/microgift-claim.php');
$repair=$read('api/merchant/microgift-redemption-reconcile.php');
$js=$read('assets/js/merchant-claims.js');

$checks=[
    'canonical_claim_authority'=>str_contains($claimRoute,'mg_microgift_claim_canonical')&&str_contains($actionClaim,'mg_microgift_claim_canonical')&&str_contains($claimAuthority,'function mg_microgift_claim_canonical'),
    'claim_idempotency_conflict'=>str_contains($claimAuthority,'mg_microgift_assert_claim_replay')&&str_contains($claimAuthority,'idempotency_key')&&str_contains($claimAuthority,'instance_id'),
    'pppm_ownership_sync'=>str_contains($claimAuthority,'PPPM ownership is not synchronized')&&str_contains($claimAuthority,'pppm_item_id')&&str_contains($claimAuthority,'owner_user_id'),
    'claim_action_center_projection'=>str_contains($claimAuthority,'mg_action_center_project_lifecycle')&&str_contains($claimAuthority,'recipient_user_id'),
    'customer_redemption_retired'=>str_contains($customerRedeem,'Direct customer redemption has been retired.')&&str_contains($customerRedeem,'canonical_endpoint')&&str_contains($customerRedeem,'/api/merchant/microgift-claim.php')&&!str_contains($customerRedeem,'mg_microgift_redeem('),
    'merchant_location_code_authority'=>str_contains($atomic,'mg_location_claim_resolve_authority')&&str_contains($location,'hash_hmac')&&str_contains($location,'hash_equals')&&str_contains($location,'mg_location_claim_actor_authorized'),
    'atomic_redemption'=>str_contains($atomic,'$started=!$pdo->inTransaction()')&&str_contains($atomic,'beginTransaction')&&str_contains($atomic,'mg_pppm_redeem')&&str_contains($atomic,'mg_location_claim_increment_usage')&&str_contains($atomic,'$pdo->commit()'),
    'completed_redemption_reconciliation'=>str_contains($reconcile,'mg_microgift_reconcile_completed_redemption')&&str_contains($reconcile,'mg_action_center_project_lifecycle')&&str_contains($reconcile,'mg_microgift_redemption_confirmations'),
    'merchant_recovery_workflow'=>str_contains($merchant,'reconciliation_pending')&&str_contains($repair,'mg_microgift_reconcile_completed_redemption')&&str_contains($js,'sessionStorage')&&str_contains($js,'microgift-redemption-reconcile.php'),
    'redeemed_tip_confirmation'=>str_contains($reconcile,'can_tip')&&str_contains($reconcile,'microgift_redemption_confirmations')&&str_contains($reconcile,'pppm_redemption'),
];

$score=0;
foreach($checks as $name=>$passed){echo($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if($passed)$score++;}
echo 'Score: '.$score.'/10'.PHP_EOL;
exit($score===10?0:1);
