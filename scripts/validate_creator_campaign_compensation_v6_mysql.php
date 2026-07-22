<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/includes/creator-campaigns.php';
$pdo=mg_db();function ccv6m(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$tables=mg_creator_campaign_compensation_required_tables();$p=implode(',',array_fill(0,count($tables),'?'));$stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p})");$stmt->execute($tables);ccv6m((int)$stmt->fetchColumn()===count($tables),'Phase 6 tables are incomplete.');
$permissions=['merchant.creator_compensation.view','merchant.creator_compensation.manage','merchant.creator_earnings.view','creator.campaign_earnings.view_own'];$p=implode(',',array_fill(0,count($permissions),'?'));$stmt=$pdo->prepare("SELECT COUNT(*) FROM permissions WHERE slug IN ({$p})");$stmt->execute($permissions);ccv6m((int)$stmt->fetchColumn()===count($permissions),'Phase 6 permissions are incomplete.');
$stmt=$pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='creator_campaign_earning_events' AND index_name IN ('uq_cc_earning_idempotency','uq_cc_earning_reversal') AND non_unique=0");ccv6m((int)$stmt->fetchColumn()===2,'Earning idempotency or reversal uniqueness missing.');
$stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('creator_campaign_budget_ledger','creator_campaign_payouts','creator_campaign_disputes')");ccv6m((int)$stmt->fetchColumn()===0,'Later financial tables were created prematurely.');
echo json_encode(['ok'=>true,'tables'=>count($tables),'permissions'=>count($permissions),'immutable_rules'=>true,'append_only_earnings'=>true,'later_phase_tables'=>0],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
