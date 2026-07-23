<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$read = static function(string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) throw new RuntimeException('Unable to read ' . $path);
    return $content;
};
$checks = [];
$add = static function(string $label, bool $passed, int $points) use (&$checks): void {
    $checks[] = compact('label','passed','points');
};

$validatorPaths = [
    'scripts/validate_creator_campaign_native_foundation_v1.php',
    'scripts/validate_creator_campaign_merchant_builder_v2.php',
    'scripts/validate_creator_campaign_participation_v3.php',
    'scripts/validate_creator_campaign_deliverables_v4.php',
    'scripts/validate_creator_campaign_tracking_v5.php',
    'scripts/validate_creator_campaign_compensation_v6.php',
    'scripts/validate_creator_campaign_budgets_v7.php',
    'scripts/validate_creator_campaign_payouts_v8.php',
    'scripts/validate_creator_campaign_messaging_v9.php',
    'scripts/validate_creator_campaign_analytics_v10.php',
    'scripts/validate_creator_campaign_ui_v11.php',
    'scripts/validate_creator_campaign_crm_v12.php',
    'scripts/validate_creator_campaign_mcp_v13a.php',
    'scripts/validate_creator_campaign_mcp_v13b.php',
    'scripts/validate_creator_campaign_mcp_v13c.php',
    'scripts/validate_creator_campaign_mcp_v13d.php',
    'scripts/validate_creator_campaign_pilot_v14.php',
    'scripts/validate_creator_campaign_onboarding_v15.php',
];
$repairSql = $read('database/20260723_creator_campaign_phases_1_15_production_audit_repair_v1.sql');
$phase15Sql = $read('database/20260723_creator_campaign_pilot_launch_onboarding_v15_single_install.sql');
$definitions = $read('includes/creator-campaigns/compensation-definitions.php');
$compensation = $read('includes/creator-campaigns/compensation-service.php');
$onboardingRepository = $read('includes/creator-campaign-onboarding/repository.php');
$readiness = $read('includes/creator-campaign-onboarding/readiness.php');
$smoke = $read('includes/creator-campaign-onboarding/smoke-test.php');
$launchView = $read('includes/creator-campaign-onboarding/page-view-launch.php');
$contextJs = $read('assets/js/creator-campaign-context-filter.js');
$docs = $read('docs/creator-campaigns/CREATOR_CAMPAIGN_PHASES_1_15_PRODUCTION_AUDIT_REPAIR.md');
$pageLoaders = implode("\n", array_map($read, [
    'merchant-creator-campaign-builder.php',
    'merchant-creator-campaign-detail.php',
    'merchant-creator-participation.php',
    'merchant-creator-deliverables.php',
    'merchant-creator-tracking.php',
    'merchant-creator-compensation.php',
    'merchant-creator-budgets.php',
]));

$add('Every Creator Campaign Phase 1–15 validator remains present',
    count(array_filter($validatorPaths, static fn(string $path): bool => is_file($root . '/' . $path))) === count($validatorPaths), 8);

$requiredCreatorPermissions = [
    'creator.campaigns.discover',
    'creator.campaign_applications.manage_own',
    'creator.campaign_invitations.respond_own',
    'creator.campaign_participants.view_own',
    'creator.campaign_agreements.view_own',
    'creator.campaign_agreements.respond_own',
    'creator.campaign_deliverables.view_own',
    'creator.campaign_submissions.manage_own',
    'creator.campaign_tracking.view_own',
    'creator.campaign_tracking.manage_own',
    'creator.campaign_earnings.view_own',
    'creator.campaign_payouts.view_own',
    'creator.campaign_disputes.manage_own',
];
$permissionCoverage = str_contains($repairSql, "WHERE r.slug='customer'");
foreach ($requiredCreatorPermissions as $permission) $permissionCoverage = $permissionCoverage && str_contains($repairSql, "'{$permission}'");
$add('Canonical customer-role Creator permission backfill covers Phases 3–8', $permissionCoverage, 12);

$add('Percentage earnings use exact integer basis-point arithmetic',
    str_contains($definitions, 'mg_creator_campaign_compensation_percent_minor')
    && str_contains($definitions, 'intdiv($sourceAmountMinor,10000)')
    && str_contains($definitions, 'intdiv($remainder*$rateBps,10000)')
    && str_contains($definitions, 'OverflowException')
    && str_contains($compensation, 'mg_creator_campaign_compensation_percent_minor')
    && !str_contains($compensation, 'floor($sourceAmountMinor *'), 12);

