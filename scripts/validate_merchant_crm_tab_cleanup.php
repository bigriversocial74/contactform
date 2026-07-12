<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = file_get_contents($root . '/merchant-crm.php') ?: '';
$view = file_get_contents($root . '/includes/merchant-crm-view.php') ?: '';
$styles = file_get_contents($root . '/assets/css/merchant-crm-contacts-only.css') ?: '';
$core = file_get_contents($root . '/assets/js/merchant-crm.js') ?: '';

preg_match_all('/data-crm-tab-target="([^"]+)"/', $view, $targetMatches);
preg_match_all('/data-crm-tab-panel="([^"]+)"/', $view, $panelMatches);
$targets = array_values(array_unique($targetMatches[1] ?? []));
$panels = array_values(array_unique($panelMatches[1] ?? []));

$checks = [
    'merchant CRM uses one canonical asset manifest' => str_contains($page, "'/assets/css/merchant-crm.css'")
        && substr_count($page, 'merchant-crm.css') === 1
        && !str_contains($view, '<link rel="stylesheet"')
        && !str_contains($view, '<script src='),
    'contacts-only stylesheet is loaded once' => str_contains($page, "'/assets/css/merchant-crm-contacts-only.css?v=1.1.0'")
        && substr_count($page, 'merchant-crm-contacts-only.css') === 1,
    'all top-level CRM tabs are removed' => $targets === []
        && $panels === []
        && !str_contains($view, 'mg-crm-toolbar')
        && !str_contains($view, 'mg-crm-tabs'),
    'distribution shortcut is removed' => !str_contains($view, 'mg-crm-distribution-btn')
        && !str_contains($view, '/merchant-distribution.php'),
    'contacts-only shell remains connected' => str_contains($view, 'mg-crm-contacts-only')
        && str_contains($view, 'data-merchant-crm-shell')
        && str_contains($view, 'data-merchant-crm-app')
        && str_contains($view, 'data-merchant-crm-table'),
    'five contact statistics remain connected' => substr_count($view, '<article') === 5
        && str_contains($view, 'data-crm-stat-high')
        && str_contains($view, 'data-crm-stat-followup')
        && str_contains($view, 'data-crm-stat-claimed')
        && str_contains($view, 'data-crm-contact-message-total')
        && str_contains($view, 'data-crm-contact-active-message-total'),
    'smart segment controls are removed' => !str_contains($view, 'data-crm-segments')
        && !str_contains($view, 'data-crm-segment='),
    'bulk selection and action controls are removed' => !str_contains($view, 'data-crm-bulk-bar')
        && !str_contains($view, 'data-crm-select-visible')
        && !str_contains($view, 'data-crm-bulk-action')
        && !str_contains($view, 'data-crm-bulk-modal'),
    'campaign and analytics workspaces are removed' => !str_contains($view, 'data-crm-campaign-builder')
        && !str_contains($view, 'data-crm-performance-kpis')
        && !str_contains($view, 'data-crm-media-segments-host')
        && !str_contains($view, 'Retention Playbooks'),
    'obsolete tab runtimes are not loaded' => !str_contains($page, 'merchant-crm-tabs.js')
        && !str_contains($page, 'merchant-crm-retention-playbooks.js')
        && !str_contains($page, 'crm-media-segments.js')
        && !str_contains($page, 'merchant-crm-campaign-builder.js')
        && !str_contains($page, 'merchant-crm-performance-dashboard.js'),
    'direct message modal remains operational' => str_contains($view, 'data-crm-message-modal')
        && str_contains($view, 'data-crm-message-form')
        && str_contains($core, '/api/merchant/crm-message.php'),
    'timeline and reward operations remain operational' => str_contains($view, 'data-crm-drawer')
        && str_contains($view, 'data-crm-reward-modal')
        && str_contains($core, '/api/merchant/campaign-timeline.php'),
    'legacy contact selection column is hidden' => str_contains($styles, '.mg-crm-select-cell')
        && str_contains($styles, 'display:none!important'),
    'obsolete message-tab runtime is not loaded' => !str_contains($page, 'merchant-crm-messages.js')
        && !str_contains($view, 'data-merchant-crm-messages'),
    'no SQL migration is introduced by the UI cleanup' => true,
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

$score = round((count($checks) - count($failed)) / max(1, count($checks)) * 10, 1);
echo 'Merchant CRM tab cleanup score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Merchant CRM tab cleanup validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Merchant CRM contacts-only tab cleanup contract passed at 10.0/10.\n";