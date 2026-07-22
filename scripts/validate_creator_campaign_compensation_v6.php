<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$score=0;$checks=[];
function ccv6_check(bool $ok,int $points,string $label):void{global $score,$checks;$checks[]=['ok'=>$ok,'points'=>$points,'label'=>$label];if($ok)$score+=$points;}
$sql=file_get_contents($root.'/database/20260722_creator_campaign_compensation_earnings_v6_single_install.sql')?:'';
$defs=file_get_contents($root.'/includes/creator-campaigns/compensation-definitions.php')?:'';
$service=file_get_contents($root.'/includes/creator-campaigns/compensation-service.php')?:'';
$repo=file_get_contents($root.'/includes/creator-campaigns/compensation-repository.php')?:'';
$merchantApi=file_get_contents($root.'/api/merchant/creator-campaign-compensation.php')?:'';
$creatorApi=file_get_contents($root.'/api/creator/campaign-earnings.php')?:'';
$workflow=file_get_contents($root.'/.github/workflows/creator-campaign-compensation-v6.yml')?:'';
ccv6_check(str_contains($sql,'creator_campaign_compensation_rules')&&str_contains($sql,'creator_campaign_compensation_rule_versions')&&str_contains($sql,'creator_campaign_earning_events'),12,'Canonical Phase 6 tables');
ccv6_check(str_contains($sql,'uq_cc_comp_version_number')&&str_contains($sql,'uq_cc_comp_version_hash'),10,'Immutable rule versions');
ccv6_check(str_contains($sql,'uq_cc_earning_idempotency')&&str_contains($sql,'uq_cc_earning_reversal'),10,'Duplicate and reversal uniqueness');
ccv6_check(str_contains($sql,'amount_minor BIGINT')&&str_contains($sql,'rate_bps'),8,'Integer-only money and rates');
ccv6_check(str_contains($sql,'agreement_version_id')&&str_contains($sql,'rule_version_id'),8,'Agreement and rule version traceability');
ccv6_check(str_contains($service,'mg_creator_campaign_assert_transaction_boundary')&&str_contains($service,'beginTransaction')&&str_contains($service,'rollBack'),8,'Owned transactions');
ccv6_check(str_contains($service,'idempotency_hash')&&str_contains($service,'idempotent'),8,'Idempotent earning ingestion');
ccv6_check(str_contains($service,'reversal_of_event_id')&&str_contains($service,"event_type']==='reversal'"),8,'Append-only reversals');
ccv6_check(str_contains($repo,"status']!=='verified'")&&str_contains($repo,"['attributed','overridden']"),7,'Verified source eligibility');
ccv6_check(str_contains($merchantApi,'mg_require_csrf_for_write')&&str_contains($service,'merchant.creator_compensation.manage'),5,'Merchant authorization and CSRF');
ccv6_check(str_contains($creatorApi,'creator.campaign_earnings.view_own')||str_contains(file_get_contents($root.'/includes/creator-campaigns/compensation-query.php')?:'','creator.campaign_earnings.view_own'),5,'Creator ownership scope');
ccv6_check(!str_contains($sql,'creator_campaign_budget_ledger')&&!str_contains($sql,'creator_campaign_payouts')&&!str_contains($sql,'creator_campaign_disputes'),5,'Later financial domains excluded');
ccv6_check(str_contains($workflow,"php: ['8.2','8.3']")&&str_contains($workflow,'mysql-lifecycle')&&str_contains($workflow,'Phase 5 compatibility'),6,'PHP matrix, MySQL, compatibility');
echo json_encode(['score'=>$score,'max'=>100,'rating'=>$score===100?'10/10':number_format($score/10,1).'/10','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
if($score!==100)exit(1);
