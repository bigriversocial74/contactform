<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'index' => $root . '/index.php',
    'css' => $root . '/assets/css/homepage-parallax-agent-v1.css',
    'js' => $root . '/assets/js/homepage-parallax-agent-v1.js',
    'header' => $root . '/includes/header.php',
    'footer' => $root . '/includes/footer.php',
];

$source = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$key} file: {$path}" . PHP_EOL);
        exit(1);
    }
    $source[$key] = (string) file_get_contents($path);
}

$mainCount = substr_count(strtolower($source['index']), '<main');
$checks = [
    'Homepage keeps the shared Microgifter header' => str_contains($source['index'], "require __DIR__ . '/includes/header.php';"),
    'Homepage keeps the shared Microgifter footer' => str_contains($source['index'], "require __DIR__ . '/includes/footer.php';"),
    'Uploaded standalone header is not duplicated' => !str_contains($source['index'], 'class="os-bar"') && !str_contains($source['index'], 'class="site-header"'),
    'Uploaded standalone footer is not duplicated' => !str_contains($source['index'], 'class="site-footer"'),
    'Homepage has one semantic main region' => $mainCount === 1 && str_contains($source['index'], 'class="mg-ph-main"'),
    'Shared footer remains responsible for closing the main region' => !str_contains($source['index'], '</main>') && str_contains($source['footer'], '</main>'),
    'Logged-out homepage body class scopes the design' => str_contains($source['index'], "\$page_body_class = 'mg-parallax-home';"),
    'Homepage loads dedicated cache-busted CSS and JavaScript' => str_contains($source['index'], '/assets/css/homepage-parallax-agent-v1.css?v=1.0.0') && str_contains($source['index'], '/assets/js/homepage-parallax-agent-v1.js?v=1.0.0'),
    'Homepage includes relationship, agent, PPPM, and CTA chapters' => str_contains($source['index'], 'id="relationship-system"') && str_contains($source['index'], 'id="agent-in-action"') && str_contains($source['index'], 'id="pppm-presentation"') && str_contains($source['index'], 'id="get-started"'),
    'Hero contains three explicit scroll phases' => str_contains($source['index'], 'data-ph-copy-one') && str_contains($source['index'], 'data-ph-copy-two') && str_contains($source['index'], 'data-ph-growth'),
    'Growth chart exposes five relationship signals' => substr_count($source['index'], 'mg-ph-line mg-ph-line-') === 5 && str_contains($source['index'], 'Sales growth'),
    'PPPM presentation contains six lifecycle feature articles' => substr_count($source['index'], '<article class="mg-ph-feature') === 6,
    'Homepage CTA routes use existing Microgifter destinations' => str_contains($source['index'], 'href="/signup.php"') && str_contains($source['index'], 'href="/learn-more.php"') && str_contains($source['index'], 'href="/discover.php"'),
    'CSS is scoped to the homepage shell' => str_contains($source['css'], '.mg-ph-main') && str_contains($source['css'], 'body.mg-parallax-home') && !preg_match('/(^|\})\s*(body|html|a|h1|h2)\s*\{/m', $source['css']),
    'CSS protects shared header and footer layering' => str_contains($source['css'], 'body.mg-parallax-home .mg-site-header') && str_contains($source['css'], 'body.mg-parallax-home .mg-site-footer'),
    'CSS aligns desktop and mobile hero to shared header heights' => str_contains($source['css'], 'calc(100vh - 72px)') && str_contains($source['css'], 'calc(100vh - 64px)'),
    'CSS provides responsive and reduced-motion layouts' => str_contains($source['css'], '@media(max-width:760px)') && str_contains($source['css'], '@media(prefers-reduced-motion:reduce)'),
    'Decorative landscape and orb are CSS-native' => str_contains($source['css'], '.mg-ph-mountains') && str_contains($source['css'], '.mg-ph-orb') && !str_contains($source['css'], 'mountains.png') && !str_contains($source['css'], 'orb.png'),
    'JavaScript boots only on the parallax homepage' => str_contains($source['js'], "document.querySelector('.mg-ph-main')") && str_contains($source['js'], '__mgHomepageParallaxAgentV1Booted'),
    'JavaScript calculates bounded hero scroll progress' => str_contains($source['js'], 'hero.getBoundingClientRect()') && str_contains($source['js'], 'hero.offsetHeight - window.innerHeight') && str_contains($source['js'], 'clamp(-rect.top / distance, 0, 1)'),
    'JavaScript updates all three hero chapters' => str_contains($source['js'], 'data-ph-copy-one') && str_contains($source['js'], 'data-ph-copy-two') && str_contains($source['js'], 'data-ph-growth'),
    'JavaScript animates charts and reveals efficiently' => str_contains($source['js'], 'strokeDashoffset') && str_contains($source['js'], 'IntersectionObserver') && str_contains($source['js'], 'requestAnimationFrame'),
    'JavaScript honors reduced motion' => str_contains($source['js'], 'prefers-reduced-motion: reduce') && str_contains($source['js'], 'reducedMotion.matches'),
    'Homepage does not introduce inline external script dependencies' => !preg_match('/<script[^>]+src=["\']https?:\/\//i', $source['index']),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Logged-out homepage parallax validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Logged-out homepage parallax validation passed (' . count($checks) . ' checks).' . PHP_EOL;
