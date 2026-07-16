<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$baseCss = $read('assets/css/create-menu.css');
$centerCss = $read('assets/css/create-center-inline.css');
$mobilePostCss = $read('assets/css/create-center-mobile-post-unified.css');
$manageCss = $read('assets/css/create-center-manage-actions.css');
$js = $read('assets/js/create-menu.js');
$centerJs = $read('assets/js/create-center-inline.js');
$postJs = $read('assets/js/create-center-post-inline.js');
$manageJs = $read('assets/js/create-center-manage-actions.js');
$template = $read('includes/header-templates/create-menu.php');
$listExtension = $read('includes/header-components/create-list-extension.php');
$postRuntime = $read('includes/header-components/post-composer-modal.php');
$composer = $read('includes/social-feed-composer.php');
$header = $read('includes/header.php');
$layoutFixes = $read('assets/css/layout-fixes.css');

$sections = [
    'Controlled stylesheet authority' => [
        is_file($root . '/assets/css/create-menu.css'),
        is_file($root . '/assets/css/create-center-inline.css'),
        is_file($root . '/assets/css/create-center-mobile-post-unified.css'),
        is_file($root . '/assets/css/create-center-manage-actions.css'),
        !is_file($root . '/assets/css/create-menu-fullscreen.css'),
        !is_file($root . '/assets/css/create-menu-desktop-force.css'),
        !str_contains($layoutFixes, 'create-menu-fullscreen.css'),
        substr_count($header, '/assets/css/create-menu.css') >= 1,
        str_contains($postRuntime, '/assets/css/create-center-inline.css'),
        str_contains($postRuntime, '/assets/css/create-center-mobile-post-unified.css'),
        str_contains($listExtension, '/assets/css/create-center-manage-actions.css?v=1.0.0'),
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
        str_contains($mobilePostCss, '.mg-create-center-post .mg-feed-upload-grid'),
    ],
    'Mobile removes duplicate tool row and cancel actions' => [
        str_contains($mobilePostCss, '@media(max-width:820px)'),
        str_contains($mobilePostCss, '.mg-create-center-rail'),
        str_contains($mobilePostCss, 'display:none!important'),
        str_contains($mobilePostCss, '.mg-create-inline-actions>.mg-create-secondary[data-create-center-home]'),
        str_contains($mobilePostCss, 'grid-template-rows:minmax(0,1fr)!important'),
        str_contains($mobilePostCss, 'env(safe-area-inset-bottom)'),
    ],
    'One controlled scroll region' => [
        str_contains($centerCss, 'overflow:hidden'),
        str_contains($centerCss, 'overflow:auto'),
        str_contains($centerCss, 'overscroll-behavior:contain'),
        str_contains($centerCss, 'body.mg-create-menu-open'),
    ],
    'Consistent accessible header' => [
        str_contains($template, 'aria-labelledby="mg-create-menu-title"'),
        str_contains($template, 'aria-describedby="mg-create-menu-description"'),
        str_contains($template, 'class="mg-create-menu-close"'),
        str_contains($template, 'aria-label="Close create center"'),
        !str_contains($postRuntime, 'data-global-post-composer'),
        !str_contains($postRuntime, 'mg-post-composer-x'),
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
        str_contains($js, 'input:not([disabled]),select:not([disabled]),textarea:not([disabled])'),
        !str_contains($js, "href === '/build.php'"),
    ],
    'All tools including Post use inline create-center views' => [
        str_contains($template, "'key' => 'product'"),
        str_contains($template, "'key' => 'campaign'"),
        str_contains($template, "'key' => 'reward'"),
        str_contains($template, "'key' => 'post'"),
        str_contains($template, "'key' => 'storefront'"),
        str_contains($template, "'key' => 'location'"),
        str_contains($template, 'data-create-inline-target="<?= mg_e($target) ?>"'),
        str_contains($template, 'aria-controls="mg-create-center-<?= mg_e($target) ?>"'),
        str_contains($template, 'data-create-center-view="post"'),
        str_contains($js, "node.hasAttribute('data-create-inline-target')"),
        str_contains($composer, 'mg-create-inline-post-composer'),
    ],
    'Direct submit and success behavior' => [
        substr_count($template, 'data-create-inline-form=') === 5,
        substr_count($template, 'data-create-inline-success=') === 5,
        str_contains($centerJs, "MG.post('/api/catalog/builder-draft.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/campaigns.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/reward-templates.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/storefront.php'"),
        str_contains($centerJs, "MG.post('/api/merchant/locations.php'"),
        str_contains($postJs, "MG.post('/api/social/posts.php'"),
        str_contains($template, 'data-create-post-success'),
        str_contains($centerJs, 'showSuccess('),
    ],
    'Home cards remove the duplicate intro and separate create from manage' => [
        str_contains($manageCss, '.mg-create-center-welcome{display:none!important}'),
        str_contains($manageCss, '.mg-create-center-card-open'),
        str_contains($manageCss, '.mg-create-center-manage'),
        str_contains($manageJs, 'intro.remove()'),
        str_contains($manageJs, "document.createElement('article')"),
        str_contains($manageJs, "document.createElement('button')"),
        str_contains($manageJs, "document.createElement('a')"),
        str_contains($manageJs, 'card.replaceWith(shell)'),
        str_contains($manageJs, "product: '/merchant-products.php'"),
        str_contains($manageJs, "campaign: '/merchant-campaigns.php'"),
        str_contains($manageJs, "reward: '/merchant-reward-templates.php'"),
        str_contains($manageJs, "post: '/feed.php?view=mine'"),
        str_contains($manageJs, "storefront: '/merchant-storefront.php'"),
        str_contains($manageJs, "location: '/merchant-locations.php'"),
        str_contains($manageJs, "list: '/lists.php'"),
        str_contains($listExtension, '/assets/js/create-center-manage-actions.js?v=1.0.0'),
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

echo "Create Menu UI v3 passed at 10.0/10.\n";
