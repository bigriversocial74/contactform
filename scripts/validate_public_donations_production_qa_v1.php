<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || trim($content) === '') throw new RuntimeException('Missing required file: ' . $path);
    return $content;
};
$must = static function (string $content, array $needles, string $label): void {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) throw new RuntimeException($label . ' missing contract: ' . $needle);
    }
};

$core = $read('includes/public-donations-reconciliation.php');
$cli = $read('scripts/reconcile_public_donations.php');
$fixture = $read('scripts/test_public_donations_production_qa_mysql.php');
$runbook = $read('docs/production/public-donations-production-runbook.md');
$installer = $read('database/20260724_public_donations_community_v1_single_install.sql');
$feature = $read('includes/public-donations-feature.php');
$governance = $read('includes/public-donations-governance.php');

$must($core, [
    'MG_PUBLIC_DONATIONS_REPAIR_MODES',
    "'counters'",
    "'batch_totals'",
    "'recalled_visibility'",
    "'assignments'",
    "'missing_attribution'",
    "'missing_links'",
    "'ownership_mismatches'",
    "'counter_drift'",
    "'batch_drift'",
    "'recalled_visible'",
    "'assignment_role_removed'",
    "'repairable' => false",
    'GET_LOCK(?,10)',
    'RELEASE_LOCK(?)',
    'beginTransaction()',
    'rollBack()',
    'public_donations.reconcile',
    "'checksum'",
], 'reconciliation core');
$must($cli, [
    '--merchant=ID',
    '--dry-run',
    '--repair=MODE[,MODE]',
    '--campaign=PUBLIC_ID|SLUG',
    '--operation=PUBLIC_ID',
    '--limit=1..1000',
    'Dry-run is the default',
    'Missing attribution, missing canonical links, and',
    'never creates or',
], 'reconciliation CLI');
$must($fixture, [
    "'initial_inventory' => 100",
    "'allocations' => [10,20,25]",
    "'gross_allocated' => 55",
    "'regifted' => 4",
    "'claimed' => 2",
    "'redeemed' => 1",
    "'recalled' => 6",
    "'net_allocated' => 49",
    "'remaining_inventory' => 51",
    "'dashboard'",
    "'public_campaign'",
    "'profile_community_tab'",
    "'wallet'",
    "'pppm'",
    "'claim'",
    "'redemption'",
    "'repair' => 'safe'",
    "'unexplained_drift_after'",
], 'acceptance fixture');
$must($runbook, [
    'Code upload status',
    'SQL import status',
    'MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled',
    'admin_only',
    'selected_merchants',
    'enabled',
    'Smoke test',
    'Rollback',
    'reconcile_public_donations.php',
    '20260724_public_donations_community_v1_single_install.sql',
    'Do not mark deployment complete',
], 'production runbook');
$must($installer, [
    "'merchant.public_donations.view'",
    "'merchant.public_donations.manage'",
    "'merchant.public_donations.assign'",
    "'merchant.public_donations.allocate'",
    "'merchant.public_donations.recall'",
    "'merchant.public_donations.report'",
], 'single installer');
$must($feature, [
    "['disabled', 'admin_only', 'selected_merchants', 'enabled']",
    'MG_PUBLIC_DONATIONS_FEATURE_STATE',
    'MG_PUBLIC_DONATIONS_MERCHANT_IDS',
], 'feature rollout contract');
$must($governance, [
    'merchant-funded promotional rewards',
    'not cash donations or tax-deductible charitable contributions',
], 'governance release contract');

if (preg_match('/INSERT\s+INTO\s+campaign_donation_rewards/i', $core) === 1) {
    throw new RuntimeException('Reconciliation must never invent missing attribution.');
}
if (preg_match('/UPDATE\s+(?:wallet_items|pppm_items|microgift_instances)\s+SET\s+(?:user_id|owner_user_id|recipient_user_id)/i', $core) === 1) {
    throw new RuntimeException('Reconciliation must never repair ownership by assignment.');
}
if (!str_contains($core, "if (empty(\$issue['repairable'])) continue;")) {
    throw new RuntimeException('Every repair loop must enforce the repairable boundary.');
}
if (str_contains($runbook, 'Deployment confirmed') || str_contains($runbook, 'deployed successfully')) {
    throw new RuntimeException('Runbook must not claim deployment before separate confirmation.');
}

$checks = 10;
echo "Public Donations production QA contracts valid: {$checks}/{$checks}.\n";
