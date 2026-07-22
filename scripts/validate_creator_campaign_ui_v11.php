<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$checks = [];
$add = static function (string $label, bool $passed) use (&$checks): void {
    $checks[] = ['label' => $label, 'passed' => $passed, 'points' => $passed ? 4 : 0];
};
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string) file_get_contents($full) : '';
};
$has = static fn(string $content, string $needle): bool => str_contains($content, $needle);

$baseCss = $read('assets/css/creator-campaign-ui-v11.css');
$componentCss = $read('assets/css/creator-campaign-ui-v11-components.css');
$overview = $read('includes/merchant-creator-campaigns-view.php');
$builder = $read('includes/merchant-creator-campaign-builder-view.php');
$detail = $read('includes/merchant-creator-campaign-detail-view.php');
$applications = $read('includes/merchant-creator-campaign-participation-view.php');
$contentReview = $read('includes/merchant-creator-campaign-deliverables-view.php');
$creatorDiscover = $read('includes/creator-campaigns-participation-view.php');
$creatorActive = $read('includes/creator-campaign-deliverables-view.php');
$detailJs = $read('assets/js/merchant-creator-campaign-detail-v11.js');
$overviewJs = $read('assets/js/merchant-creator-campaigns.js');
$docs = $read('docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE11_SIX_SCREEN_UI.md');

$routes = [
    'merchant-creator-campaigns.php',
    'merchant-creator-campaign-builder.php',
    'merchant-creator-campaign-detail.php',
    'merchant-creator-participation.php',
    'merchant-creator-deliverables.php',
    'creator-campaigns.php',
    'creator-campaign-deliverables.php',
];
$routeContents = array_map($read, $routes);
$combinedViews = implode("\n", [$overview, $builder, $detail, $applications, $contentReview, $creatorDiscover, $creatorActive]);

$add('Shared Phase 11 base design system', $baseCss !== '' && $has($baseCss, '--cc11-bg:#f7f8fa') && $has($baseCss, 'Light professional'));
$add('Mockup-specific component system', $componentCss !== '' && $has($componentCss, 'Dedicated merchant campaign detail') && $has($componentCss, 'Creator action center'));
$add('Responsive desktop tablet mobile rules', substr_count($baseCss . $componentCss, '@media(max-width:') >= 6 && $has($baseCss, 'max-width:600px'));
$add('Compact operational page headers', $has($baseCss, 'font-size:clamp(1.65rem,2.2vw,2.35rem)') && !$has($baseCss, 'min-height:100vh'));

$add('Merchant overview mockup composition', $has($overview, 'data-cc-screen="merchant-overview"') && $has($overview, 'Campaign health') && $has($overview, 'Campaign performance overview'));
$add('Ten-step builder mockup composition', $has($builder, 'data-cc-screen="campaign-builder"') && substr_count($builder, 'data-cc-step-button') >= 1 && $has($builder, 'Campaign summary'));
$add('Dedicated merchant campaign detail', $has($detail, 'data-cc-screen="merchant-campaign-detail"') && $has($detail, 'Conversion funnel') && $has($detail, 'Top Creators'));
$add('Merchant applications review composition', $has($applications, 'data-cc-screen="merchant-applications-review"') && $has($applications, 'Application Review') && $has($applications, 'Creator Directory'));
$add('Merchant content review composition', $has($contentReview, 'data-cc-screen="merchant-content-review"') && $has($contentReview, 'Submission Queue') && $has($contentReview, 'data-ccdv-review-dialog'));
$add('Creator discovery composition', $has($creatorDiscover, 'data-cc-screen="creator-discovery-active-workspace"') && $has($creatorDiscover, 'Campaign readiness') && $has($creatorDiscover, 'Discover Campaigns'));
$add('Creator active workspace composition', $has($creatorActive, 'data-cc-screen="creator-active-campaign-workspace"') && $has($creatorActive, 'Action center') && $has($creatorActive, 'Tracking & performance'));

$add('Every production route loads Phase 11 base CSS', count(array_filter($routeContents, static fn(string $content): bool => str_contains($content, 'creator-campaign-ui-v11.css'))) === count($routes));
$add('Every production route loads component CSS', count(array_filter($routeContents, static fn(string $content): bool => str_contains($content, 'creator-campaign-ui-v11-components.css'))) === count($routes));
$add('Merchant routes preserve authenticated app shell', $has($read('merchant-creator-campaign-detail.php'), 'mg-app-shell') && $has($read('merchant-creator-participation.php'), 'mg-app-sidebar'));
$add('Creator routes preserve account shell', $has($read('creator-campaigns.php'), 'mg-account-layout') && $has($read('creator-campaign-deliverables.php'), 'account-sidebar.php'));

$add('Existing overview runtime hooks preserved', $has($overview, 'data-cc-overview') && $has($overview, 'data-cc-metrics') && $has($overview, 'data-cc-list'));
$add('Existing builder runtime hooks preserved', $has($builder, 'data-cc-form') && $has($builder, 'data-cc-products') && $has($builder, 'data-cc-summary'));
$add('Existing participation runtime hooks preserved', $has($applications, 'data-ccp-filters') && $has($applications, 'data-ccp-list') && $has($applications, 'data-ccp-review-form'));
$add('Existing deliverable runtime hooks preserved', $has($contentReview, 'data-ccdv-filters') && $has($contentReview, 'data-ccdv-list') && $has($creatorActive, 'data-ccdv-submission-form'));

$add('Detail page reads canonical campaign API', $has($detailJs, '/api/merchant/creator-campaigns.php?action=detail') && $has($detailJs, 'campaign_id='));
$add('Detail page reads authoritative analytics API', $has($detailJs, '/api/merchant/creator-campaign-analytics.php') && $has($detailJs, 'range=last_30_days'));
$add('No duplicate analytics persistence', !$has($detailJs, 'localStorage') && !$has($detailJs, 'sessionStorage') && !$has($detailJs, 'POST'));
$add('Campaign cards open dedicated detail route', $has($overviewJs, '/merchant-creator-campaign-detail.php?campaign='));

$add('Canonical communication routes only', $has($combinedViews, '/merchant-creator-messages.php') && $has($combinedViews, '/creator-campaign-messages.php') && !$has($combinedViews, '/merchant-creator-campaign-messages.php'));
$add('No SQL or generated image requirement', $has($docs, '**No SQL required.**') && $has($docs, 'No new mockup or decorative image assets'));

$total = array_sum(array_column($checks, 'points'));
$failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['passed']));

foreach ($checks as $check) {
    echo sprintf("[%s] %s (%d/4)\n", $check['passed'] ? 'PASS' : 'FAIL', $check['label'], $check['points']);
}
echo sprintf("\nCreator Campaign Phase 11 UI score: %d/100\n", $total);

if ($total !== 100 || $failed !== []) {
    exit(1);
}
