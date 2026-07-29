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
$containsAll = static function (string $source, array $needles): bool {
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) return false;
    }
    return true;
};
$containsNone = static function (string $source, array $needles): bool {
    foreach ($needles as $needle) {
        if (str_contains($source, $needle)) return false;
    }
    return true;
};

$page = $read('profile.php');
$css = $read('assets/css/public-profile-content-first.css');
$runtime = $read('assets/js/public-profile-investment.js');
$profileApi = $read('api/public/profile.php');

$checks = [
    'content-first stylesheet remains loaded after the legacy profile stack' =>
        str_contains($page, '/assets/css/public-profile-content-first.css?v=1.0.0')
        && strpos($page, 'public-profile-content-first.css?v=1.0.0') > strpos($page, 'public-profile-realtime.css'),
    'page declares the content-first body authority' => str_contains($page, 'mg-profile-content-first'),
    'cover image remains full-width and visible above content' =>
        $containsAll($page, ['class="mg-invest-cover-card"', 'data-profile-cover'])
        && $containsAll($css, ['height:560px!important', 'margin-top:-168px!important']),
    'profile identity and actions share one unified hero card' =>
        $containsAll($page, ['class="mg-profile-hero-card"', 'class="mg-profile-hero-identity"', 'class="mg-profile-hero-actions"'])
        && str_contains($css, 'grid-template-columns:minmax(0,1fr) 320px'),
    'profile keeps avatar name merchant badge biography and meta' =>
        $containsAll($page, ['data-profile-avatar', 'data-profile-name', 'mg-profile-merchant-badge', 'data-profile-biography', 'data-profile-meta']),
    'profile keeps follow message share and owner edit actions without Save' =>
        $containsAll($page, ['data-profile-follow', 'data-profile-message', 'data-profile-share', 'data-profile-edit'])
        && !str_contains($page, 'data-profile-save'),
    'public data dashboards charts and analytics panel are removed from markup' =>
        $containsNone($page, ['mg-invest-stat-board', 'mg-invest-chart-row', 'data-invest-market-chart', 'data-invest-demand-meter', 'mg-invest-sidebar', 'Portfolio Snapshot', 'Ticker Value', 'Merchant Score', 'Public analytics are not displayed.', 'data-invest-panel="analytics"']),
    'six content tabs remain without Analytics' =>
        substr_count($page, 'data-invest-tab=') === 6
        && $containsAll($page, ['data-invest-tab="overview"', 'data-invest-tab="products"', 'data-invest-tab="stories"', 'data-invest-tab="posts"', 'data-invest-tab="campaigns"', 'data-invest-tab="community"'])
        && !str_contains($page, 'data-invest-tab="analytics"'),
    'overview focuses on products and campaigns' =>
        $containsAll($page, ['Featured Experiences', 'data-profile-products-grid', 'Active Campaigns', 'data-invest-campaigns-list']),
    'Community remains a content surface rather than an analytics dashboard' =>
        $containsAll($page, ['data-profile-community-summary', 'data-profile-community-campaigns', 'data-profile-community-accounts'])
        && !str_contains($page, 'data-invest-analytics-grid'),
    'legacy required profile data hooks remain safely hidden' =>
        $containsAll($page, ['class="mg-profile-data-bridge"', 'data-profile-followers', 'data-profile-supporters', 'data-profile-products'])
        && $containsAll($css, ['.mg-profile-data-bridge', 'display:none!important']),
    'campaign runtime renders content cards without progress UI' =>
        $containsAll($runtime, ['mg-profile-campaign-card', 'mg-profile-campaign-icon', 'mg-profile-campaign-chevron'])
        && $containsNone($runtime, ["document.createElement('progress')", 'mg-profile-campaign-progress', 'data-campaign-progress']),
    'runtime no longer requests public market-series chart data' =>
        !str_contains($runtime, 'profile-market-series.php')
        && str_contains($runtime, '/api/public/profile-investment.php?slug='),
    'post API resolves linked product cover assets' =>
        $containsAll($profileApi, ['mg_public_profile_attach_post_product_images', 'catalog_product_version_assets', "pva.role='cover'", "cover.status='ready'"]),
    'post API exposes product metadata and image media fallback' =>
        $containsAll($profileApi, ["\$post['product'] = \$product;", "'source' => 'product_cover'", "'type' => 'image'", "'url' => \$product['cover_url']"]),
    'desktop tablet and mobile layouts are defined' =>
        $containsAll($css, ['@media(max-width:1180px)', '@media(max-width:900px)', '@media(max-width:680px)', '@media(max-width:420px)']),
    'product and campaign content remains responsive' =>
        $containsAll($css, ['grid-template-columns:repeat(2,minmax(0,1fr))!important', '.mg-profile-campaign-list-full', 'grid-template-columns:minmax(0,1fr)!important']),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

$score = round((count($checks) - count($failed)) / max(1, count($checks)) * 10, 1);
echo 'Public profile content-first score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Public profile content-first validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo "Public profile content-first validation passed at 10.0/10.\n";
