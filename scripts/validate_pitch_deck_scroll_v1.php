<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$page = $read('pitch-deck.php');
$css = $read('assets/css/pitch-deck-scroll-v1.css');
$runtimeCss = $read('assets/css/pitch-deck-scroll-runtime-v2.css');
$js = $read('assets/js/pitch-deck-scroll-v1.js');
$workflow = $read('.github/workflows/pitch-deck-scroll-v1.yml');

$checks = [
    'pitch deck page exists' => $page !== '',
    'dedicated pitch deck assets are registered' => str_contains($page, '/assets/css/pitch-deck-scroll-v1.css?v=1.0.0')
        && str_contains($page, '/assets/js/pitch-deck-scroll-v1.js?v=1.0.0'),
    'page uses the shared public shell' => str_contains($page, "require __DIR__ . '/includes/header.php'")
        && str_contains($page, "require __DIR__ . '/includes/footer.php'"),
    'deck contains ten investor chapters' => substr_count($page, 'data-pitch-slide data-slide-label=') === 10
        && str_contains($page, 'data-slide-count="10"'),
    'investor narrative covers core deck sections' => str_contains($page, 'The problem')
        && str_contains($page, 'The solution')
        && str_contains($page, 'The business model')
        && str_contains($page, 'The market path')
        && str_contains($page, 'Go-to-market')
        && str_contains($page, 'Defensibility')
        && str_contains($page, 'The vision and the ask'),
    'current investor model assumptions are disclosed' => str_contains($page, '$49<small>/month</small>')
        && str_contains($page, '15<small>%</small>')
        && str_contains($page, '$1,174<small>MRR</small>')
        && str_contains($page, 'Current internal investor-model assumption'),
    'existing Microgifter landscape assets are reused' => str_contains($page, '/assets/images/mountains.png?v=2.0.0')
        && str_contains($page, '/assets/images/foreground.png?v=2.0.0')
        && str_contains($page, '/assets/images/orb.png?v=2.0.0'),
    'audited runtime v2 is loaded by the controller' => str_contains($js, "const RUNTIME_VERSION = '2.0.0'")
        && str_contains($js, '/assets/css/pitch-deck-scroll-runtime-v2.css?v=')
        && $runtimeCss !== '',
    'scroll track uses explicit pixel geometry' => str_contains($js, 'stepPixels * slideCount')
        && str_contains($js, "scrollSection.style.setProperty('height', `${sectionHeight}px`, 'important')")
        && str_contains($js, 'sectionHeight = stageHeight + stepPixels * slideCount'),
    'stage uses stable fixed positioning while active' => str_contains($js, "setImportant('position', 'fixed')")
        && str_contains($js, "setStageState('before')")
        && str_contains($js, "setStageState('after')")
        && str_contains($js, "setStageState('active')"),
    'slides have deliberate hold and reveal phases' => str_contains($js, 'const HOLD_PORTION = 0.74')
        && str_contains($js, 'const REVEAL_PORTION = 0.44')
        && str_contains($js, 'positionFromTimeline')
        && str_contains($js, 'revealForItem'),
    'controller avoids inertial progress lag' => !str_contains($js, 'currentProgress +=')
        && str_contains($js, 'timeline = timelineFromScroll()')
        && str_contains($js, 'rafId = 0'),
    'short desktop viewports are fitted safely' => str_contains($js, 'function fitSlides()')
        && str_contains($js, '--pitch-fit')
        && str_contains($runtimeCss, '@media (min-width: 901px) and (max-height: 900px)')
        && str_contains($runtimeCss, '@media (min-width: 901px) and (max-height: 740px)'),
    'runtime removes expensive full-slide blur repainting' => str_contains($runtimeCss, 'filter: none !important')
        && str_contains($runtimeCss, 'backdrop-filter: none !important')
        && str_contains($runtimeCss, 'animation-play-state: paused !important')
        && str_contains($js, "slide.style.setProperty('--slide-blur', '0px')"),
    'deck includes progress navigation and keyboard controls' => str_contains($page, 'data-pitch-jump="9"')
        && str_contains($js, "event.key === 'ArrowDown'")
        && str_contains($js, "event.key === 'Home'")
        && str_contains($js, "event.key === 'End'"),
    'mobile and reduced-motion fallbacks remain readable' => str_contains($css, '@media (max-width: 900px)')
        && str_contains($css, '@media (prefers-reduced-motion: reduce)')
        && str_contains($js, "window.matchMedia('(prefers-reduced-motion: reduce)')")
        && str_contains($js, 'showStaticSlides'),
    'print output supports one slide per page' => str_contains($css, '@media print')
        && str_contains($css, 'break-after: page'),
    'workflow covers the audited runtime stylesheet' => str_contains($workflow, "assets/css/pitch-deck-scroll-runtime-v2.css")
        && str_contains($workflow, 'node --check assets/js/pitch-deck-scroll-v1.js'),
    'investor calls to action are connected' => str_contains($page, 'href="/investors.php"')
        && str_contains($page, 'href="/learn-more.php"')
        && str_contains($page, 'linkedin.com/in/david-evans-15005530/'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 100);
echo 'Pitch deck scroll score: ' . $score . '/100' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Pitch deck validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Pitch deck scroll presentation contract passed at 100/100.\n";
