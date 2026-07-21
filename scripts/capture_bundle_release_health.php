<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/api/bundles/_release_readiness.php';
$pdo=mg_db();$environment=mg_payment_mode()==='live'?'live':'test';$health=mg_bundle_release_checks($pdo,$environment);
$counts=[
 'transfers_pending'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_transfers WHERE transfer_status IN ('created','submitted')")->fetchColumn(),
 'transfers_failed'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_transfers WHERE transfer_status='failed'")->fetchColumn(),
 'reversals_pending'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_adjustments WHERE adjustment_status IN ('dispatch_pending','submitted')")->fetchColumn(),
 'reversals_failed'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_adjustments WHERE adjustment_status='failed'")->fetchColumn(),
 'open_dead_letters'=>$health['checks']['open_dead_letters'],'critical_incidents'=>$health['checks']['critical_incidents'],'stale_provider_events'=>$health['checks']['stale_provider_events']
];
$pdo->prepare("INSERT INTO gift_bundle_release_health_snapshots (public_id,environment,overall_status,readiness_score,transfers_pending,transfers_failed,reversals_pending,reversals_failed,open_dead_letters,critical_incidents,stale_provider_events,checks_json,captured_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
 ->execute([mg_public_uuid(),$environment,$health['status'],$health['score'],$counts['transfers_pending'],$counts['transfers_failed'],$counts['reversals_pending'],$counts['reversals_failed'],$counts['open_dead_letters'],$counts['critical_incidents'],$counts['stale_provider_events'],json_encode($health['checks'],JSON_THROW_ON_ERROR)]);
fwrite(STDOUT,json_encode(['health'=>$health,'counts'=>$counts],JSON_THROW_ON_ERROR).PHP_EOL);
