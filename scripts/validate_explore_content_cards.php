<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $source = file_get_contents($full);
    if (!is_string($source)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $source;
};

$page = $read('discover.php');
$css = $read('assets/css/profile-discovery-content-cards.css');
$client = $read('assets/js/profile-discovery.js');
$api = $read('api/profiles/_discovery.php');

$checks = [
    'Explore loads dedicated content-first CSS and versioned runtime' =>
        str_contains($page, '/assets/css/profile-discovery-content-cards.css?v=1.0.0')
        && str_contains($page, '/assets/js/profile-discovery.js?v=2.0.0'),
    'Explore keeps search category state and loading foundations' =>
        str_contains($page, 'data-discovery-form')
        && str_contains($page, 'data-discover-category-list')
        && str_contains($page, 'data-discover-state-list')
        && str_contains($page, 'data-discovery-loading')
        && str_contains($page, 'data-discovery-error')
        && str_contains($page, 'data-discovery-no-results'),
    'Explore headline describes content rather than market scores' =>
        str_contains($page, 'Discover local businesses worth following.')
        && str_contains($page, 'products, active campaigns, and customer reviews')
        && !str_contains($page, 'Sort market'),
    'merchant cards render cover profile business and display name' =>
        str_contains($client, 'mg-discovery-cover')
        && str_contains($client, 'mg-discovery-avatar')
        && str_contains($client, 'mg-discovery-business-name')
        && str_contains($client, 'profile.business_name')
        && str_contains($client, 'profile.display_name'),
    'merchant cards render product campaign and review summaries' =>
        str_contains($client, "metric('Products'")
        && str_contains($client, "metric('Campaigns'")
        && str_contains($client, 'mg-discovery-review-metric')
        && str_contains($client, 'review.average')
        && str_contains($client, 'review.total'),
    'market score ticker and chart renderers are removed' =>
        !str_contains($client, 'Ticker Value')
        && !str_contains($client, 'Merchant Score')
        && !str_contains($client, 'marketPanel(')
        && !str_contains($client, 'statGrid(')
        && !str_contains($client, 'sparkline(')
        && !str_contains($client, 'rankBadge('),
    'API projects cover and business identity safely' =>
        str_contains($api, 'pp.cover_url')
        && str_contains($api, 'AS business_name')
        && str_contains($api, 'AS storefront_cover_url')
        && str_contains($api, "'business_name' =>")
        && str_contains($api, "'cover_url' =>")
        && str_contains($api, 'mg_public_profile_safe_url'),
    'API projects active campaign totals and review-ready shape' =>
        str_contains($api, 'AS published_campaign_count')
        && str_contains($api, "c.status='active'")
        && str_contains($api, "'published_campaigns' =>")
        && str_contains($api, "'reviews' =>")
        && str_contains($api, "'status' => 'module_pending'"),
    'card CSS provides two-column desktop and one-column responsive layout' =>
        str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))!important')
        && str_contains($css, '@media(max-width:1180px)')
        && str_contains($css, 'grid-template-columns:1fr!important')
        && str_contains($css, '@media(max-width:620px)'),
    'legacy data UI is explicitly suppressed by final CSS authority' =>
        str_contains($css, '.mg-discovery-market-panel')
        && str_contains($css, '.mg-discovery-stat-grid')
        && str_contains($css, '.mg-discovery-business-row')
        && str_contains($css, '.mg-market-rank-badge')
        && str_contains($css, 'display:none!important'),
    'client uses safe DOM construction' =>
        str_contains($client, 'document.createElement(')
        && str_contains($client, 'textContent')
        && !str_contains($client, '.innerHTML =')
        && !str_contains($client, 'insertAdjacentHTML(')
        && !str_contains($client, 'eval('),
    'review UI clearly handles the pre-module empty state' =>
        str_contains($client, "averageNode.textContent = total > 0 ? average.toFixed(1) : 'New'")
        && str_contains($client, "countNode.textContent = total > 0")
        && str_contains($client, "'0 reviews'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

$score = round((count($checks) - count($failed)) / max(1, count($checks)) * 10, 1);
echo 'Explore content-card score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Explore content-card validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Explore content-card validation passed at 10.0/10.\n";