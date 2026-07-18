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
    'index shared header' => str_contains($index, "require __DIR__ . '/includes/header.php'"),
    'index shared footer' => str_contains($index, "require __DIR__ . '/includes/footer.php'"),
    'index current homepage shell' => str_contains($index, 'class="mg-ph-main"') && str_contains($index, 'data-ph-scene'),
    'index merchant route' => str_contains($index, "'/merchant-landing.php'"),
    'index current homepage assets' => str_contains($index, 'homepage-parallax-agent-v1.css') && str_contains($index, 'homepage-parallax-agent-v1.js'),
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
