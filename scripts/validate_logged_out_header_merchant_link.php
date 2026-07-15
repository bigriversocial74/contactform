<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$headerPath = $root . '/includes/header-components/public-header.php';
$failures = [];
$passes = 0;

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }

    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    if (!is_file($headerPath)) {
        throw new RuntimeException('Missing shared public header component.');
    }

    $header = file_get_contents($headerPath);
    if (!is_string($header)) {
        throw new RuntimeException('Unable to read shared public header component.');
    }

    $expect(
        str_contains($header, "['label' => 'For Businesses', 'href' => '/merchant-landing.php']"),
        'Logged-out navigation includes the merchant landing page'
    );
    $expect(
        !str_contains($header, "['label' => 'Explore', 'href' => '/discover.php']"),
        'Logged-out navigation no longer includes Explore'
    );
    $expect(
        str_contains($header, "['label' => 'Case Studies', 'href' => '/featured-case-studies.php']")
        && str_contains($header, "['label' => 'Blog', 'href' => '/blog.php']")
        && str_contains($header, "['label' => 'Pricing', 'href' => '/pricing.php']"),
        'Existing logged-out navigation links remain intact'
    );
    $expect(
        substr_count($header, 'foreach ($public_nav_links as $public_header_link)') >= 2,
        'Desktop and mobile navigation share the same logged-out link source'
    );
    $expect(
        str_contains($header, 'data-header-template="logged-out-public"')
        && str_contains($header, 'class="mg-public-mobile-nav"'),
        'Desktop and mobile logged-out header templates remain present'
    );
    $expect(
        str_contains($header, 'if (!$user) {')
        && str_contains($header, "$public_nav_links = ["),
        'Navigation override remains limited to logged-out users'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Logged-out header merchant-link validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Logged-out header merchant-link validation passed: {$passes} checks.\n";
