<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'merchant-community-support.php',
    'includes/merchant-community-support.php',
    'includes/merchant-community-support-view.php',
    'api/merchant/community-support.php',
    'assets/js/merchant-community-support.js',
    'assets/css/merchant-community-support.css',
    'tests/phpunit/MerchantCommunitySupportContractTest.php',
    'scripts/test_merchant_community_support_mysql.php',
    '.github/workflows/merchant-community-support-v1.yml',
];

$ok = true;
$checks = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $checks['file:' . $path] = $exists;
    $ok = $ok && $exists;
}

$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';
$core = $read('includes/merchant-community-support.php');
$api = $read('api/merchant/community-support.php');
$page = $read('merchant-community-support.php');
$view = $read('includes/merchant-community-support-view.php');
$js = $read('assets/js/merchant-community-support.js');
$nav = $read('includes/merchant-navigation.php');

$contracts = [
    'merchant_scoped_core' => substr_count($core, 'merchant_user_id=?') >= 5,
    'canonical_reward_joins' => str_contains($core, 'INNER JOIN wallet_items wallet')
        && str_contains($core, 'INNER JOIN pppm_items pppm')
        && str_contains($core, 'INNER JOIN microgift_instances microgift'),
    'cumulative_metrics' => str_contains($core, "'gross_allocated'")
        && str_contains($core, "'regifted'")
        && str_contains($core, "'claimed'")
        && str_contains($core, "'redeemed'"),
    'distinct_accounts' => str_contains($core, 'GROUP BY assignment.community_user_id')
        && str_contains($core, 'COUNT(DISTINCT assignment.campaign_id)'),
    'privacy_boundary' => str_contains($core, "'downstream_recipient_identity_exposed' => false")
        && !str_contains($core, 'recipient.display_name')
        && !str_contains($core, 'downstream_user'),
    'attention_rules' => str_contains($core, "'low_inventory'")
        && str_contains($core, "'ending_soon'")
        && str_contains($core, "'untouched_balance'")
        && str_contains($core, "'role_removed'")
        && str_contains($core, "'failed_operation'"),
    'read_only_api' => str_contains($api, "if (\$method !== 'GET')")
        && !str_contains($api, 'mg_require_csrf_for_write')
        && !preg_match('/\b(INSERT|UPDATE|DELETE)\b/i', $api),
    'api_permission' => str_contains($api, "mg_merchant_require_permission('merchant.campaigns.view')"),
    'safe_unexpected_errors' => str_contains($api, 'mg_fail_unexpected(')
        && !str_contains($api, '$error->getMessage()'),
    'workspace_page' => str_contains($page, "\$merchantView = 'community_support'")
        && str_contains($page, 'merchant-community-support-view.php'),
    'navigation' => str_contains($nav, "'community_support'")
        && str_contains($nav, '/merchant-community-support.php')
        && str_contains($nav, "'merchant-community-support' => 'community_support'"),
    'four_tabs' => str_contains($view, 'data-tab="campaigns"')
        && str_contains($view, 'data-tab="accounts"')
        && str_contains($view, 'data-tab="batches"')
        && str_contains($view, 'data-tab="activity"'),
    'safe_dom' => str_contains($js, 'document.createElement')
        && !str_contains($js, '.innerHTML')
        && !str_contains($js, 'document.write')
        && !str_contains($js, 'eval('),
];

foreach ($contracts as $name => $passed) {
    $checks[$name] = (bool)$passed;
    $ok = $ok && (bool)$passed;
}

echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
