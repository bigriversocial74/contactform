<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/bootstrap.php';require_once dirname(__DIR__).'/includes/creator-campaigns.php';$pdo=mg_db();function ccb7m(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$t=mg_creator_campaign_budget_required_tables();$p=implode(',',array_fill(0,count($t),'?'));$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p})");$s->execute($t);ccb7m((int)$s->fetchColumn()===3,'Phase 7 tables incomplete.');
$s=$pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='creator_campaign_budget_reservations' AND index_name IN ('uq_cc_budget_reservation_earning','uq_cc_budget_reservation_idempotency') AND non_unique=0");ccb7m((int)$s->fetchColumn()===2,'Reservation uniqueness missing.');
$s=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('creator_campaign_payouts','creator_campaign_disputes')");ccb7m((int)$s->fetchColumn()===0,'Later financial tables created prematurely.');
echo json_encode(['ok'=>true,'tables'=>3,'bucket_ledger'=>true,'reservation_uniqueness'=>true,'later_phase_tables'=>0],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
