<?php
declare(strict_types=1);

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

$sql = $read('database/20260723_creator_campaign_pilot_launch_onboarding_v15_single_install.sql');
$page = $read('account-creator-campaign-onboarding.php')
    . $read('includes/creator-campaign-onboarding/page-view.php')
    . $read('includes/creator-campaign-onboarding/page-view-foundation.php')
    . $read('includes/creator-campaign-onboarding/page-view-guardrails.php')
    . $read('includes/creator-campaign-onboarding/page-view-launch.php');
$bootstrap = $read('includes/creator-campaign-onboarding/bootstrap.php');
$repository = $read('includes/creator-campaign-onboarding/repository.php');
$service = $read('includes/creator-campaign-onboarding/service.php');
$readiness = $read('includes/creator-campaign-onboarding/readiness.php');
$smoke = $read('includes/creator-campaign-onboarding/smoke-test.php');
$pilotView = $read('includes/creator-campaign-pilot/page-view.php') . $read('includes/creator-campaign-pilot/page-view-onboarding.php');
$css = $read('assets/css/creator-campaign-onboarding.css');
$js = $read('assets/js/creator-campaign-onboarding.js');
$docs = $read('docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE15_PILOT_LAUNCH_ONBOARDING.md');

$add('Three additive native onboarding tables and idempotent migration ledger are present',
    substr_count($sql, 'CREATE TABLE IF NOT EXISTS creator_campaign_') === 3
    && str_contains($sql, 'creator_campaign_merchant_onboarding')
    && str_contains($sql, 'creator_campaign_onboarding_events')
    && str_contains($sql, 'creator_campaign_onboarding_receipts')
    && str_contains($sql, '20260723_creator_campaign_pilot_launch_onboarding_v15_single_install')
    && str_contains($sql, 'ON DUPLICATE KEY UPDATE')
    && !str_contains($sql, 'mcp_scope_catalog')
    && !str_contains($sql, 'mcp_connections'), 12);

$add('Nine-step responsive merchant onboarding exists without MCP setup',
    substr_count($bootstrap, "'label'=>") >= 9
    && str_contains($page, 'Pilot launch and merchant onboarding')
    && str_contains($page, 'Pilot enrollment')
    && str_contains($page, 'Production smoke test')
    && str_contains($page, 'Merchant launch dashboard')
    && str_contains($css, '.mg-onboarding-step-nav')
    && str_contains($js, 'data-onboarding-financial-form')
    && !str_contains($page, 'MCP connection setup')
    && !str_contains($page, 'Create grant'), 10);

$add('Enrollment and reusable merchant defaults are validated and durable',
    str_contains($service, 'mg_creator_campaign_onboarding_save_enrollment')
    && str_contains($service, 'pilot_boundaries_accepted')
    && str_contains($service, 'mg_creator_campaign_onboarding_save_business')
    && str_contains($service, 'business_defaults_json')
    && str_contains($service, 'expected_campaign_volume')
    && str_contains($service, 'intended_launch_date'), 10);

$add('Product readiness uses canonical catalog, assets, price, and PPPM evidence',
    str_contains($repository, 'catalog_products')
    && str_contains($repository, 'catalog_product_versions')
    && str_contains($repository, 'catalog_product_version_assets')
    && str_contains($repository, 'catalog_assets')
    && str_contains($repository, 'catalog_pppm_templates')
    && str_contains($repository, "'claim_rules'")
    && str_contains($readiness, 'selected_products'), 10);

$add('Compensation planning calculates bounded exposure without moving money',
    str_contains($service, 'mg_creator_campaign_onboarding_financial_exposure')
    && str_contains($service, 'campaign_budget_minor')
    && str_contains($service, 'per_creator_limit_minor')
    && str_contains($service, 'merchant_approval_required')
    && str_contains($readiness, 'financial_within_ceiling')
    && !str_contains($service, 'payment_intent')
    && !str_contains($service, 'stripe'), 10);

$add('Approved-Creator preferences and named human roles remain non-automatic',
    str_contains($service, "'approved_creators_only'=>true")
    && str_contains($service, 'mg_creator_campaign_onboarding_save_roles')
    && str_contains($bootstrap, "'application_reviewer'")
    && str_contains($bootstrap, "'payout_operator'")
    && str_contains($page, 'No automatic outreach')
    && str_contains($page, 'Native Microgifter permissions still determine'), 10);

$add('First campaign uses canonical draft and builder services and never publishes',
    str_contains($service, 'mg_creator_campaign_onboarding_create_first_campaign')
    && str_contains($service, 'mg_creator_campaign_create_draft')
    && substr_count($service, 'mg_creator_campaign_builder_save_step') >= 3
    && str_contains($service, "'automatic_acceptance'=>false")
    && !str_contains($service, 'mg_creator_campaign_transition_status(')
    && !str_contains($service, "toStatus='active'"), 14);

$add('Read-only smoke test creates durable receipts and proves non-execution',
    str_contains($smoke, 'mg_creator_campaign_onboarding_run_smoke_test')
    && str_contains($smoke, 'creator_campaign_onboarding_receipts')
    && str_contains($smoke, 'snapshot_hash')
    && str_contains($smoke, "'automatic_execution'=>false")
    && str_contains($smoke, "'campaign_published'=>false")
    && str_contains($smoke, "'payment_provider_called'=>false")
    && !str_contains($smoke, 'mg_creator_campaign_transition_status(')
    && !str_contains($smoke, 'mg_mcp_'), 14);

$add('Phase 14 cockpit surfaces native onboarding progress without changing safety controls',
    str_contains($pilotView, 'page-view-onboarding.php')
    && str_contains($pilotView, 'Phase 15 · Native merchant launch')
    && str_contains($pilotView, 'MCP authority remains separate')
    && str_contains($pilotView, '/account-creator-campaign-onboarding.php')
    && str_contains($css, '.mg-pilot-onboarding-card'), 6);

$add('Operating guide documents activation, smoke test, rollback, and no-authority boundary',
    str_contains($docs, 'Production smoke test')
    && str_contains($docs, 'Activation boundary')
    && str_contains($docs, 'Rollback')
    && str_contains($docs, 'configure MCP')
    && str_contains($docs, 'does not publish the campaign'), 4);

$score = 0;
foreach ($checks as $check) {
    if ($check['passed']) $score += $check['points'];
    echo ($check['passed'] ? 'PASS' : 'FAIL') . ' [' . $check['points'] . '] ' . $check['label'] . PHP_EOL;
}
echo 'Creator Campaign Phase 15 score: ' . $score . '/100' . PHP_EOL;
exit($score === 100 ? 0 : 1);
