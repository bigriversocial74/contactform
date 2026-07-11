<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$feed = file_get_contents($root . '/feed.php') ?: '';
$layout = file_get_contents($root . '/assets/css/feed-centered-layout.css') ?: '';

$checks = [
    'feed page keeps canonical layout stylesheet last' => str_contains($feed, "'/assets/css/feed-online-chat.css',\n    '/assets/css/feed-centered-layout.css?v=2.1.0',"),
    'feed page does not duplicate app-loaded base feed css' => !str_contains($feed, "'/assets/css/social-feed.css'")
        && !str_contains($feed, "'/assets/css/social-feed-upload.css'"),
    'feed page does not duplicate footer-loaded presence css' => !str_contains($feed, "'/assets/css/store-presence-feed.css'"),
    'canonical layout declares one intentional top gap' => str_contains($layout, '--mg-feed-top-gap:8px')
        && str_contains($layout, 'padding:var(--mg-feed-top-gap) clamp(12px,1.6vw,24px) 64px!important'),
    'workspace padding is removed' => str_contains($layout, '.mg-app-workspace.mg-feed-workspace')
        && str_contains($layout, 'padding:0!important'),
    'desktop feed uses symmetric three-column centering' => str_contains($layout, 'grid-template-columns:minmax(0,1fr) minmax(0,var(--mg-feed-width)) minmax(260px,1fr)!important')
        && str_contains($layout, 'grid-column:2!important')
        && str_contains($layout, 'justify-self:center!important'),
    'desktop grid spans the full browser viewport around the fixed sidebar' => str_contains($layout, '@media(min-width:981px)')
        && str_contains($layout, 'width:calc(100% + var(--mg-app-sidebar,280px))!important')
        && str_contains($layout, 'margin-left:calc(-1 * var(--mg-app-sidebar,280px))!important'),
    'sponsored rail occupies right column without shifting feed' => str_contains($layout, 'grid-column:3!important')
        && str_contains($layout, 'max-width:var(--mg-feed-right-rail)!important')
        && str_contains($layout, 'justify-self:start!important'),
    'tablet fallback centers the feed in the full viewport' => str_contains($layout, '@media(max-width:1320px)')
        && str_contains($layout, 'width:min(var(--mg-feed-width),calc(100% - 32px))!important')
        && str_contains($layout, 'justify-self:center!important')
        && str_contains($layout, 'grid-template-columns:minmax(0,1fr)!important'),
    'empty story status no longer adds vertical space' => str_contains($layout, '.mg-feed-stories-header:has(.mg-stories-status:empty)')
        && str_contains($layout, '.mg-stories-status:empty')
        && str_contains($layout, 'display:none!important'),
    'legacy feed transform is neutralized' => str_contains($layout, 'transform:none!important')
        && !str_contains($layout, 'translateX(calc('),
    'mobile feed remains full width with a 58px app header' => str_contains($layout, '@media(max-width:640px)')
        && str_contains($layout, '--mg-app-header:58px')
        && str_contains($layout, 'width:100%!important')
        && str_contains($layout, 'padding:0 0 28px!important'),
    'feed functionality and story surfaces remain present' => str_contains($feed, 'data-social-feed')
        && str_contains($feed, 'data-feed-stories')
        && str_contains($feed, 'data-feed-list')
        && str_contains($feed, 'data-story-modal'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Feed desktop layout score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Feed desktop layout validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Feed desktop layout v2.1 contract passed at 10.0/10.\n";
