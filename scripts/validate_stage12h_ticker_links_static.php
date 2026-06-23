<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$js = is_file($root . '/assets/js/public-profile-investment.js')
    ? (string) file_get_contents($root . '/assets/js/public-profile-investment.js')
    : '';
$api = is_file($root . '/api/public/profile-investment.php')
    ? (string) file_get_contents($root . '/api/public/profile-investment.php')
    : '';
$css = is_file($root . '/assets/css/public-profile-investment.css')
    ? (string) file_get_contents($root . '/assets/css/public-profile-investment.css')
    : '';

$checks = [
    'ticker_uses_anchor' => str_contains($js, "var wrapper = el('a', 'mg-invest-ticker-item')"),
    'ticker_uses_safe_href' => str_contains($js, 'wrapper.href = safeHref(item.url, profileHref())'),
    'ticker_has_profile_fallback' => str_contains($js, "function profileHref()") && str_contains($js, "/profile.php?slug="),
    'ticker_has_accessible_label' => str_contains($js, "wrapper.setAttribute('aria-label'"),
    'ticker_css_preserves_link_style' => str_contains($css, 'a.mg-invest-ticker-item') && str_contains($css, 'text-decoration:none'),
    'api_ticker_can_emit_url' => str_contains($api, "'url' => '/profile.php?slug=' . rawurlencode(") || str_contains($api, '"url" =>'),
];

$failed = array_keys(array_filter($checks, static fn($pass) => !$pass));
$result = [
    'ok' => count($failed) === 0,
    'failed' => $failed,
    'checks' => $checks,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
