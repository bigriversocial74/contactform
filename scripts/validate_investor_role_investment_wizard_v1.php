<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failures = [];
$pass = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo "[PASS] {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "[FAIL] {$message}\n";
};
$read = static function (string $path) use ($root, $pass): string {
    $full = $root . '/' . $path;
    $pass(is_file($full), 'Required file exists: ' . $path);
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$required = [
    'database/20260723_investor_role_investment_wizard_v1_single_install.sql',
    'includes/investment/investment-service.php',
    'includes/investment/investment-access.php',
    'includes/investment/investment-planning.php',
    'includes/investment/investment-scenarios.php',
    'includes/investment/investment-content.php',
    'includes/investment/investment-rounds.php',
    'includes/investment/investment-reporting.php',
    'api/investment/access-request.php',
    'api/investment/portal.php',
    'api/admin/investor-access.php',
    'api/admin/investment-wizard.php',
    'investor-access.php',
    'investor-portal.php',
    'admin/investor-access-requests.php',
    'admin/investment-wizard.php',
    'assets/css/investment-system-v1.css',
    'assets/js/investor-access-v1.js',
    'assets/js/admin-investor-access-v1.js',
    'assets/js/investor-portal-v1.js',
    'investment-wizard-runtime.php',
    'includes/account-sidebar.php',
];
foreach (range(0, 8) as $index) $required[] = sprintf('assets/js/investment-wizard-v1-parts/part-%02d', $index);
foreach ($required as $file) $read($file);

$sql = $read('database/20260723_investor_role_investment_wizard_v1_single_install.sql');
foreach ([
    "('investor','Investor'", 'investment.portal.view', 'admin.investment.manage',
    'CREATE TABLE IF NOT EXISTS investor_access_requests',
    'CREATE TABLE IF NOT EXISTS investor_profiles',
    'CREATE TABLE IF NOT EXISTS investment_workspaces',
    'CREATE TABLE IF NOT EXISTS investment_scenarios',
    'CREATE TABLE IF NOT EXISTS investment_scenario_budgets',
    'CREATE TABLE IF NOT EXISTS investment_scenario_goals',
    'CREATE TABLE IF NOT EXISTS investment_rounds',
    'CREATE TABLE IF NOT EXISTS investment_round_versions',
    'CREATE TABLE IF NOT EXISTS investment_metrics',
    'CREATE TABLE IF NOT EXISTS investment_metric_snapshots',
    'CREATE TABLE IF NOT EXISTS investment_documents',
    'CREATE TABLE IF NOT EXISTS investment_ai_analyses',
    'CREATE TABLE IF NOT EXISTS investment_round_access',
    'CREATE TRIGGER mg_investor_privacy_after_user_update',
] as $needle) $pass(str_contains($sql, $needle), 'SQL contract contains ' . $needle);

$service = '';
foreach (['investment-access.php','investment-planning.php','investment-scenarios.php','investment-content.php','investment-rounds.php','investment-reporting.php'] as $module) {
    $service .= $read('includes/investment/' . $module);
}
foreach ([
    'function mg_investment_submit_access_request',
    'function mg_investment_admin_decide_access',
    'function mg_investment_scenario_calculate',
    'function mg_investment_scenario_projection',
    'Minimum Launch — $250K', 'Balanced Growth — $500K', 'Full Market Launch — $750K',
    'function mg_investment_clone_scenario',
    'function mg_investment_replace_budget',
    'function mg_investment_replace_goals',
    'function mg_investment_save_metrics',
    'function mg_investment_save_documents',
    'function mg_investment_adopt_round',
    'function mg_investment_update_round',
    'function mg_investment_run_claude_analysis',
    "require_once dirname(__DIR__).'/ai/anthropic-client.php'",
    'function mg_investment_portal_data',
] as $needle) $pass(str_contains($service, $needle), 'Service contract contains ' . $needle);
$pass(str_contains($service, "DELETE FROM user_roles WHERE user_id=? AND role_id=?"), 'Revocation removes only the Investor role.');
$pass(str_contains($service, 'UPDATE user_sessions SET revoked_at=NOW()'), 'Revocation ends active sessions.');
$pass(str_contains($service, "INSERT INTO investment_round_versions"), 'Official round changes are versioned.');
$pass(!str_contains($service, 'publish_round'), 'Claude cannot directly publish a round.');

$wizard = '';
foreach (range(0, 8) as $index) $wizard .= $read(sprintf('assets/js/investment-wizard-v1-parts/part-%02d', $index));
foreach (['clone_scenario','save_budget','save_goals','save_metrics','save_documents','adopt_round','update_round','run_ai_analysis','forecast_case','stress_tests'] as $needle) {
    $pass(str_contains($wizard, $needle), 'Wizard runtime contains ' . $needle);
}
$pass(!str_contains($wizard, "querySelectorAll('input,select,textarea,button').forEach"), 'Busy state does not disable saved form fields.');

foreach (['api/investment/access-request.php','api/admin/investor-access.php','api/admin/investment-wizard.php'] as $apiPath) {
    $api = $read($apiPath);
    $pass(str_contains($api, 'mg_require_api_user()'), $apiPath . ' requires authenticated identity.');
    $pass(str_contains($api, 'mg_require_csrf_for_write'), $apiPath . ' enforces CSRF for writes.');
    $pass(str_contains($api, 'mg_rate_limit'), $apiPath . ' applies rate limiting.');
}

require_once $root . '/includes/investment/investment-service.php';
$workspace = [
    'operating_plan_json' => json_encode(['founder_compensation'=>5000,'hosting'=>1000,'marketing'=>4000,'one_time_launch_expenses'=>10000]),
    'assumptions_json' => json_encode(mg_investment_default_assumptions()),
];
$scenario = [
    'target_raise_cents'=>50000000,'minimum_raise_cents'=>25000000,'maximum_raise_cents'=>75000000,
    'valuation_cap_cents'=>666666667,'maximum_dilution_bps'=>1000,'desired_runway_months'=>14,
    'forecast_months'=>24,'forecast_case'=>'expected','assumptions_json'=>'{}','stress_tests_json'=>'{}',
];
$calculation = mg_investment_scenario_calculate($workspace, $scenario);
$projection = mg_investment_scenario_projection($workspace, $scenario, $calculation);
$pass($calculation['monthly_burn_cents'] === 1000000, 'Deterministic monthly burn calculation is correct.');
$pass(count($projection['rows']) === 24, 'Deterministic projection creates 24 monthly rows.');
$pass($calculation['approximate_investor_ownership_percent'] !== null, 'Deterministic ownership estimate is produced.');

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " validation failure(s).\n");
    exit(1);
}
echo "\nInvestor Role & Investment Wizard v1: {$checks}/{$checks} checks passed.\n";
