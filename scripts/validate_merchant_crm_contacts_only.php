<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string) file_get_contents($full) : '';
};

$view = $read('includes/merchant-crm-view.php');
$page = $read('merchant-crm.php');
$css = $read('assets/css/merchant-crm-contacts-only.css');

$checks = [
    'contacts-only shell is present' =>
        str_contains($view, 'mg-crm-contacts-only')
        && str_contains($view, 'data-merchant-crm-shell')
        && str_contains($view, 'data-merchant-crm-app'),
    'five contact statistics remain' =>
        substr_count($view, '<article') === 5
        && str_contains($view, 'data-crm-stat-high')
        && str_contains($view, 'data-crm-stat-followup')
        && str_contains($view, 'data-crm-stat-claimed')
        && str_contains($view, 'data-crm-contact-message-total')
        && str_contains($view, 'data-crm-contact-active-message-total'),
    'contact table remains active' =>
        str_contains($view, 'data-merchant-crm-table')
        && str_contains($view, 'Loading contacts'),
    'top CRM navigation is removed' =>
        !str_contains($view, 'mg-crm-toolbar')
        && !str_contains($view, 'mg-crm-tabs')
        && !str_contains($view, 'data-crm-tab-target')
        && !str_contains($view, 'mg-crm-distribution-btn'),
    'overview and secondary panels are removed' =>
        !str_contains($view, 'data-crm-tab-panel')
        && !str_contains($view, 'Campaign Command Center')
        && !str_contains($view, 'Campaign builder')
        && !str_contains($view, 'Campaign Performance')
        && !str_contains($view, 'Media Segments')
        && !str_contains($view, 'Retention'),
    'segment filters are removed' =>
        !str_contains($view, 'data-crm-segments')
        && !str_contains($view, 'data-crm-segment='),
    'bulk selection controls are removed' =>
        !str_contains($view, 'data-crm-bulk-bar')
        && !str_contains($view, 'data-crm-select-visible')
        && !str_contains($view, 'data-crm-bulk-action')
        && !str_contains($view, 'data-crm-bulk-modal'),
    'single-contact actions remain available' =>
        str_contains($view, 'data-crm-drawer')
        && str_contains($view, 'data-crm-message-modal')
        && str_contains($view, 'data-crm-reward-modal'),
    'contacts-only stylesheet is cache bumped and loaded last' =>
        str_contains($page, '/assets/css/merchant-crm-contacts-only.css?v=1.1.0')
        && strpos($page, 'merchant-crm-contacts-only.css?v=1.1.0') > strpos($page, 'merchant-crm-layout-stability.css?v=1.0.0'),
    'desktop rows define four visible columns' =>
        str_contains($css, 'Four visible columns: Contact, Campaign, Engagement, Actions')
        && str_contains($css, 'minmax(250px,1.08fr)')
        && str_contains($css, 'minmax(260px,1.12fr)')
        && str_contains($css, 'minmax(250px,.98fr)')
        && str_contains($css, 'minmax(190px,.72fr)'),
    'hidden source cells cannot consume grid tracks' =>
        str_contains($css, '.mg-crm-select-cell,')
        && str_contains($css, '.mg-crm-account-cell,')
        && str_contains($css, 'display:none!important')
        && str_contains($css, 'td:not(.mg-crm-select-cell):not(.mg-crm-account-cell)'),
    'contact and campaign copy cannot overlap' =>
        str_contains($css, 'overflow-wrap:anywhere')
        && str_contains($css, 'text-overflow:ellipsis')
        && str_contains($css, 'grid-template-columns:minmax(0,1fr)!important'),
    'action controls stay icon-only and bounded' =>
        str_contains($css, '.mg-crm-icon-btn span')
        && str_contains($css, 'display:none!important')
        && str_contains($css, 'max-width:36px!important'),
    'tablet and mobile rows reflow safely' =>
        str_contains($css, '@media(max-width:1180px)')
        && str_contains($css, 'grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr)!important')
        && str_contains($css, '@media(max-width:820px)')
        && str_contains($css, 'grid-template-columns:minmax(0,1fr)!important'),
    'non-contact module assets are not loaded' =>
        !str_contains($page, 'merchant-crm-tabs.js')
        && !str_contains($page, 'merchant-crm-overview-consolidation.js')
        && !str_contains($page, 'merchant-crm-campaign-builder.js')
        && !str_contains($page, 'merchant-crm-performance-dashboard.js')
        && !str_contains($page, 'merchant-crm-retention-playbooks.js')
        && !str_contains($page, 'crm-media-segments.js'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

$score = round((count($checks) - count($failed)) / max(1, count($checks)) * 10, 1);
echo 'Merchant CRM contacts-only score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Failed checks: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Merchant CRM contacts-only validation passed at 10.0/10.\n";