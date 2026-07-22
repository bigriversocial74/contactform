<?php
declare(strict_types=1);

$root=dirname(__DIR__);$checks=[];
$add=static function(string $label,bool $passed,int $points)use(&$checks):void{$checks[]=compact('label','passed','points');};
$read=static function(string $path)use($root):string{$value=file_get_contents($root.'/'.$path);if(!is_string($value))throw new RuntimeException('Unable to read '.$path);return $value;};
$tools=$read('services/mcp/src/tools/creatorCampaignActions.ts');
$bridge=$read('api/internal/_mcp_creator_campaign_action_bridge.php').$read('api/internal/mcp-bridge.php');
$request=$read('includes/mcp-creator-campaign-actions/request-service.php');
$owner=$read('includes/mcp-creator-campaign-actions/owner-service.php').$read('includes/mcp-creator-campaign-actions/owner-page-view.php');
$execute=$read('includes/mcp-creator-campaign-actions/execution-service.php');
$catalog=$read('includes/mcp-creator-campaign-actions/bootstrap.php');
$oauth=$read('includes/mcp-oauth/operation-classes.php').$read('includes/mcp-oauth/clients.php');
$grants=$read('includes/mcp-automations/bootstrap.php').$read('includes/mcp-automations/create-grant.php').$read('includes/mcp-automations/authorize-action.php');
$sql=$read('database/20260722_creator_campaign_mcp_canonical_actions_v13c_single_install.sql');
$node=$read('services/mcp/tests/creatorCampaignActions.test.mjs');
$earning=$read('includes/creator-campaigns/earning-service.php');
$docs=$read('docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE13C_MCP_CANONICAL_ACTIONS.md');
$toolNames=[
'microgifter.creator_campaigns.publish','microgifter.creator_campaigns.schedule','microgifter.creator_campaigns.pause','microgifter.creator_campaigns.resume','microgifter.creator_campaigns.complete','microgifter.creator_campaigns.cancel','microgifter.creator_campaigns.application.approve','microgifter.creator_campaigns.application.decline','microgifter.creator_campaigns.invitation.send','microgifter.creator_campaigns.agreement.offer','microgifter.creator_campaigns.participant.suspend','microgifter.creator_campaigns.participant.remove','microgifter.creator_campaigns.submission.approve','microgifter.creator_campaigns.submission.request_revision','microgifter.creator_campaigns.submission.reject','microgifter.creator_campaigns.attribution.override','microgifter.creator_campaigns.earning.approve','microgifter.creator_campaigns.earning.hold','microgifter.creator_campaigns.earning.reject','microgifter.creator_campaigns.earning.reverse','microgifter.creator_campaigns.payout.record','microgifter.creator_campaigns.dispute.resolve'];
$scopeKeys=['creator_campaigns:publish','creator_campaign_participants:manage','creator_campaign_agreements:manage','creator_campaign_submissions:review','creator_campaign_attribution:manage','creator_campaign_earnings:manage','creator_campaign_payouts:manage','creator_campaign_disputes:manage'];
$nativeCalls=['mg_creator_campaign_transition_status','mg_creator_campaign_application_review_merchant','mg_creator_campaign_invitation_create_merchant','mg_creator_campaign_agreement_offer_merchant','mg_creator_campaign_participant_transition_merchant','mg_creator_campaign_submission_review_merchant','mg_creator_campaign_attribution_override_merchant','mg_creator_campaign_earning_decide_merchant','mg_creator_campaign_payout_create','mg_creator_campaign_dispute_transition'];

