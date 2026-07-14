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
    $view = $read('includes/merchant-crm-view.php');
    $styles = $read('assets/css/merchant-crm-contacts-only.css');
    $core = $read('assets/js/merchant-crm.js');
    $mobileStatStart = strpos($view, 'data-crm-contact-stat-strip');
    $mobileStatEnd = $mobileStatStart === false ? false : strpos($view, '</section>', $mobileStatStart);
    $mobileStatMarkup = $mobileStatStart !== false && $mobileStatEnd !== false
        ? substr($view, $mobileStatStart, $mobileStatEnd - $mobileStatStart)
        : '';

    $expect(
        str_contains($page, 'merchant-crm-contacts-only.css?v=1.1.0')
        && !str_contains($page, 'merchant-crm-overview-consolidation.css')
        && !str_contains($page, 'merchant-crm-overview-consolidation.js')
        && !str_contains($page, 'merchant-crm-performance-dashboard.js'),
        'Contacts-only layout replaces the former Overview consolidation assets'
    );

    $expect(
        str_contains($view, 'mg-crm-contacts-only')
        && str_contains($view, 'data-merchant-crm-shell')
        && str_contains($view, 'data-merchant-crm-app'),
        'Merchant CRM renders one contacts-only workspace'
    );

    $expect(
        !str_contains($view, 'data-crm-tab-panel="overview"')
        && !str_contains($view, 'data-crm-tab-target="overview"')
        && !str_contains($view, 'Campaign Command Center')
        && !str_contains($view, 'CRM Insight'),
        'The former tabbed Overview workspace remains removed'
    );

    $expect(
        !str_contains($view, 'data-crm-performance-section')
        && !str_contains($view, 'data-crm-performance-kpis')
        && !str_contains($view, 'Campaign Performance'),
        'Legacy performance workspace is not reintroduced'
    );

    $expect(
        !str_contains($view, 'data-crm-campaign-builder')
        && !str_contains($view, 'data-crm-media-segments-host')
        && !str_contains($view, 'Retention Playbooks'),
        'Campaign builder, media segments, and retention workspaces are removed'
    );

    $expect(
        substr_count($mobileStatMarkup, '<article') === 5
        && str_contains($mobileStatMarkup, 'data-crm-contact-stat-strip')
        && str_contains($mobileStatMarkup, 'data-crm-stat-high')
        && str_contains($mobileStatMarkup, 'data-crm-stat-followup')
        && str_contains($mobileStatMarkup, 'data-crm-stat-claimed'),
        'Five mobile contact statistics remain visible'
    );

    $expect(
        str_contains($view, 'data-merchant-crm-table')
        && str_contains($core, '/api/merchant/campaign-contacts.php')
        && str_contains($core, 'function renderContacts()'),
        'Contact table remains connected to the existing campaign contacts API'
    );

    $expect(
        str_contains($styles, '.mg-crm-contacts-only')
        && str_contains($styles, '.mg-crm-select-cell')
        && str_contains($styles, 'display:none!important'),
        'Contacts-only styles preserve the full-width table and hide selection controls'
    );

    $expect(
        str_contains($view, 'data-crm-drawer')
        && str_contains($view, 'data-crm-message-modal')
        && str_contains($view, 'data-crm-reward-modal'),
        'Individual contact timeline, message, and reward operations remain available'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant CRM contacts-only replacement validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Merchant CRM contacts-only replacement validation passed: {$passes} checks.\n";
