<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$indexPath = $root . '/index.php';
$merchantPath = $root . '/merchant-landing.php';

if (!is_file($indexPath) || !is_file($merchantPath)) {
    fwrite(STDERR, "Homepage foundation files are missing.\n");
    exit(1);
}

$index = (string) file_get_contents($indexPath);
$merchant = (string) file_get_contents($merchantPath);

$checks = [
    'index keeps shared header' => str_contains($index, "require __DIR__ . '/includes/header.php'"),
    'index keeps shared footer' => str_contains($index, "require __DIR__ . '/includes/footer.php'"),
    'index exposes redesign root' => str_contains($index, 'data-homepage-redesign-root'),
    'index links merchant landing' => str_contains($index, "'/merchant-landing.php'"),
    'index redirects legacy merchant anchors' => str_contains($index, "window.location.replace('/merchant-landing.php'"),
    'index removes legacy homepage shell' => !str_contains($index, 'class="mg-lb-home"'),
    'index removes merchant homepage stylesheet' => !str_contains($index, 'homepage-local-business-v1.css'),
    'merchant landing keeps previous homepage shell' => str_contains($merchant, 'class="mg-lb-home"'),
    'merchant landing keeps business section' => str_contains($merchant, 'id="businesses"'),
    'merchant landing keeps workflow section' => str_contains($merchant, 'id="how-it-works"'),
    'merchant landing keeps rewards section' => str_contains($merchant, 'id="rewards"'),
    'merchant landing keeps CRM integrations' => str_contains($merchant, "includes/landing/homepage-crm-integrations.php"),
    'merchant landing keeps merchant stylesheet' => str_contains($merchant, 'homepage-local-business-v1.css'),
    'merchant landing keeps shared header' => str_contains($merchant, "require __DIR__ . '/includes/header.php'"),
    'merchant landing keeps shared footer' => str_contains($merchant, "require __DIR__ . '/includes/footer.php'"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "Homepage redesign foundation validation failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Homepage redesign foundation validation passed (' . count($checks) . " checks).\n";
