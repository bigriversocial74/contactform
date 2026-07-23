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

$tools = $read('services/mcp/src/tools/creatorCampaignPlaybooks.ts');
$nodeTest = $read('services/mcp/tests/creatorCampaignPlaybooks.test.mjs');
$sql = $read('database/20260722_creator_campaign_mcp_bounded_playbooks_v13d_single_install.sql');
$catalog = $read('includes/mcp-automations/bootstrap.php');
$definitions = $read('includes/mcp-automations/definitions.php');
$definitionView = $read('includes/mcp-automations/definitions-page-view.php');
$bridge = $read('api/internal/_mcp_creator_campaign_playbook_bridge.php')
    . $read('api/internal/mcp-bridge.php');
$service = $read('includes/mcp-creator-campaign-playbooks/service.php');
$common = $read('includes/mcp-creator-campaign-playbooks/common.php');
$builders = $read('includes/mcp-creator-campaign-playbooks/campaign-preparation.php')
    . $read('includes/mcp-creator-campaign-playbooks/review-builders.php')
    . $read('includes/mcp-creator-campaign-playbooks/insight-builders.php');
$allPlaybookPhp = $read('includes/mcp-creator-campaign-playbooks/bootstrap.php')
    . $common . $builders . $service;
$legacySimulation = $read('includes/mcp-automations/simulations.php');

$toolNames = [
    'microgifter.creator_campaigns.playbooks.campaign_preparation.run',
    'microgifter.creator_campaigns.playbooks.application_review.run',
    'microgifter.creator_campaigns.playbooks.content_review.run',
    'microgifter.creator_campaigns.playbooks.campaign_health.run',
    'microgifter.creator_campaigns.playbooks.earnings_review.run',
    'microgifter.creator_campaigns.playbooks.creator_outreach.run',
];
$scopeKeys = [
    'creator_campaign_playbooks:campaign_preparation',
    'creator_campaign_playbooks:application_review',
    'creator_campaign_playbooks:content_review',
    'creator_campaign_playbooks:campaign_health',
    'creator_campaign_playbooks:earnings_review',
    'creator_campaign_playbooks:creator_outreach',
];
$playbookKeys = [
    'creator_campaign_campaign_preparation',
    'creator_campaign_application_review',
    'creator_campaign_content_review',
    'creator_campaign_health',
    'creator_campaign_earnings_review',
    'creator_campaign_creator_outreach',
];

