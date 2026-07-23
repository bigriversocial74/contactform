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

$sql = $read('database/20260722_creator_campaign_production_pilot_v14_single_install.sql');
$page = $read('account-creator-campaign-pilot.php') . $read('includes/creator-campaign-pilot/page-view.php') . $read('includes/creator-campaign-pilot/page-view-runs.php') . $read('includes/creator-campaign-pilot/page-view-monitoring.php');
$bootstrap = $read('includes/creator-campaign-pilot/bootstrap.php');
$repository = $read('includes/creator-campaign-pilot/repository.php');
$service = $read('includes/creator-campaign-pilot/service.php') . $read('includes/creator-campaign-pilot/service-readiness.php') . $read('includes/creator-campaign-pilot/service-health.php') . $read('includes/creator-campaign-pilot/service-control.php');
$runtime = $read('includes/creator-campaign-pilot/runtime.php');
$handoff = $read('includes/creator-campaign-pilot/action-handoff.php') . $read('includes/creator-campaign-pilot/action-handoff-seed.php') . $read('includes/creator-campaign-pilot/action-handoff-service.php');
$aggregate = $read('includes/creator-campaign-pilot.php');
$playbookBridge = $read('api/internal/_mcp_creator_campaign_playbook_bridge.php');
$docs = $read('docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE14_PRODUCTION_PILOT_OPERATOR.md');
$css = $read('assets/css/creator-campaign-pilot.css');

$add(
    'Three additive operator tables and idempotent migration ledger are present',
    substr_count($sql, 'CREATE TABLE IF NOT EXISTS creator_campaign_operator_') === 3
        && str_contains($sql, 'creator_campaign_operator_pilots')
        && str_contains($sql, 'creator_campaign_operator_events')
        && str_contains($sql, 'creator_campaign_operator_handoffs')
        && str_contains($sql, '20260722_creator_campaign_production_pilot_v14_single_install')
        && str_contains($sql, 'ON DUPLICATE KEY UPDATE')
        && !str_contains($sql, 'mcp_scope_catalog'),
    12
);
$add(
    'Operator cockpit consolidates readiness, playbooks, runs, artifacts, events, and security',
    str_contains($page, 'Production pilot cockpit')
        && str_contains($page, 'Pilot readiness')
        && str_contains($page, 'Bounded assistants')
        && str_contains($page, 'Playbook run history')
        && str_contains($page, 'Recent recommendations')
        && str_contains($page, 'Pilot activity')
        && str_contains($page, 'MCP security feed')
        && str_contains($css, '.mg-pilot-workspace'),
    10
);
$add(
    'Readiness is derived from canonical authority and owner attestations',
    str_contains($service, 'mg_creator_campaign_pilot_readiness')
        && str_contains($repository, 'mcp_connections')
        && str_contains($repository, 'mcp_automation_grants')
        && str_contains($repository, 'mcp_automations')
        && str_contains($repository, 'mcp_automation_runs')
        && str_contains($repository, 'mcp_agent_drafts')
        && str_contains($service, 'MG_CREATOR_CAMPAIGN_PILOT_MANUAL_CHECKS'),
    10
);
$add(
    'Emergency stop is fail closed and blocks new Phase 13D runs',
    str_contains($runtime, 'mg_creator_campaign_pilot_assert_playbook_enabled')
        && str_contains($runtime, 'MCP_CREATOR_CAMPAIGN_PILOT_EMERGENCY_DISABLED')
        && str_contains($playbookBridge, 'creator-campaign-pilot/runtime.php')
        && str_contains($playbookBridge, 'mg_creator_campaign_pilot_assert_playbook_enabled')
        && str_contains($service, "status='disabled',emergency_disabled=1"),
    14
);
$add(
    'Emergency shutdown pauses authority and clear never reactivates it automatically',
    str_contains($service, 'cancellation_requested_at=COALESCE')
        && str_contains($service, 'UPDATE mcp_automation_triggers')
        && str_contains($service, 'UPDATE mcp_automations')
        && str_contains($service, 'UPDATE mcp_automation_grants')
        && str_contains($service, 'g.revocation_version=g.revocation_version+1')
        && str_contains($service, "SET status='paused',emergency_disabled=0")
        && !str_contains($service, "SET status='active',emergency_disabled=0"),
    12
);
$add(
    'Recovery controls record evidence and never auto retry',
    str_contains($service, 'mg_creator_campaign_pilot_acknowledge_run')
        && str_contains($service, "'retry_external'")
        && str_contains($service, "'review_configuration'")
        && str_contains($service, "'pause_definition'")
        && str_contains($service, "'no_retry'")
        && str_contains($service, "'resolved'")
        && !str_contains($service, 'maximum_attempts=maximum_attempts+1'),
    8
);
$add(
    'Approved playbook artifacts can create request-only Phase 13C handoffs',
    str_contains($handoff, 'mg_creator_campaign_pilot_prepare_action_request')
        && str_contains($handoff, "status'] !== 'approved'")
        && str_contains($handoff, 'mg_mcp_creator_campaign_action_request')
        && str_contains($handoff, 'creator_campaign_operator_handoffs')
        && str_contains($handoff, "'owner_approval_required'=>true")
        && str_contains($handoff, "'execution_performed'=>false"),
    14
);
$add(
    'Handoff remains separately approval gated and cannot call native execution',
    str_contains($handoff, "maximum_operation_class'] !== 'approval_gated'")
        && str_contains($handoff, 'mg_mcp_creator_campaign_action_contract')
        && str_contains($handoff, 'mg_mcp_creator_campaign_action_request')
        && !str_contains($handoff, 'mg_mcp_creator_campaign_action_execute(')
        && !str_contains($handoff, 'mg_mcp_creator_campaign_action_native_execute('),
    10
);
$add(
    'Operator evidence is durable across pilot, audit, domain, and security ledgers',
    str_contains($service, 'INSERT INTO creator_campaign_operator_events')
        && str_contains($service, 'mg_audit')
        && str_contains($service, 'mg_event')
        && str_contains($service, 'mg_security_log')
        && str_contains($handoff, 'mg_mcp_draft_event'),
    6
);
$add(
    'Production guide and explicit safety boundary are documented',
    str_contains($docs, 'Production smoke test')
        && str_contains($docs, 'Emergency stop')
        && str_contains($docs, 'Recommendation handoff')
        && str_contains($docs, 'Rollback')
        && str_contains($docs, 'does not approve or execute')
        && str_contains($aggregate, "require_once __DIR__ . '/creator-campaign-pilot/action-handoff.php'"),
    4
);

$score = 0;
foreach ($checks as $check) {
    if ($check['passed']) $score += $check['points'];
    echo ($check['passed'] ? 'PASS' : 'FAIL') . ' [' . $check['points'] . '] ' . $check['label'] . PHP_EOL;
}
echo 'Creator Campaign Phase 14 score: ' . $score . '/100' . PHP_EOL;
exit($score === 100 ? 0 : 1);
