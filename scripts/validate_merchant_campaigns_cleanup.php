<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $page = $read('merchant-campaigns.php');
    $controller = $read('assets/js/merchant-campaigns-cleanup.js');
    $styles = $read('assets/css/merchant-campaigns-cleanup.css');
    $embedLinks = $read('assets/js/campaign-embed-analytics-links.js');

    $expect(
        str_contains($page, 'merchant-campaigns-cleanup.css?v=1.0.0')
        && str_contains($page, 'merchant-campaigns-cleanup.js?v=1.0.0'),
        'Merchant Campaigns loads cache-bumped focused-layout assets'
    );

    $expect(
        str_contains($controller, "var removedTopTabs = ['active', 'performance', 'contacts']")
        && str_contains($controller, 'removedTopTabs.forEach(removeTopTab)'),
        'Active, Performance, and Contacts are removed from the top-level campaign navigation'
    );

    $expect(
        str_contains($controller, "var analyticsKeys = ['recommendations', 'landing_qa', 'refund_qa', 'queue']")
        && str_contains($controller, 'data-campaign-analytics-shell')
        && str_contains($controller, 'data-campaign-analytics-tabs')
        && str_contains($controller, 'moveAnalyticsSection'),
        'Recommendations, Landing QA, Refund QA, and Queue are consolidated into Analytics'
    );

    $expect(
        str_contains($controller, "root.querySelectorAll('.mg-campaign-side')")
        && str_contains($controller, "removeClosestPanel('[data-agent-action-list]')")
        && str_contains($controller, "removeClosestPanel('[data-campaign-insights-list]')")
        && str_contains($controller, "removeClosestPanel('[data-media-performance-list]')")
        && str_contains($controller, "removeClosestPanel('[data-customer-refund-creator-prompt]')"),
        'Right-side campaign panels, Demand Insights, media performance, and refund setup are removed'
    );

    $expect(
        str_contains($styles, 'grid-template-columns:minmax(0,1fr)!important')
        && str_contains($styles, '.mg-campaign-list-panel')
        && str_contains($styles, 'grid-column:1/-1'),
        'Campaign activity uses the full available workspace width'
    );

    $expect(
        str_contains($styles, '.mg-campaign-analytics-shell')
        && str_contains($styles, '.mg-campaign-analytics-tabs')
        && str_contains($styles, '.mg-campaign-analytics-content'),
        'The consolidated Analytics workspace has dedicated responsive styling'
    );

    $expect(
        !str_contains($embedLinks, 'data-campaign-embed-analytics-shortcut')
        && !str_contains($embedLinks, 'Embed Analytics')
        && str_contains($embedLinks, 'data-campaign-analytics-id'),
        'The global Embed Analytics shortcut is removed while per-campaign Analytics links remain'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant Campaigns cleanup validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Merchant Campaigns cleanup validation passed: {$passes} checks.\n";
