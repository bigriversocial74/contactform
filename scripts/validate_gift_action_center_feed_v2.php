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
        str_contains($feed, 'is-location')
        && str_contains($feed, 'is-time')
        && str_contains($feed, 'is-views')
        && str_contains($feed, 'relativeTime(value)'),
        'Compact feed keeps location, relative time, and views metadata'
    );

    $expect(
        str_contains($feed, "button.classList.remove('is-primary')")
        && str_contains($css, '.mg-gift-row-action.is-primary')
        && str_contains($css, 'background:#fff!important')
        && !str_contains($css, '.mg-gift-row.mg-gift-row-v2 .mg-gift-row-action.is-primary{background:#'),
        'All stacked row actions use the same neutral white treatment'
    );

    $expect(
        str_contains($feed, "folder === 'claimed'")
        && str_contains($feed, 'data-gift-action=\"load\"')
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

    foreach (['Location', 'Activity', 'Views', 'From', 'To', 'Type', 'Status', 'Expires', 'Gift ID', 'Source'] as $field) {
        $expect(str_contains($load, "detail('{$field}'"), "Load drawer includes {$field} metadata");
    }

    $expect(
        str_contains($css, 'grid-template-columns:72px minmax(0,1fr) 118px')
        && str_contains($css, 'border-radius:18px')
        && str_contains($css, 'box-shadow:0 5px 16px'),
        'Desktop feed uses compact avatar, content, and stacked-action cards'
    );

    $expect(
        str_contains($css, '@media(max-width:760px)')
        && str_contains($css, 'grid-template-columns:repeat(auto-fit,minmax(88px,1fr))')
        && str_contains($css, '.mg-load-detail-grid{grid-template-columns:1fr}'),
        'Mobile feed and Load metadata remain responsive'
    );

    $expect(
        str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($sent, "require __DIR__ . '/includes/gift-action-center.php'")
        && str_contains($claimed, "require __DIR__ . '/includes/gift-action-center.php'"),
        'Inbox, Sent, and Claimed share the redesigned feed include'
    );

    $expect(
        !str_contains($feed, 'Microgifter.post(')
        && !str_contains($load, 'Microgifter.post(')
        && !str_contains($feed, 'method: \'POST\'')
        && !str_contains($load, 'method: \'POST\''),
        'Feed redesign creates no mutation or transaction authority'
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