$add(
    'All six Phase 13D tools are exact and registered',
    array_reduce($toolNames, static fn(bool $ok, string $name): bool => $ok && str_contains($tools, $name), true)
        && substr_count($tools, 'register(') >= 7,
    15
);
$add(
    'All six scopes are additive grantable draft scopes',
    array_reduce($scopeKeys, static fn(bool $ok, string $scope): bool => $ok && str_contains($sql, "'{$scope}'"), true)
        && substr_count($sql, "'draft',1,1,NOW(),NOW())") === 6
        && str_contains($sql, 'ON DUPLICATE KEY UPDATE')
        && !str_contains($sql, "'bounded_auto'")
        && !str_contains($sql, "'prohibited'"),
    10
);
$add(
    'Fixed playbooks require owner grant and matching active definition',
    array_reduce($playbookKeys, static fn(bool $ok, string $key): bool => $ok && str_contains($catalog, "'{$key}'"), true)
        && str_contains($service, 'mg_mcp_automation_lock_owner_definition')
        && str_contains($service, "automation['status'] !== 'active'")
        && str_contains($service, "automation['grant_status'] !== 'active'")
        && str_contains($service, 'MCP_CREATOR_CAMPAIGN_PLAYBOOK_DEFINITION_MISMATCH'),
    10
);
$add(
    'Every playbook is manual and scheduler-disabled',
    str_contains($definitions, "'manual_bounded_playbook'")
        && str_contains($definitions, "'review_artifact_only' => \$boundedReview")
        && str_contains($definitions, "'execution_requested' => false")
        && str_contains($service, "trigger_type='manual'")
        && str_contains($service, "'scheduler_enabled' => false"),
    8
);
$add(
    'One non-convertible owner-review artifact is created per successful run',
    str_contains($service, 'INSERT INTO mcp_agent_drafts')
        && str_contains($service, "'pending_review'")
        && str_contains($service, "'creator_campaign_playbook_output' => true")
        && str_contains($service, "'native_conversion_enabled' => false")
        && str_contains($service, 'mg_mcp_draft_event'),
    10
);
$add(
    'Canonical campaign ownership and resource state are revalidated',
    str_contains($bridge, 'mg_mcp_draft_bridge_authenticate')
        && str_contains($bridge, "['merchant', 'merchant_workspace']")
        && str_contains($common, 'mg_mcp_creator_campaign_bridge_dispatch')
        && str_contains($common, 'mg_creator_campaign_repository_by_public_id')
        && str_contains($common, 'MCP_CREATOR_CAMPAIGN_PLAYBOOK_RESOURCE_NOT_FOUND'),
    10
);
$add(
    'No canonical action request, native mutation, payment, or external effect is enabled',
    !str_contains($allPlaybookPhp, 'mg_mcp_creator_campaign_action_request(')
        && !str_contains($allPlaybookPhp, 'mg_creator_campaign_transition_status(')
        && !str_contains($allPlaybookPhp, 'mg_creator_campaign_payout_create(')
        && str_contains($service, "'canonical_action_request_created' => false")
        && str_contains($service, "'canonical_mutation_enabled' => false")
        && str_contains($service, "'payment_provider_enabled' => false")
        && str_contains($service, "'external_effects' => false"),
    10
);
$add(
    'Automation run, action, canonical receipt, audit, and security evidence are recorded',
    str_contains($service, 'INSERT INTO mcp_automation_runs')
        && str_contains($service, 'INSERT INTO mcp_automation_actions')
        && str_contains($service, 'INSERT INTO mcp_action_receipts')
        && str_contains($service, 'mg_audit')
        && str_contains($service, 'mg_event')
        && str_contains($service, 'mg_security_log'),
    8
);
$add(
    'Review and financial playbooks preserve decision boundaries',
    str_contains($builders, "'application_decision_enabled' => false")
        && str_contains($builders, "'submission_decision_enabled' => false")
        && str_contains($builders, "'earning_decision_enabled' => false")
        && str_contains($builders, "'payout_record_enabled' => false")
        && str_contains($builders, "'invitation_send_enabled' => false"),
    8
);
$add(
    'Creator outreach accepts active approved Microgifter Creators only',
    str_contains($common, "um.code='creator'")
        && str_contains($common, "cp.status='active'")
        && str_contains($builders, "'existing_approved_creators_only' => true")
        && str_contains($builders, 'blocked_candidates'),
    4
);
$add(
    'Node contracts prove scope filtering, receipts, canonical bridge use, and fail-closed behavior',
    str_contains($nodeTest, 'scope filtered')
        && str_contains($nodeTest, 'canonical bounded-playbook bridge')
        && str_contains($nodeTest, 'records a draft receipt')
        && str_contains($nodeTest, 'fail closed'),
    4
);
$add(
    'Phase 4B simulation boundary remains intact',
    str_contains($legacySimulation, "'execution_attempted' => false")
        && str_contains($legacySimulation, "'action_receipts_created' => 0")
        && !str_contains($legacySimulation, 'INSERT INTO mcp_action_receipts')
        && str_contains($definitionView, 'Simulation-only deployment state')
        && str_contains($definitionView, 'No scheduler or canonical action path exists in Phase 4B'),
    3
);

$score = 0;
foreach ($checks as $check) {
    if ($check['passed']) {
        $score += $check['points'];
    }
    echo ($check['passed'] ? 'PASS' : 'FAIL') . ' [' . $check['points'] . '] ' . $check['label'] . PHP_EOL;
}
echo 'Creator Campaign MCP v13D score: ' . $score . '/100' . PHP_EOL;
exit($score === 100 ? 0 : 1);
