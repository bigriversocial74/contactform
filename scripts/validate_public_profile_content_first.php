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

$page = $read('profile.php');
$css = $read('assets/css/public-profile-content-first.css');
$runtime = $read('assets/js/public-profile-investment.js');
$profileApi = $read('api/public/profile.php');

$checks = [
    'content-first stylesheet remains loaded after the legacy profile stack' =>
        str_contains($page, '/assets/css/public-profile-content-first.css?v=1.0.0')
        && strpos($page, 'public-profile-content-first.css?v=1.0.0') > strpos($page, 'public-profile-realtime.css'),
    'page declares the content-first body authority' =>
        str_contains($page, 'mg-profile-content-first'),
    'cover image remains full-width and visible above content' =>
        str_contains($page, 'class="mg-invest-cover-card"')
        && str_contains($page, 'data-profile-cover')
        && str_contains($css, 'height:560px!important')
        && str_contains($css, 'margin-top:-168px!important'),
    'profile identity and actions share one unified hero card' =>
        str_contains($page, 'class="mg-profile-hero-card"')
        && str_contains($page, 'class="mg-profile-hero-identity"')
        && str_contains($page, 'class="mg-profile-hero-actions"')
        && str_contains($css, 'grid-template-columns:minmax(0,1fr) 320px'),
    'profile keeps avatar name merchant badge biography and meta' =>
        str_contains($page, 'data-profile-avatar')
        && str_contains($page, 'data-profile-name')
        && str_contains($page, 'mg-profile-merchant-badge')
        && str_contains($page, 'data-profile-biography')
        && str_contains($page, 'data-profile-meta'),
    'profile keeps follow message share and owner edit actions without Save' =>
        str_contains($page, 'data-profile-follow')
        && str_contains($page, 'data-profile-message')
        && str_contains($page, 'data-profile-share')
        && str_contains($page, 'data-profile-edit')
        && !str_contains($page, 'data-profile-save'),
    'public data dashboards charts and analytics panel are removed from markup' =>
        !str_contains($page, 'mg-invest-stat-board')
        && !str_contains($page, 'mg-invest-chart-row')
        && !str_contains($page, 'data-invest-market-chart')
        && !str_contains($page, 'data-invest-demand-meter')
        && !str_contains($page, 'mg-invest-sidebar')
        && !str_contains($page, 'Portfolio Snapshot')
        && !str_contains($page, 'Ticker Value')
        && !str_contains($page, 'Merchant Score')
        && !str_contains($page, 'Public analytics are not displayed.')
        && !str_contains($page, 'data-invest-panel="analytics"'),
    'six content tabs remain without Analytics' =>
        substr_count($page, 'data-invest-tab=') === 6
        && str_contains($page, 'data-invest-tab="overview"')
        && str_contains($page, 'data-invest-tab="products"')
        && str_contains($page, 'data-invest-tab="stories"')
        && str_contains($page, 'data-invest-tab="posts"')
        && str_contains($page, 'data-invest-tab="campaigns"')
        && str_contains($page, 'data-invest-tab="community"')
        && !str_contains($page, 'data-invest-tab="analytics"'),
    'overview focuses on products and campaigns' =>
        str_contains($page, 'Featured Experiences')
        && str_contains($page, 'data-profile-products-grid')
        && str_contains($page, 'Active Campaigns')
        && str_contains($page, 'data-invest-campaigns-list'),
    'Community remains a content surface rather than an analytics dashboard' =>
        str_contains($page, 'data-profile-community-summary')
        && str_contains($page, 'data-profile-community-campaigns')
        && str_contains($page, 'data-profile-community-accounts')
        && !str_contains($page, 'data-invest-analytics-grid'),
    'legacy required profile data hooks remain safely hidden' =>
        str_contains($page, 'class="mg-profile-data-bridge"')
        && str_contains($page, 'data-profile-followers')
        && str_contains($page, 'data-profile-supporters')
        && str_contains($page, 'data-profile-products')
        && str_contains($css, '.mg-profile-data-bridge')
        && str_contains($css, 'display:none!important'),
    'campaign runtime renders content cards without progress metrics' =>
        str_contains($runtime, 'mg-profile-campaign-card')
        && str_contains($runtime, 'mg-profile-campaign-icon')
        && str_contains($runtime, 'mg-profile-campaign-chevron')
        && !str_contains($runtime, 'document.createElement(\'progress\')')
        && !str_contains($runtime, 'issued_count'),
    'runtime no longer requests public market-series chart data' =>
        !str_contains($runtime, 'profile-market-series.php')
        && str_contains($runtime, '/api/public/profile-investment.php?slug='),
    'post API resolves linked product cover assets' =>
        str_contains($profileApi, 'mg_public_profile_attach_post_product_images')
        && str_contains($profileApi, 'catalog_product_version_assets')
        && str_contains($profileApi, "pva.role='cover'")
        && str_contains($profileApi, "cover.status='ready'"),
    'post API exposes product metadata and image media fallback' =>
        str_contains($profileApi, "\$post['product'] = \$product;")
        && str_contains($profileApi, "'source' => 'product_cover'")
        && str_contains($profileApi, "'type' => 'image'")
        && str_contains($profileApi, "'url' => \$product['cover_url']"),
    'desktop tablet and mobile layouts are defined' =>
        str_contains($css, '@media(max-width:1180px)')
        && str_contains($css, '@media(max-width:900px)')
        && str_contains($css, '@media(max-width:680px)')
        && str_contains($css, '@media(max-width:420px)'),
    'product and campaign content remains responsive' =>
        str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))!important')
        && str_contains($css, '.mg-profile-campaign-list-full')
        && str_contains($css, 'grid-template-columns:minmax(0,1fr)!important'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

$score = round((count($checks) - count($failed)) / max(1, count($checks)) * 10, 1);
echo 'Public profile content-first score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Public profile content-first validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Public profile content-first validation passed at 10.0/10.\n";