$add('All 22 Phase 13C tools are registered',array_reduce($toolNames,static fn(bool $ok,string $name):bool=>$ok&&str_contains($tools,$name)&&str_contains($catalog,$name),true),14);
$add('All 8 exact scopes are approval-gated, active, grantable, and idempotent',array_reduce($scopeKeys,static fn(bool $ok,string $scope):bool=>$ok&&str_contains($sql,"'{$scope}'"),true)&&substr_count($sql,"'approval_gated',1,1,NOW(),NOW())")===8&&str_contains($sql,'ON DUPLICATE KEY UPDATE'),10);
$add('MCP tools are request-only and report no execution',str_contains($tools,'requestCreatorCampaignAction')&&str_contains($tools,'performed:false')&&str_contains($tools,'waiting_for_owner_approval')&&!str_contains($tools,'executeCreatorCampaignAction'),8);
$add('The MCP bridge exposes request only',str_contains($bridge,"creator_campaign_actions.request")&&str_contains($bridge,'mg_mcp_creator_campaign_action_request')&&!str_contains($bridge,'mg_mcp_creator_campaign_action_execute'),7);
$add('Requests write only MCP authority and approval evidence',str_contains($request,'INSERT INTO mcp_automation_runs')&&str_contains($request,'INSERT INTO mcp_automation_actions')&&str_contains($request,'INSERT INTO mcp_creator_campaign_action_approvals')&&!preg_match('/(?:INSERT INTO|UPDATE|DELETE FROM) creator_campaign_/', $request),7);
$add('Approval and execution are separate owner actions',str_contains($owner,"value=\"approve\"")&&str_contains($owner,'Execute approved action')&&str_contains($owner,'confirm_execute')&&str_contains($owner,'mg_mcp_creator_campaign_action_execute'),8);
$add('Execution revalidates authority, limits, and fresh state',str_contains($execute,'mg_mcp_automation_authorize_grant_action')&&str_contains($execute,'MCP_CREATOR_CAMPAIGN_ACTION_STATE_CHANGED')&&str_contains($execute,'fresh_state_token')&&str_contains($execute,'approval_expires_at'),8);
$add('All canonical effects route through native Creator Campaign services',array_reduce($nativeCalls,static fn(bool $ok,string $call):bool=>$ok&&str_contains($execute,$call),true),10);
$add('Native earning decisions are append-safe and permissioned',str_contains($earning,"merchant.creator_earnings.manage")&&str_contains($earning,'mg_creator_campaign_compensation_reverse')&&str_contains($earning,'creator_campaign_earning_reviews')&&str_contains($sql,'merchant.creator_earnings.manage'),6);
$add('Action receipts preserve before/after evidence',str_contains($execute,'INSERT INTO mcp_action_receipts')&&str_contains($execute,'before_state_token')&&str_contains($execute,'after_state_token')&&str_contains($execute,'result_reference_type'),5);
$add('Dynamic OAuth remains read-only while preregistered clients may be approval-gated',str_contains($oauth,"$registrationType==='dynamic'?'read'")&&str_contains($oauth,"['read', 'draft', 'approval_gated']")&&str_contains($oauth,'owner_execution_required'),5);
$add('Fixed playbooks and critical ceilings prevent arbitrary authority',str_contains($grants,'creator_campaign_lifecycle_actions')&&str_contains($grants,'creator_campaign_financial_actions')&&str_contains($grants,"$riskCeiling!=='critical'")&&str_contains($grants,'allowed_tools_json'),4);
$add('Financial execution stays internal-only',str_contains($docs,'does not:')&&str_contains($docs,'call a payment provider')&&str_contains($docs,'internal Microgifter draft payout record')&&!str_contains($execute,'provider_reference'),4);
$add('Node contracts prove filtering, request-only behavior, receipts, and fail-closed behavior',str_contains($node,'exactly 22')&&str_contains($node,'never execute canonical actions')&&str_contains($node,'operationClass,"approval_gated"')&&str_contains($node,'fails closed'),4);

$score=0;foreach($checks as $check){if($check['passed'])$score+=$check['points'];echo($check['passed']?'PASS':'FAIL').' ['.$check['points'].'] '.$check['label'].PHP_EOL;}echo'Creator Campaign MCP v13C score: '.$score.'/100'.PHP_EOL;exit($score===100?0:1);
