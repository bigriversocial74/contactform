<?php
declare(strict_types=1);
require_once __DIR__.'/_provider_reversal.php';
function mg_bundle_release_control(PDO $pdo,string $environment):array{
 $stmt=$pdo->prepare("SELECT * FROM gift_bundle_release_controls WHERE release_key='product_bundles' AND environment=? LIMIT 1");$stmt->execute([$environment]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Bundle release control is not installed.');return $row;
}
function mg_bundle_release_checks(PDO $pdo,string $environment):array{
 $q=fn(string $sql)=>(int)$pdo->query($sql)->fetchColumn();
 $checks=[
  'failed_transfers'=>$q("SELECT COUNT(*) FROM gift_bundle_settlement_transfers WHERE transfer_status='failed'"),
  'failed_reversals'=>$q("SELECT COUNT(*) FROM gift_bundle_settlement_adjustments WHERE adjustment_status='failed'"),
  'open_dead_letters'=>$q("SELECT COUNT(*) FROM gift_bundle_provider_dead_letters WHERE status IN ('open','retrying')"),
  'critical_incidents'=>$q("SELECT COUNT(*) FROM gift_bundle_settlement_incidents WHERE status IN ('open','investigating') AND severity='critical'"),
  'stale_provider_events'=>$q("SELECT COUNT(*) FROM gift_bundle_provider_events WHERE processing_status='received' AND received_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)"),
 ];
 $penalty=min(100,$checks['failed_transfers']*10+$checks['failed_reversals']*10+$checks['open_dead_letters']*5+$checks['critical_incidents']*25+$checks['stale_provider_events']*5);
 $score=100-$penalty;$status=$score>=95?'healthy':($score>=80?'degraded':'blocked');return ['environment'=>$environment,'score'=>$score,'status'=>$status,'checks'=>$checks];
}
function mg_bundle_release_assert_runtime_allowed(PDO $pdo,string $operation):void{
 $env=mg_payment_mode()==='live'?'live':'test';$c=mg_bundle_release_control($pdo,$env);if((int)$c['emergency_stop']===1)throw new RuntimeException('Bundle emergency stop is active.');if((string)$c['rollout_stage']==='disabled')throw new RuntimeException('Bundle release is disabled.');if($operation==='transfer'&&(int)$c['transfers_enabled']!==1)throw new RuntimeException('Bundle transfers are disabled by release control.');if($operation==='reversal'&&(int)$c['reversals_enabled']!==1)throw new RuntimeException('Bundle reversals are disabled by release control.');
}
