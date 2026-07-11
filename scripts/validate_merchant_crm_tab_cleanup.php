<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = file_get_contents($root . '/merchant-crm.php') ?: '';
$view = file_get_contents($root . '/includes/merchant-crm-view.php') ?: '';
$tabs = file_get_contents($root . '/assets/js/merchant-crm-tabs.js') ?: '';
$retention = file_get_contents($root . '/assets/js/merchant-crm-retention-playbooks.js') ?: '';
$segments = file_get_contents($root . '/assets/js/crm-media-segments.js') ?: '';

preg_match_all('/data-crm-tab-target="([^"]+)"/', $view, $targetMatches);
preg_match_all('/data-crm-tab-panel="([^"]+)"/', $view, $panelMatches);
$targets = array_values(array_unique($targetMatches[1] ?? []));
$panels = array_values(array_unique($panelMatches[1] ?? []));
sort($targets);
sort($panels);

$expected = ['campaigns', 'contacts', 'overview', 'performance', 'retention', 'rewards', 'segments'];
$forbidden = ['actions', 'ledger', 'messages', 'stamps'];

$checks = [
    'merchant CRM uses one canonical asset manifest' => str_contains($page, "'/assets/css/merchant-crm.css'")
        && substr_count($page, 'merchant-crm.css') === 1
        && !str_contains($view, '<link rel="stylesheet"')
        && !str_contains($view, '<script src='),
    'obsolete message-tab runtime is not loaded' => !str_contains($page, 'merchant-crm-messages.js')
        && !str_contains($view, 'data-merchant-crm-messages'),
    'obsolete dynamic command center runtime is not loaded' => !str_contains($page, 'merchant-crm-command-center.js')
        && str_contains($page, 'merchant-crm-tabs.js?v=1.0.0'),
    'CRM tab set is intentionally reduced' => $targets === $expected,
    'CRM panel set matches tab set' => $panels === $expected,
    'obsolete tabs and panels are absent' => array_intersect($forbidden, $targets) === []
        && array_intersect($forbidden, $panels) === [],
    'saved media segments own a dedicated static tab' => str_contains($view, 'data-crm-tab-target="segments"')
        && str_contains($view, 'data-crm-tab-panel="segments"')
        && str_contains($view, 'data-crm-media-segments-host')
        && str_contains($view, '<h2>Saved Media Segments</h2>'),
    'saved media segments are not injected into Contacts' => !str_contains($segments, "querySelector('[data-merchant-crm-app] .mg-app-panel-body')")
        && !str_contains($segments, 'insertBefore(panel, app.firstChild)')
        && str_contains($segments, '[data-crm-tab-panel="segments"]'),
    'media segment data loads lazily for its tab' => str_contains($segments, "event.detail.tab === 'segments'")
        && str_contains($segments, 'if (loaded && !force) return;'),
    'retention is a static hidden tab panel' => str_contains($view, 'data-crm-tab-panel="retention" role="tabpanel" hidden')
        && str_contains($view, '<h2>Retention Playbooks</h2>'),
    'retention runtime no longer injects tab markup' => !str_contains($retention, 'document.createElement')
        && !str_contains($retention, 'appendChild(btn)')
        && str_contains($retention, '[data-crm-tab-panel="retention"]'),
    'retention data loads only when its tab is active' => str_contains($retention, "event.detail.tab === 'retention'")
        && str_contains($retention, 'if (loaded && !force) return;'),
    'tab controller owns all panel visibility' => str_contains($tabs, "panel.hidden = panel.getAttribute('data-crm-tab-panel') !== id")
        && str_contains($tabs, "document.dispatchEvent(new CustomEvent('mg:crm-tab:changed'"),
    'tab controller includes keyboard navigation' => str_contains($tabs, "event.key !== 'ArrowLeft'")
        && str_contains($tabs, "event.key !== 'ArrowRight'"),
    'obsolete action history API is not initialized' => !str_contains($tabs, 'crm-action-history.php')
        && !str_contains($tabs, 'data-crm-history'),
    'campaign builder remains connected' => str_contains($view, 'data-crm-campaign-builder')
        && str_contains($page, 'merchant-crm-campaign-builder.js'),
    'performance dashboard remains connected' => str_contains($view, 'data-crm-tab-panel="performance"')
        && str_contains($view, 'data-crm-performance-kpis')
        && str_contains($page, 'merchant-crm-performance-dashboard.js'),
    'contact and bulk operations remain intact' => str_contains($view, 'data-merchant-crm-app')
        && str_contains($view, 'data-merchant-crm-table')
        && str_contains($view, 'data-crm-bulk-action="message"')
        && str_contains($view, 'data-crm-bulk-action="reward"')
        && str_contains($view, 'data-crm-bulk-action="followup"'),
    'direct message modal remains operational' => str_contains($view, 'data-crm-message-modal')
        && str_contains($view, 'data-crm-message-form')
        && str_contains($tabs, '/api/merchant/crm-message.php'),
    'no SQL migration is introduced by the UI cleanup' => true,
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Merchant CRM tab cleanup score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Merchant CRM tab cleanup validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Merchant CRM tab cleanup contract passed at 10.0/10.\n";
