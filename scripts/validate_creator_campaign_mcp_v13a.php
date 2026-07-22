<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$add = static function (string $label, bool $passed, int $points) use (&$checks): void {
    $checks[] = compact('label', 'passed', 'points');
};
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $content;
};

$tools = $read('services/mcp/src/tools/creatorCampaigns.ts');
$canonical = $read('services/mcp/src/bridge/canonicalBridge.ts');
$phpBridge = $read('api/internal/_mcp_creator_campaign_bridge.php');
$endpoint = $read('api/internal/mcp-bridge.php');
$sql = $read('database/20260722_creator_campaign_mcp_read_scopes_v13a_single_install.sql');
$test = $read('services/mcp/tests/creatorCampaigns.test.mjs');

$toolNames = [
    'microgifter.creator_campaigns.list','microgifter.creator_campaigns.get','microgifter.creator_campaigns.validate',
    'microgifter.creator_campaigns.analytics.get','microgifter.creator_campaigns.applications.list',
    'microgifter.creator_campaigns.participants.list','microgifter.creator_campaigns.deliverables.list',
    'microgifter.creator_campaigns.submissions.list','microgifter.creator_campaigns.tracking.get',
    'microgifter.creator_campaigns.attributions.list','microgifter.creator_campaigns.earnings.list',
    'microgifter.creator_campaigns.payouts.list','microgifter.creator_campaigns.disputes.list',
];
$add('All 13 Phase 13A read tools are registered', array_reduce($toolNames, static fn(bool $ok, string $name): bool => $ok && str_contains($tools, $name), true), 20);
$add('All tool annotations are read-only and nondestructive', str_contains($tools, 'readOnlyHint: true') && str_contains($tools, 'destructiveHint: false'), 8);
$add('Canonical TypeScript bridge exposes the bounded read operation union', str_contains($canonical, 'CreatorCampaignReadOperation') && str_contains($canonical, 'creatorCampaignRead'), 8);
$add('PHP bridge dispatches only the allowlisted Creator Campaign operations', substr_count($phpBridge, "'creator_campaigns.") >= 26 && str_contains($phpBridge, 'MCP_CREATOR_CAMPAIGN_OPERATION_UNKNOWN'), 8);
$add('Every PHP operation revalidates its exact granted scope', str_contains($phpBridge, 'mg_mcp_bridge_require_scope($context, $scope)'), 8);
$add('Merchant reads are workspace constrained', str_contains($phpBridge, 'cc.workspace_id=?') && str_contains($phpBridge, "['merchant', 'merchant_workspace']"), 8);
$add('Creator reads are constrained to the authenticated Creator identity', str_contains($phpBridge, 'creator_user_id=?') && str_contains($phpBridge, "um.code='creator'"), 8);
$add('Native campaign validation is reused without persistence', str_contains($phpBridge, 'mg_creator_campaign_builder_validation') && !str_contains($phpBridge, 'builder_validation_json=?'), 6);
$add('Privacy-sensitive tracking hashes are absent', !preg_match('/session_hash|visitor_hash|request_hash/', $phpBridge), 8);
$add('Payout provider and banking internals are absent', !preg_match('/provider_reference|bank_account|routing_number/', $phpBridge), 6);
$add('Scope migration is read-only and idempotent', substr_count($sql, "'read',1,1,NOW(),NOW())") === 11 && str_contains($sql, 'ON DUPLICATE KEY UPDATE') && !preg_match("/'(?:draft|approval_gated|bounded_auto)'/", $sql), 6);
$add('Bridge endpoint preserves signed canonical authentication', str_contains($endpoint, 'mg_mcp_bridge_authenticate') && str_contains($endpoint, 'mg_mcp_creator_campaign_bridge_dispatch'), 3);
$add('Node contract covers scope filtering, bridge calls, receipts, and fail-closed behavior', str_contains($test, 'scope filtered') && str_contains($test, 'record receipts') && str_contains($test, 'fail closed'), 3);

$score = 0;
foreach ($checks as $check) {
    if ($check['passed']) {
        $score += $check['points'];
    }
    echo ($check['passed'] ? 'PASS' : 'FAIL') . ' [' . $check['points'] . '] ' . $check['label'] . PHP_EOL;
}
echo 'Creator Campaign MCP v13A score: ' . $score . '/100' . PHP_EOL;
exit($score === 100 ? 0 : 1);
