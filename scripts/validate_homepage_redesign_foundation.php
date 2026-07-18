<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$indexPath = $root . '/index.php';
$merchantPath = $root . '/merchant-landing.php';

if (!is_file($indexPath) || !is_file($merchantPath)) {
    fwrite(STDERR, "Homepage files are missing.\n");
    exit(1);
}

$index = (string) file_get_contents($indexPath);
$merchant = (string) file_get_contents($merchantPath);

$checks = [
    'index app bootstrap' => str_contains($index, "require_once __DIR__ . '/includes/app.php'"),
    'index authenticated redirect' => str_contains($index, "header('Location: /inbox.php', true, 302)"),
    'index exact homepage shell' => str_contains($index, 'class="hero-scroll"') && str_contains($index, 'class="scene" id="scene"'),
    'index exact stylesheet' => str_contains($index, '/assets/css/homepage-parallax-exact-v2.css'),
    'index exact javascript' => str_contains($index, '/assets/js/homepage-parallax-exact-v2.js'),
    'index foreground image' => str_contains($index, '/assets/images/foreground.png'),
    'index mountains image' => str_contains($index, '/assets/images/mountains.png'),
    'index orb image' => str_contains($index, '/assets/images/orb.png'),
    'index no superseded v1 assets' => !str_contains($index, 'homepage-parallax-agent-v1.css') && !str_contains($index, 'homepage-parallax-agent-v1.js'),
    'index no placeholder root' => !str_contains($index, 'data-homepage-redesign-root'),
    'index no merchant shell' => !str_contains($index, 'class="mg-lb-home"'),
    'index no merchant stylesheet' => !str_contains($index, 'homepage-local-business-v1.css'),
    'merchant shell' => str_contains($merchant, 'class="mg-lb-home"'),
    'merchant business section' => str_contains($merchant, 'id="businesses"'),
    'merchant workflow section' => str_contains($merchant, 'id="how-it-works"'),
    'merchant rewards section' => str_contains($merchant, 'id="rewards"'),
    'merchant CRM integration' => str_contains($merchant, 'includes/landing/homepage-crm-integrations.php'),
    'merchant stylesheet' => str_contains($merchant, 'homepage-local-business-v1.css'),
    'merchant shared header' => str_contains($merchant, "require __DIR__ . '/includes/header.php'"),
    'merchant shared footer' => str_contains($merchant, "require __DIR__ . '/includes/footer.php'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Homepage foundation validation failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Homepage foundation validation passed (' . count($checks) . " checks).\n";