$add('Manual adjustments and reversals return canonical events on retries',
    substr_count($compensation, "idempotent'=>true") >= 4
    && str_contains($compensation, 'WHERE campaign_id=? AND idempotency_hash=? LIMIT 1 FOR UPDATE')
    && str_contains($compensation, 'WHERE reversal_of_event_id=? OR (campaign_id=? AND idempotency_hash=?)')
    && str_contains($compensation, "(string)$e->getCode()==='23000'"), 10);

$add('Phase 15 receipt identity is status-aware for clean and deployed databases',
    str_contains($phase15Sql, 'uq_creator_campaign_onboarding_receipt_snapshot (onboarding_id,receipt_type,snapshot_hash,status)')
    && str_contains($repairSql, "'onboarding_id,receipt_type,snapshot_hash,status'")
    && str_contains($repairSql, 'DROP INDEX uq_creator_campaign_onboarding_receipt_snapshot')
    && str_contains($smoke, 'snapshot_hash=? AND status=?')
    && str_contains($smoke, '$status = $passed ?'), 12);

$add('Smoke-test freshness includes product, campaign, operator, and emergency evidence',
    str_contains($readiness, "'version'=>'creator_campaign_onboarding_smoke_v15_1'")
    && str_contains($readiness, "'unit_value_cents'")
    && str_contains($readiness, "'ready_image_count'")
    && str_contains($readiness, "'active_pppm_count'")
    && str_contains($readiness, "'operator_evidence'")
    && str_contains($readiness, "'automatic_acceptance_disabled'")
    && str_contains($readiness, "'emergency_disabled'")
    && str_contains($readiness, "'current_passing_smoke_test'"), 12);

$add('Onboarding creation and operator assignments revalidate current workspace authority',
    str_contains($onboardingRepository, 'INSERT IGNORE INTO creator_campaign_merchant_onboarding')
    && str_contains($onboardingRepository, 'mg_creator_campaign_onboarding_operator_evidence')
    && str_contains($onboardingRepository, "mtm.status='active'")
    && str_contains($onboardingRepository, "u.status='active'")
    && str_contains($readiness, '$primaryOperatorCurrent')
    && str_contains($readiness, '$roleComplete'), 10);

$add('Campaign context survives Phase 11 and Phase 15 workspace navigation',
    str_contains($launchView, '/merchant-creator-deliverables.php?campaign=')
    && str_contains($launchView, '/merchant-creator-compensation.php?campaign=')
    && str_contains($launchView, '/merchant-creator-budgets.php?campaign=')
    && str_contains($launchView, '/merchant-creator-tracking.php?campaign=')
    && str_contains($launchView, '/merchant-creator-participation.php?campaign=')
    && str_contains($contextJs, 'campaignWorkspacePaths')
    && str_contains($contextJs, '[data-ccp-campaign-filter]')
    && str_contains($contextJs, '[data-ccdv-campaign]')
    && str_contains($contextJs, '[data-cct-campaign]')
    && substr_count($pageLoaders, 'creator-campaign-context-filter.js') >= 7, 10);

$add('Audit repairs add no MCP authority, campaign publication, or payment execution',
    !str_contains($repairSql, 'INSERT INTO mcp_scope_catalog')
    && !str_contains($repairSql, 'mcp_automation_grants')
    && !str_contains($smoke, 'mg_mcp_')
    && !str_contains($smoke, 'mg_creator_campaign_transition_status(')
    && str_contains($smoke, "'automatic_execution'=>false")
    && str_contains($smoke, "'campaign_published'=>false")
    && str_contains($smoke, "'payment_provider_called'=>false"), 8);

$add('Audit report contains workflow, permission, financial, database, and deployment matrices',
    str_contains($docs, 'Production workflow test matrix')
    && str_contains($docs, 'Permission matrix')
    && str_contains($docs, 'Financial calculation verification')
    && str_contains($docs, 'Database audit')
    && str_contains($docs, 'Deployment order')
    && str_contains($docs, 'Required production checks after deployment'), 6);

$score = 0;
foreach ($checks as $check) {
    if ($check['passed']) $score += $check['points'];
    echo ($check['passed'] ? 'PASS' : 'FAIL') . ' [' . $check['points'] . '] ' . $check['label'] . PHP_EOL;
}
echo 'Creator Campaign Phases 1–15 production audit score: ' . $score . '/100' . PHP_EOL;
exit($score === 100 ? 0 : 1);
