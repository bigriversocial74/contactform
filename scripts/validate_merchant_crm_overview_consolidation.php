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
    $page = $read('merchant-crm.php');
    $controller = $read('assets/js/merchant-crm-overview-consolidation.js');
    $performance = $read('assets/js/merchant-crm-performance-dashboard.js');
    $styles = $read('assets/css/merchant-crm-overview-consolidation.css');

    $expect(
        str_contains($page, 'merchant-crm-overview-consolidation.css?v=1.0.0')
        && str_contains($page, 'merchant-crm-overview-consolidation.js?v=1.0.0')
        && str_contains($page, 'merchant-crm-performance-dashboard.js?v=2.0.0'),
        'Merchant CRM loads cache-bumped consolidation assets'
    );

    $expect(
        strpos($page, 'merchant-crm-overview-consolidation.js?v=1.0.0')
        < strpos($page, 'merchant-crm-tabs.js?v=1.0.0'),
        'Overview consolidation runs before the shared tab controller captures panels'
    );

    foreach (['campaigns', 'performance', 'rewards', 'segments', 'drafts', 'draft_review', 'launch_audit'] as $target) {
        $expect(
            str_contains($controller, $target . ': true'),
            'Consolidation removes the ' . $target . ' top-level workspace'
        );
    }

    $expect(
        str_contains($controller, "setAttribute('data-crm-performance-section', '')")
        && str_contains($controller, 'movePerformanceIntoOverview')
        && str_contains($controller, 'overview.appendChild(performance)'),
        'Performance panel is converted into an Overview section'
    );

    $expect(
        str_contains($controller, 'MutationObserver')
        && str_contains($controller, 'restoreOverviewWhenNeeded'),
        'Late dynamic Draft Review and Launch Audit tabs are continuously filtered'
    );

    $expect(
        str_contains($performance, 'ensureOverviewSection')
        && str_contains($performance, 'data-crm-performance-section')
        && str_contains($performance, "ev.detail.tab==='overview'")
        && !str_contains($performance, "setAttribute('data-crm-tab-target','performance')"),
        'Performance controller loads in Overview without recreating a Performance tab'
    );

    $expect(
        str_contains($styles, '[data-crm-tab-target="campaigns"]')
        && str_contains($styles, '[data-crm-tab-target="drafts"]')
        && str_contains($styles, '[data-crm-tab-target="launch-audit"]')
        && str_contains($styles, '[data-crm-performance-section].mg-crm-overview-performance'),
        'Removed tabs are hidden before JavaScript and Overview performance is styled'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant CRM overview consolidation validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Merchant CRM overview consolidation validation passed: {$passes} checks.\n";
