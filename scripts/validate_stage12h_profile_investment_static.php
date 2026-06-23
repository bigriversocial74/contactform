<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$profile = is_file($root . '/profile.php') ? (string) file_get_contents($root . '/profile.php') : '';
$js = is_file($root . '/assets/js/public-profile-investment.js') ? (string) file_get_contents($root . '/assets/js/public-profile-investment.js') : '';
$css = is_file($root . '/assets/css/public-profile-investment.css') ? (string) file_get_contents($root . '/assets/css/public-profile-investment.css') : '';
$api = is_file($root . '/api/public/profile-investment.php') ? (string) file_get_contents($root . '/api/public/profile-investment.php') : '';

$checks = [
    'profile_file_exists' => is_file($root . '/profile.php'),
    'investment_css_exists' => is_file($root . '/assets/css/public-profile-investment.css'),
    'investment_js_exists' => is_file($root . '/assets/js/public-profile-investment.js'),
    'investment_api_exists' => is_file($root . '/api/public/profile-investment.php'),
    'profile_loads_assets' => str_contains($profile, 'public-profile-investment.css') && str_contains($profile, 'public-profile-investment.js'),
    'profile_has_page_hook' => str_contains($profile, 'data-public-profile-page'),
    'js_uses_safe_href' => str_contains($js, 'function safeHref') && str_contains($js, 'safeHref(item.url,profileHref())'),
    'js_avoids_inner_html' => !str_contains($js, '.innerHTML'),
    'css_has_ticker_anchor' => str_contains($css, '.mg-invest-ticker-item'),
    'api_no_store' => str_contains($api, 'no-store'),
    'api_schema_cache' => str_contains($api, 'static $cache'),
];

$failed = array_keys(array_filter($checks, static fn($pass) => !$pass));
$result = ['ok' => count($failed) === 0, 'failed' => $failed, 'checks' => $checks];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
