<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string) file_get_contents($full) : '';
};
$check = static function (bool $passed, string $label) use (&$failures, &$checks): void {
    $checks++;
    if (!$passed) $failures[] = $label;
};

$build = $read('build.php');
$sidebar = $read('includes/product-builder-sidebar.php');
$controller = $read('assets/js/builder-process-stabilization-v1.js');
$css = $read('assets/css/builder-process-stabilization-v1.css');
$api = $read('api/catalog/builder-draft.php');

$check(str_contains($build, 'data-builder-process-v1'), 'build.php enables the stabilized builder process');
$check(str_contains($build, 'builder-process-stabilization-v1.css'), 'build.php loads the scoped stabilization stylesheet');
$check(str_contains($build, 'builder-process-stabilization-v1.js'), 'build.php loads the consolidated builder controller');
$check(!str_contains($build, 'builder-stage4b.js'), 'build.php no longer loads the overlapping legacy controller');
$check(!str_contains($build, 'product-builder-shell.js'), 'build.php no longer loads the overlapping shell controller');
$check(!str_contains($build, 'builder-merchant-profile.js'), 'build.php no longer loads merchant polling helpers');
$check(!str_contains($build, 'builder-simple-product-post.js'), 'build.php no longer loads duplicate preview synchronization');
$check(!str_contains($build, 'builder-internal-slug.js'), 'build.php no longer loads the slug overwrite helper');
$check(!str_contains($build, '<style>'), 'build.php contains no inline builder patch stylesheet');

$check(str_contains($build, 'data-save-draft'), 'builder exposes Save Draft');
$check(str_contains($sidebar, 'data-publish-product'), 'builder exposes Publish Product in the publish step');
$check(str_contains($build, 'data-publish-product-link'), 'builder exposes View Product after publication');
$check(!str_contains($build, 'data-publish-store-link') && !str_contains($sidebar, 'data-publish-store-link'), 'builder does not expose View Store');
$check(!str_contains($build, 'data-publish-feed-link') && !str_contains($sidebar, 'data-publish-feed-link'), 'builder does not expose View Feed');
$check(!str_contains($build, 'Create Campaign') && !str_contains($sidebar, 'Create Campaign'), 'builder does not expose Create Campaign');

$check(str_contains($build, 'data-builder-status'), 'builder has a persistent accessible status region');
$check(str_contains($build, 'data-live-version-banner'), 'builder distinguishes live product and draft changes');
$check(str_contains($sidebar, 'data-publish-readiness'), 'publish step includes readiness feedback');
$check(str_contains($sidebar, 'id="slug" type="hidden"'), 'slug is an internal hidden field rather than a misleading editable control');

$check(str_contains($controller, 'state.uploading'), 'controller tracks active uploads');
$check(str_contains($controller, 'state.pendingPublish'), 'controller queues publication behind active work');
$check(str_contains($controller, 'beforeunload'), 'controller protects unsaved draft changes');
$check(str_contains($controller, 'lock_version: state.lockVersion'), 'controller preserves optimistic locking');
$check(str_contains($controller, "action: 'save'") && str_contains($controller, "action: 'publish'"), 'controller preserves save and publish API actions');
$check(str_contains($controller, 'data.product_url'), 'controller uses the canonical published product URL');
$check(str_contains($controller, "state.productStatus === 'published'"), 'controller models published versus draft state');
$check(str_contains($controller, "&p=' + encodeURIComponent(slug)"), 'controller reconstructs the canonical published product URL after reload');

$check(str_contains($css, '.mg-builder-shell[data-builder-process-v1]'), 'all stabilization CSS is page scoped');
$check(str_contains($css, '@media(max-width:900px)'), 'stabilization CSS includes mobile layout handling');
$check(str_contains($css, '@media(prefers-reduced-motion:reduce)'), 'stabilization CSS respects reduced motion');
$check(str_contains($api, 'mg_require_csrf_for_write'), 'builder API remains CSRF protected');
$check(str_contains($api, 'FOR UPDATE') && str_contains($api, 'lock_version'), 'builder API retains transactional optimistic locking');
$check(str_contains($api, 'mg_catalog_publish_distribution'), 'builder API retains canonical product distribution');

if ($failures) {
    echo "Builder Process Stabilization v1 validation failed:\n";
    foreach ($failures as $failure) echo ' - ' . $failure . "\n";
    echo count($failures) . ' of ' . $checks . " checks failed.\n";
    exit(1);
}

echo 'Builder Process Stabilization v1 validation passed: ' . $checks . " checks.\n";
