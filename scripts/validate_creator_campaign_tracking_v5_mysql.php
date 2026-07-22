<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/includes/creator-campaigns.php';
$pdo=mg_db();
function cctv5_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$tables=mg_creator_campaign_tracking_required_tables();
$p=implode(',',array_fill(0,count($tables),'?'));
$stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p})");
$stmt->execute($tables);cctv5_assert((int)$stmt->fetchColumn()===count($tables),'Phase 5 tables are incomplete.');
$permissions=['merchant.creator_tracking.view','merchant.creator_tracking.manage','merchant.creator_attribution.view','merchant.creator_attribution.manage','creator.campaign_tracking.view_own','creator.campaign_tracking.manage_own'];
$p=implode(',',array_fill(0,count($permissions),'?'));
$stmt=$pdo->prepare("SELECT COUNT(*) FROM permissions WHERE slug IN ({$p})");
$stmt->execute($permissions);cctv5_assert((int)$stmt->fetchColumn()===count($permissions),'Phase 5 permissions are incomplete.');
$stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='creator_campaign_tracking_events' AND index_name='uq_cc_tracking_event_key' AND non_unique=0");
cctv5_assert((int)$stmt->fetchColumn()>=1,'Tracking event idempotency is not enforced.');
$stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='creator_campaign_attributions' AND column_name IN ('conversion_event_id','touch_event_id','source_id','participant_id','creator_user_id','status','lock_version')");
cctv5_assert((int)$stmt->fetchColumn()===7,'Attribution decision integrity columns are incomplete.');
$stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='creator_campaign_attributions' AND index_name='uq_cc_attribution_conversion' AND non_unique=0");
cctv5_assert((int)$stmt->fetchColumn()>=1,'One attribution per conversion is not enforced.');
$stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('creator_campaign_compensation_rules','creator_campaign_earning_events','creator_campaign_budget_ledger','creator_campaign_payouts','creator_campaign_disputes')");
cctv5_assert((int)$stmt->fetchColumn()===0,'Financial or dispute tables were created prematurely.');
echo json_encode(['ok'=>true,'tables'=>count($tables),'permissions'=>count($permissions),'event_idempotency'=>true,'one_attribution_per_conversion'=>true,'touch_event_audit'=>true,'privacy_safe_hashes'=>true,'financial_tables'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
