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
    $include = $read('includes/gift-action-center.php');
    $feed = $read('assets/js/gift-action-center-feed-v2.js');
    $load = $read('assets/js/gift-envelope-presentation.js');
    $source = $read('assets/js/gift-source-metadata.js');
    $css = $read('assets/css/gift-action-center-feed-v2.css');
    $center = $read('assets/js/gift-action-center.js');
    $inbox = $read('inbox.php');
    $sent = $read('sent.php');
    $claimed = $read('claimed.php');

    $expect(
        str_contains($include, '/assets/css/gift-action-center-feed-v2.css')
        && str_contains($include, '/assets/js/gift-action-center-feed-v2.js'),
        'Shared Action Center include loads the feed v2 assets'
    );

    $expect(
        str_contains($feed, "const folders = ['inbox', 'sent', 'claimed']")
        && str_contains($feed, 'new MutationObserver(scheduleApply)')
        && str_contains($feed, 'requestFolder(activeFolder, false)'),
        'Feed v2 covers Inbox, Sent, and Claimed dynamic renders'
    );

    $expect(
        str_contains($feed, 'function upsertBusinessName(row, item)')
        && str_contains($feed, "business.className = 'mg-gift-business-name'")
        && str_contains($feed, 'business.textContent = businessNameFor(item)'),
        'Cards place the business name directly beneath the title row'
    );

    $expect(
        str_contains($feed, 'is-sender')
        && str_contains($feed, 'Sent from ')
        && str_contains($feed, 'is-time')
        && str_contains($feed, 'is-views')
        && !str_contains($feed, 'is-location'),
        'Compact card metadata shows sender, relative time, and views instead of participating location'
    );

    $expect(
        str_contains($source, "row.querySelectorAll('[data-gift-source-meta]').forEach")
        && str_contains($source, 'row.dataset.giftSourceLabel')
        && str_contains($source, 'row.dataset.giftSourceDetail')
        && str_contains($source, 'row.dataset.giftSourceReference')
        && !str_contains($source, "span.innerHTML = 'Source: '"),
        'Source metadata remains available for Load without rendering a Source line in feed cards'
    );

    $expect(
        str_contains($feed, "button.classList.remove('is-primary')")
        && str_contains($css, '.mg-gift-row-action.is-primary')
        && str_contains($css, 'background:#fff!important')
        && !str_contains($css, 'background:#1261e8!important')
        && !str_contains($css, 'background:#2563eb!important')
        && !str_contains($css, 'background:#1d4ed8!important'),
        'All row actions use the same neutral white treatment'
    );

    $expect(
        str_contains($feed, "folder === 'claimed'")
        && str_contains($feed, 'data-gift-action="load"')
        && str_contains($center, "data-gift-action=\"load\""),
        'Load remains available across the Action Center folders'
    );

    $expect(
        str_contains($load, "document.querySelector('[data-gift-drawer]')")
        && str_contains($load, "document.querySelector('[data-gift-drawer-content]')")
        && str_contains($load, "window.addEventListener('click', async")
        && str_contains($load, "data-gift-action=\"load\""),
        'Canonical Load controller resolves portaled drawer elements from document scope'
    );

    foreach (['Business', 'Sent From', 'Sent To', 'Location', 'Activity', 'Views', 'Type', 'Status', 'Expires', 'Gift ID', 'Source', 'Source Detail', 'Source Reference'] as $field) {
        $expect(str_contains($load, "detail('{$field}'"), "Load drawer includes {$field} metadata");
    }

    $expect(
        str_contains($load, 'function mergeRowMetadata(item, row)')
        && str_contains($load, 'row.dataset.giftSourceLabel')
        && str_contains($load, 'row.dataset.giftSourceDetail')
        && str_contains($load, 'row.dataset.giftSourceReference'),
        'Load merges source metadata retained on the card without exposing it in the feed'
    );

    $expect(
        str_contains($css, 'grid-template-columns:72px minmax(0,1fr) 118px')
        && str_contains($css, 'border-radius:18px')
        && str_contains($css, 'box-shadow:0 5px 16px'),
        'Desktop feed keeps the approved compact avatar, content, and stacked-action layout'
    );

    $expect(
        str_contains($css, '@media(max-width:760px)')
        && str_contains($css, '.mg-gift-center-workspace{padding:0!important}')
        && str_contains($css, '.mg-gift-feed-column{padding:0}')
        && str_contains($css, 'align-items:start')
        && str_contains($css, 'grid-template-columns:repeat(auto-fit,minmax(0,1fr))')
        && str_contains($css, 'font-size:9px')
        && str_contains($css, 'min-height:32px'),
        'Mobile feed uses full width, top-aligned imagery, and smaller equal-width buttons'
    );

    $expect(
        str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($sent, "require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($claimed, "require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($inbox, '/assets/js/gift-source-metadata.js')
        && str_contains($sent, '/assets/js/gift-source-metadata.js')
        && str_contains($claimed, '/assets/js/gift-source-metadata.js'),
        'Inbox, Sent, and Claimed share the redesigned feed and source metadata controller'
    );

    $expect(
        !str_contains($feed, 'Microgifter.post(')
        && !str_contains($load, 'Microgifter.post(')
        && !str_contains($source, 'Microgifter.post(')
        && !str_contains($feed, "method: 'POST'")
        && !str_contains($load, "method: 'POST'")
        && !str_contains($source, "method: 'POST'"),
        'Feed refinement creates no mutation or transaction authority'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Gift Action Center feed v2 validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Gift Action Center feed v2 validation passed: {$passes} checks.\n";
