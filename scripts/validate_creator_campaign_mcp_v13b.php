<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$add = static function (string $label, bool $passed, int $points) use (&$checks): void {
    $checks[] = compact('label', 'passed', 'points');
};
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) throw new RuntimeException('Unable to read ' . $path);
    return $content;
};

$tools = $read('services/mcp/src/tools/creatorCampaignDrafts.ts');
$bridge = $read('api/internal/_mcp_creator_campaign_draft_bridge.php');
$draftBridge = $read('api/internal/_mcp_draft_bridge.php');
$sql = $read('database/20260722_creator_campaign_mcp_draft_scopes_v13b_single_install.sql');
$nodeTest = $read('services/mcp/tests/creatorCampaignDrafts.test.mjs');
$account = $read('includes/mcp-drafts/account-page-phase3b.php');
$view = $read('includes/mcp-drafts/account-page-phase3b-view.php');

$toolNames = [
    'microgifter.creator_campaigns.draft.create','microgifter.creator_campaigns.draft.update',
    'microgifter.creator_campaigns.products.propose','microgifter.creator_campaigns.eligibility.propose',
    'microgifter.creator_campaigns.deliverables.propose','microgifter.creator_campaigns.compensation.propose',
    'microgifter.creator_campaigns.attribution.propose','microgifter.creator_campaigns.budget.propose',
    'microgifter.creator_campaigns.rights.propose','microgifter.creator_campaigns.terms.propose',
    'microgifter.creator_campaigns.invitation.draft','microgifter.creator_campaigns.message.draft',
    'microgifter.creator_campaigns.submission_feedback.draft',
];
$scopeKeys = [
    'creator_campaigns:draft','creator_campaign_products:draft','creator_campaign_eligibility:draft',
    'creator_campaign_deliverables:draft','creator_campaign_compensation:draft','creator_campaign_attribution:draft',
    'creator_campaign_budget:draft','creator_campaign_rights:draft','creator_campaign_terms:draft',
    'creator_campaign_invitations:draft','creator_campaign_messages:draft','creator_campaign_submission_feedback:draft',
];

$add('All 13 Phase 13B proposal tools are registered', array_reduce($toolNames, static fn(bool $ok, string $name): bool => $ok && str_contains($tools, $name), true), 20);
$add('All 12 exact draft scopes are additive and idempotent', array_reduce($scopeKeys, static fn(bool $ok, string $scope): bool => $ok && str_contains($sql, "'{$scope}'"), true) && substr_count($sql, "'draft',1,1,NOW(),NOW())") === 12 && str_contains($sql, 'ON DUPLICATE KEY UPDATE'), 10);
$add('No approval-gated, bounded-auto, or prohibited authority is granted', !preg_match("/'(?:approval_gated|bounded_auto|prohibited)'/", $sql) && !str_contains($tools, '"microgifter.creator_campaigns.publish"') && !str_contains($tools, '"microgifter.creator_campaigns.payout.record"') && !str_contains($tools, '"microgifter.creator_campaigns.invitation.send"'), 10);
$add('The existing MCP draft ledger remains the only proposal write target', str_contains($bridge, 'INSERT INTO mcp_agent_drafts') && str_contains($bridge, 'mg_mcp_draft_event') && !preg_match('/INSERT INTO creator_campaigns|UPDATE creator_campaigns|INSERT INTO creator_campaign_(?:agreements|earning|payout)/', $bridge), 10);
$add('Every proposal enforces its exact granted scope', str_contains($bridge, 'MG_MCP_CREATOR_CAMPAIGN_PROPOSAL_SCOPE_BY_KIND') && str_contains($bridge, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_SCOPE_DENIED'), 8);
$add('Merchant workspace ownership is mandatory', str_contains($bridge, "['merchant', 'merchant_workspace']") && str_contains($bridge, "(int)\$context['workspace_id']"), 8);
$add('Campaign, product, Creator, participant, and submission references are revalidated', str_contains($bridge, 'mg_creator_campaign_repository_by_public_id') && str_contains($bridge, 'mg_creator_campaign_repository_assert_product_owned') && str_contains($bridge, "um.code='creator'") && str_contains($bridge, 'creator_campaign_participants') && str_contains($bridge, 'creator_campaign_submissions'), 8);
$add('Financial, rights, and terms proposals enforce elevated risk floors', str_contains($bridge, "'compensation.propose' => 'high'") && str_contains($bridge, "'budget.propose' => 'high'") && str_contains($bridge, "'rights.propose' => 'high'") && str_contains($bridge, "'terms.propose' => 'high'"), 6);
$add('Native conversion and external effects are explicitly disabled', substr_count($bridge, "'native_conversion_enabled' => false") >= 3 && str_contains($bridge, "'external_effects' => false") && str_contains($account, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_CONVERSION_DISABLED'), 8);
$add('Tools are non-destructive, closed-world, and idempotent', str_contains($tools, 'readOnlyHint: false') && str_contains($tools, 'destructiveHint: false') && str_contains($tools, 'idempotentHint: true') && str_contains($tools, 'openWorldHint: false'), 4);
$add('Node contracts cover scope filtering, review-ledger calls, receipts, and fail-closed behavior', str_contains($nodeTest, 'scope filtered') && str_contains($nodeTest, 'canonical review ledger') && str_contains($nodeTest, 'record a draft receipt') && str_contains($nodeTest, 'fail closed'), 4);
$add('Owner review UI labels proposals and suppresses conversion actions', str_contains($view, 'CREATOR CAMPAIGN') && str_contains($view, 'Awaiting approval-gated canonical actions') && str_contains($view, '$isCreatorCampaignProposal'), 4);

$score = 0;
foreach ($checks as $check) {
    if ($check['passed']) $score += $check['points'];
    echo ($check['passed'] ? 'PASS' : 'FAIL') . ' [' . $check['points'] . '] ' . $check['label'] . PHP_EOL;
}
echo 'Creator Campaign MCP v13B score: ' . $score . '/100' . PHP_EOL;
exit($score === 100 ? 0 : 1);
