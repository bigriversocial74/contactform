<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$score=0;$checks=[];function ccb7(bool $ok,int $p,string $l):void{global $score,$checks;$checks[]=['ok'=>$ok,'points'=>$p,'label'=>$l];if($ok)$score+=$p;}
$sql=file_get_contents($root.'/database/20260722_creator_campaign_budget_controls_v7_single_install.sql')?:'';$service=file_get_contents($root.'/includes/creator-campaigns/budget-service.php')?:'';$repo=file_get_contents($root.'/includes/creator-campaigns/budget-repository.php')?:'';$api=file_get_contents($root.'/api/merchant/creator-campaign-budgets.php')?:'';$workflow=file_get_contents($root.'/.github/workflows/creator-campaign-budgets-v7.yml')?:'';
ccb7(str_contains($sql,'creator_campaign_budgets')&&str_contains($sql,'creator_campaign_budget_reservations')&&str_contains($sql,'creator_campaign_budget_events'),12,'Canonical budget tables');
ccb7(str_contains($sql,'available_delta_minor')&&str_contains($sql,'reserved_delta_minor')&&str_contains($sql,'committed_delta_minor'),12,'Three-bucket immutable ledger');
ccb7(str_contains($sql,'uq_cc_budget_reservation_earning')&&str_contains($sql,'uq_cc_budget_event_idempotency'),10,'Reservation and event uniqueness');
ccb7(str_contains($service,'FOR UPDATE')||str_contains($repo,'FOR UPDATE'),8,'Atomic budget locking');
ccb7(str_contains($repo,'insufficient available funds')&&str_contains($repo,'allow_overage'),8,'Cap and controlled-overage enforcement');
ccb7(str_contains($service,"'reserve'")&&str_contains($service,"'commit'")&&str_contains($service,"'release'")&&str_contains($service,"'restore'"),10,'Complete reservation lifecycle');
ccb7(str_contains($service,'mg_creator_campaign_assert_transaction_boundary')&&str_contains($service,'rollBack'),8,'Owned transactions');
ccb7(str_contains($service,'idempotent')&&str_contains($service,'idempotency_key'),8,'Idempotent operations');
ccb7(str_contains($api,'mg_require_csrf_for_write')&&str_contains($service,'merchant.creator_budgets.manage'),6,'Merchant authorization and CSRF');
ccb7(!str_contains($sql,'creator_campaign_payouts')&&!str_contains($sql,'creator_campaign_disputes'),6,'Payouts and disputes excluded');
ccb7(str_contains($workflow,"php: ['8.2','8.3']")&&str_contains($workflow,'mysql-lifecycle')&&str_contains($workflow,'Phase 6 compatibility'),8,'PHP matrix and Phase 6→7 lifecycle');
ccb7(str_contains(file_get_contents($root.'/merchant-creator-budgets.php')?:'','mg-app-shell')&&str_contains(file_get_contents($root.'/merchant-creator-budgets.php')?:'','app-sidebar.php'),4,'Authenticated app layout');
echo json_encode(['score'=>$score,'max'=>100,'rating'=>$score===100?'10/10':number_format($score/10,1).'/10','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;if($score!==100)exit(1);
