<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[];
$read=static function(string $path)use($root,&$files):string{
    if(!array_key_exists($path,$files)){
        $value=file_get_contents($root.'/'.$path);
        if(!is_string($value))throw new RuntimeException('Unable to read '.$path);
        $files[$path]=$value;
    }
    return $files[$path];
};
$contains=static fn(string $path,string $needle):bool=>str_contains($read($path),$needle);
$checks=[];
$add=static function(string $name,bool $passed,string $detail='')use(&$checks):void{$checks[]=['name'=>$name,'passed'=>$passed,'detail'=>$detail];};

$migration='database/20260730_creator_affiliate_operations_experience_v16.sql';
$service='includes/creator-campaigns/operations-service.php';
$payoutService='includes/creator-campaigns/payout-service.php';
$merchantView='includes/merchant-creator-affiliate-operations-view.php';
$merchantJs='assets/js/merchant-creator-affiliate-operations.js';
$earningsQuery='includes/creator-campaigns/compensation-query.php';
$payoutQuery='includes/creator-campaigns/payout-query.php';
$docs='docs/creator-campaigns/CREATOR_AFFILIATE_OPERATIONS_EXPERIENCE_V16.md';

$add('Payout policy table', $contains($migration,'CREATE TABLE IF NOT EXISTS creator_campaign_payout_policies'));
$add('Persistent reconciliation table', $contains($migration,'CREATE TABLE IF NOT EXISTS creator_campaign_reconciliation_cases'));
$add('Workspace-scoped policy uniqueness', $contains($migration,'uq_cc_payout_policy_workspace_currency'));
$add('Manual approval database constraint', $contains($migration,'CHECK (manual_approval_required = 1)'));
$add('Idempotent migration receipt', $contains($migration,'20260730_creator_affiliate_operations_experience_v16')&&$contains($migration,'ON DUPLICATE KEY UPDATE'));

$add('Safe default hold period', $contains($service,"'hold_days'=>7"));
$add('Safe default minimum payout', $contains($service,"'minimum_payout_minor'=>2500"));
$add('Safe default manual cadence', $contains($service,"'cadence'=>'manual'"));
$add('Policy save keeps manual approval', $contains($service,'manual_approval_required=1'));
$add('Next payout date calculation', $contains($service,'mg_creator_campaign_operations_next_payout_date'));

$add('Paid order lifecycle detector', $contains($service,'paid_order_incomplete'));
$add('Missing earning detector', $contains($service,'attribution_missing_earning'));
$add('Missing budget reservation detector', $contains($service,'earning_missing_reservation'));
$add('Refund adjustment detector', $contains($service,'refund_missing_adjustment'));
$add('Payout attention detector', $contains($service,'payout_needs_attention'));
$add('Dispute detector', $contains($service,'active_dispute'));
$add('Suspect tracking detector', $contains($service,'suspect_tracking_activity'));
$add('Scanner failure is fail-safe', $contains($service,'reconciliation_scan_error')&&$contains($service,"if(\$detected['errors']===[])"));
$add('Persistent fingerprinted cases', $contains($service,"hash('sha256',\$candidate['type'].'|'.\$candidate['sourceType'].'|'.\$candidate['sourcePublicId'])"));
$add('Clean scan resolves stale cases', $contains($service,"status='resolved',resolved_at=NOW()"));

$add('Hold period enforced in payout assembly', $contains($payoutService,'r.committed_at<=?')&&$contains($payoutService,'hold_days'));
$add('Effective minimum uses policy and profile', $contains($payoutService,"max((int)\$profile['minimum_payout_minor'],\$policyMinimum)"));
$add('Paused policy blocks payout assembly', $contains($payoutService,'The merchant payout policy is paused.'));
$add('Payout starts in draft', $contains($payoutService,"\$total,'draft'"));
$add('Provider reference remains required', $contains($payoutService,'provider_reference is required before marking a payout paid'));

$add('Merchant operations page', is_file($root.'/merchant-creator-affiliate-operations.php'));
$add('Payout policy form', $contains($merchantView,'data-caops-policy-form'));
$add('Campaign readiness workspace', $contains($merchantView,'Campaign readiness'));
$add('Guided Creator eligibility', $contains($merchantJs,'data-profile-participant'));
$add('Guided payout creation', $contains($merchantJs,'data-payout-participant'));
$add('Reconciliation action queue', $contains($merchantJs,'data-case-action'));
$add('Merchant navigation entry', $contains('includes/merchant-navigation.php','Affiliate Operations'));

$add('Creator earning reservation visibility', $contains($earningsQuery,'reservation_status'));
$add('Creator earning payout visibility', $contains($earningsQuery,'payout_status'));
$add('Creator lifecycle status', $contains($earningsQuery,'lifecycle_status'));
$add('Creator paid total', $contains($earningsQuery,"'paid_minor'"));
$add('Creator payout policy visibility', $contains($payoutQuery,'operations_creator_policies'));
$add('Creator payout timeline fields', $contains($payoutQuery,'processing_at')&&$contains($payoutQuery,'paid_at'));
$add('Creator plain-language status guide', $contains($payoutQuery,'status_guide'));

$add('Provider-neutral boundary documented', $contains($docs,'does not:')&&$contains($docs,'call Stripe transfers'));
$add('Tax boundary documented', $contains($docs,'file or calculate tax forms'));
$add('Refund clawback behavior documented', $contains($docs,'Refund and clawback behavior'));
$add('Controlled smoke test documented', $contains($docs,'Production smoke test'));

$total=count($checks);
$passed=count(array_filter($checks,static fn(array $check):bool=>$check['passed']));
$score=$total>0?(int)round(($passed/$total)*100):0;
foreach($checks as $check)echo ($check['passed']?'PASS':'FAIL').' | '.$check['name'].($check['detail']!==''?' | '.$check['detail']:'').PHP_EOL;
echo "SCORE {$score}/100 ({$passed}/{$total})".PHP_EOL;
exit($score===100?0:1);
