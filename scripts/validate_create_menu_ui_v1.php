<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$css = $read('assets/css/create-menu.css');
$js = $read('assets/js/create-menu.js');
$template = $read('includes/header-templates/create-menu.php');
$header = $read('includes/header.php');
$layoutFixes = $read('assets/css/layout-fixes.css');

$sections = [
    'Single stylesheet authority' => [
        is_file($root . '/assets/css/create-menu.css'),
        !is_file($root . '/assets/css/create-menu-fullscreen.css'),
        !is_file($root . '/assets/css/create-menu-desktop-force.css'),
        !str_contains($layoutFixes, 'create-menu-fullscreen.css'),
        !str_contains($js, 'create-menu-desktop-force.css'),
        substr_count($header, '/assets/css/create-menu.css') >= 1,
    ],
    'Professional dialog shell' => [
        str_contains($css, 'width:min(960px,100%)'),
        str_contains($css, 'max-height:calc(100dvh - 48px)'),
        str_contains($css, 'grid-template-rows:auto minmax(0,1fr)'),
        str_contains($css, 'border-radius:26px'),
        str_contains($css, 'box-shadow:0 34px 100px'),
    ],
    'Compact desktop choice layout' => [
        str_contains($css, 'grid-template-columns:repeat(auto-fit,minmax(320px,1fr))'),
        str_contains($css, 'grid-template-columns:52px minmax(0,1fr) 34px'),
        str_contains($css, 'min-height:104px'),
        str_contains($css, '.mg-create-menu-copy'),
        str_contains($css, '.mg-create-menu-arrow'),
    ],
    'Mobile viewport and safe areas' => [
        str_contains($css, '@media(max-width:720px)'),
        str_contains($css, 'height:100dvh'),
        str_contains($css, 'min-height:100svh'),
        str_contains($css, 'grid-template-columns:1fr'),
        str_contains($css, 'env(safe-area-inset-top)'),
        str_contains($css, 'env(safe-area-inset-bottom)'),
    ],
    'One controlled scroll region' => [
        str_contains($css, 'overflow:hidden'),
        str_contains($css, 'overflow-y:auto'),
        str_contains($css, 'overscroll-behavior:contain'),
        str_contains($css, 'body.mg-create-menu-open'),
    ],
    'Consistent accessible header' => [
        str_contains($template, 'aria-labelledby="mg-create-menu-title"'),
        str_contains($template, 'aria-describedby="mg-create-menu-description"'),
        str_contains($template, 'class="mg-create-menu-close"'),
        str_contains($template, 'aria-label="Close create menu"'),
        str_contains($css, '.mg-create-menu-close:focus-visible'),
    ],
    'Professional icon system' => [
        substr_count($template, '<svg viewBox="0 0 24 24"') >= 6,
        str_contains($css, '.mg-create-menu-icon svg'),
        str_contains($css, '.mg-create-menu-icon.is-product'),
        str_contains($css, '.mg-create-menu-icon.is-location'),
        !str_contains($css, 'content:"🎁"'),
    ],
    'Explicit trigger boundary' => [
        str_contains($js, 'explicitTriggerSelector'),
        str_contains($js, 'looksLikePlusControl'),
        str_contains($js, "node.matches(explicitTriggerSelector) || looksLikePlusControl(node)"),
        !str_contains($js, "href === '/build.php'"),
        !str_contains($js, 'MutationObserver'),
    ],
    'Keyboard and focus behavior' => [
        str_contains($js, "event.key === 'Escape'"),
        str_contains($js, "event.key !== 'Tab'"),
        str_contains($js, 'lastFocused.focus({ preventScroll: true })'),
        str_contains($js, 'dialog.focus()'),
        str_contains($js, 'setExpanded(true)'),
        str_contains($js, 'setExpanded(false)'),
    ],
    'Creation routes and post handoff' => [
        str_contains($template, 'data-create-menu-option="microgift"'),
        str_contains($template, 'data-create-menu-option="campaign"'),
        str_contains($template, 'data-create-menu-option="agent_offer"'),
        str_contains($template, 'data-create-menu-option="post"'),
        str_contains($template, 'data-create-menu-option="storefront"'),
        str_contains($template, 'data-create-menu-option="location"'),
        str_contains($template, 'aria-controls="mg-post-composer-modal"'),
        str_contains($js, "node.dataset.createMenuOption === 'post'"),
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

echo "Create Menu UI v1 passed at 10.0/10.\n";
