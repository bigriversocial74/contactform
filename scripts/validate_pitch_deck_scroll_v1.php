<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$page = $read('pitch-deck.php');
$css = $read('assets/css/pitch-deck-scroll-v1.css');
$js = $read('assets/js/pitch-deck-scroll-v1.js');

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
    'scroll presentation uses a sticky full viewport stage' => str_contains($css, '.pitch-deck.is-enhanced .pitch-sticky')
        && str_contains($css, 'position: sticky')
        && str_contains($css, 'height: calc(100svh - 72px)'),
    'sticky stage escapes clipped wrapper overflow' => str_contains($js, "deck.style.setProperty('overflow', 'visible', 'important')"),
    'slides support animated transition variables' => str_contains($css, '--slide-opacity')
        && str_contains($css, '--slide-y')
        && str_contains($css, '--slide-scale')
        && str_contains($css, '--slide-blur')
        && str_contains($css, '--slide-local'),
    'controller maps page scroll to all deck slides' => str_contains($js, 'currentProgress * (slideCount - 1)')
        && str_contains($js, 'slides.forEach((slide, index) => renderSlide')
        && str_contains($js, 'jumpTo(activeIndex + 1)'),
    'deck includes progress navigation and keyboard controls' => str_contains($page, 'data-pitch-jump="9"')
        && str_contains($js, "event.key === 'ArrowDown'")
        && str_contains($js, "event.key === 'Home'")
        && str_contains($js, "event.key === 'End'"),
    'mobile and reduced-motion fallbacks remain readable' => str_contains($css, '@media (max-width: 900px)')
        && str_contains($css, '@media (prefers-reduced-motion: reduce)')
        && str_contains($js, "window.matchMedia('(prefers-reduced-motion: reduce)')"),
    'print output supports one slide per page' => str_contains($css, '@media print')
        && str_contains($css, 'break-after: page'),
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
