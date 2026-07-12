<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$baseCss = $read('assets/css/create-menu.css');
$centerCss = $read('assets/css/create-center-inline.css');
$postCss = $read('assets/css/post-composer-modal.css');
$js = $read('assets/js/create-menu.js');
$centerJs = $read('assets/js/create-center-inline.js');
$template = $read('includes/header-templates/create-menu.php');
$postModal = $read('includes/header-components/post-composer-modal.php');
$header = $read('includes/header.php');
$layoutFixes = $read('assets/css/layout-fixes.css');

$sections = [
    'Controlled stylesheet authority' => [
        is_file($root . '/assets/css/create-menu.css'),
        is_file($root . '/assets/css/create-center-inline.css'),
        !is_file($root . '/assets/css/create-menu-fullscreen.css'),
        !is_file($root . '/assets/css/create-menu-desktop-force.css'),
        !str_contains($layoutFixes, 'create-menu-fullscreen.css'),
        substr_count($header, '/assets/css/create-menu.css') >= 1,
        str_contains($postModal, '/assets/css/create-center-inline.css'),
    ],
    'Full-screen professional workspace' => [
        str_contains($centerCss, 'width:100vw!important'),
        str_contains($centerCss, 'height:100dvh!important'),
        str_contains($centerCss, 'min-height:100svh!important'),
        str_contains($centerCss, 'grid-template-rows:auto minmax(0,1fr)!important'),
        str_contains($centerCss, 'grid-template-columns:240px minmax(0,1fr)'),
        str_contains($centerCss, 'box-shadow:none!important'),
    ],
    'Large desktop creation layout' => [
        str_contains($centerCss, '.mg-create-center-rail'),
        str_contains($centerCss, '.mg-create-center-content'),
        str_contains($centerCss, 'grid-template-columns:repeat(2,minmax(320px,1fr))!important'),
        str_contains($centerCss, '.mg-create-inline-form'),
        str_contains($centerCss, 'min-height:54px'),
        str_contains($centerCss, '.mg-create-inline-actions'),
    ],
    'Mobile viewport and responsive forms' => [
        str_contains($centerCss, '@media(max-width:820px)'),
        str_contains($centerCss, '@media(max-width:520px)'),
        str_contains($centerCss, 'grid-template-columns:1fr;grid-template-rows:auto minmax(0,1fr)'),
        str_contains($centerCss, '.mg-create-form-grid-2,.mg-create-form-grid-3,.mg-create-form-grid-4{grid-template-columns:1fr}'),
        str_contains($postCss, 'min-height:100svh'),
    ],
    'One controlled scroll region' => [
        str_contains($centerCss, 'overflow:hidden'),
        str_contains($centerCss, 'overflow:auto'),
        str_contains($centerCss, 'overscroll-behavior:contain'),
        str_contains($centerCss, 'body.mg-create-menu-open'),
        str_contains($postCss, 'overflow:auto'),
    ],
    'Consistent accessible header' => [
        str_contains($template, 'aria-labelledby="mg-create-menu-title"'),
        str_contains($template, 'aria-describedby="mg-create-menu-description"'),
        str_contains($template, 'class="mg-create-menu-close"'),
        str_contains($template, 'aria-label="Close create center"'),
        str_contains($postModal, 'aria-label="Close post composer"'),
        str_contains($postModal, 'class="mg-post-composer-x"'),
    ],
    'Professional icon and tool system' => [
        substr_count($template, '<svg viewBox="0 0 24 24"') >= 6,
        str_contains($baseCss, '.mg-create-menu-icon svg'),
        str_contains($baseCss, '.mg-create-menu-icon.is-product'),
        str_contains($baseCss, '.mg-create-menu-icon.is-location'),
        str_contains($template, 'data-create-tool-key='),
        !str_contains($baseCss, 'content:"🎁"'),
    ],
    'Explicit trigger and keyboard boundary' => [
        str_contains($js, 'explicitTriggerSelector'),
        str_contains($js, 'looksLikePlusControl'),
        str_contains($js, "node.matches(explicitTriggerSelector) || looksLikePlusControl(node)"),
        str_contains($js, "event.key === 'Escape'"),
        str_contains($js, "event.key !== 'Tab'"),
        str_contains($js, 'lastFocused.focus({ preventScroll: true })'),
        !str_contains($js, "href === '/build.php'"),
    ],
    'Inline creation routes and post handoff' => [
        str_contains($template, "'key' => 'product'"),
        str_contains($template, "'key' => 'campaign'"),
        str_contains($template, "'key' => 'reward'"),
        str_contains($template, "'key' => 'post'"),
        str_contains($template, "'key' => 'storefront'"),
        str_contains($template, "'key' => 'location'"),
        str_contains($template, 'data-create-inline-target="'),
        str_contains($template, 'aria-controls="mg-post-composer-modal"'),
        str_contains($js, "node.dataset.createMenuOption === 'post'"),
    ],
    'Direct submit and success behavior' => [
        substr_count($template, 'data-create-inline-form=') === 5,
        substr_count($template, 'data-create-inline-success=') === 5,
        str_contains($centerJs, "MG.post('/api/catalog/builder-draft.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/campaigns.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/reward-templates.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/storefront.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/locations.php'"),
        str_contains($centerJs, 'showSuccess('),
    ],
];

$passed = 0;
$failed = [];
foreach ($sections as $name => $checks) {
    $ok = !in_array(false, $checks, true);
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if ($ok) {
        $passed++;
    } else {
        $failed[] = $name;
    }
}

$score = round(($passed / max(1, count($sections))) * 10, 1);
echo 'Create Menu UI score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Failed sections: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Create Menu UI v2 passed at 10.0/10.\n";
