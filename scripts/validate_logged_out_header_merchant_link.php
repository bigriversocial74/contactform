<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$headerPath = $root . '/includes/header-components/public-header.php';
$loggedInPath = $root . '/includes/header-templates/logged-in.php';
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
    $loggedIn = is_file($loggedInPath) ? file_get_contents($loggedInPath) : false;
    if (!is_string($header)) {
        throw new RuntimeException('Unable to read shared public header component.');
    }

    $expect(
        !str_contains($header, "['label' => 'For Businesses'")
        && !str_contains($header, '/merchant-landing.php'),
        'Logged-out navigation excludes For Businesses'
    );
    $expect(
        !str_contains($header, "['label' => 'Case Studies'")
        && !str_contains($header, '/featured-case-studies.php'),
        'Logged-out navigation excludes Case Studies'
    );
    $expect(
        str_contains($header, "['label' => 'Blog', 'href' => '/blog.php']")
        && str_contains($header, "['label' => 'Pricing', 'href' => '/pricing.php']"),
        'Remaining logged-out navigation links stay intact'
    );
    $expect(
        substr_count($header, 'foreach ($public_nav_links as $public_header_link)') >= 2,
        'Desktop and mobile navigation share the same cleaned link source'
    );
    $expect(
        str_contains($header, 'data-header-template="logged-out-public"')
        && str_contains($header, 'class="mg-public-mobile-nav"'),
        'Desktop and mobile logged-out header templates remain present'
    );
    $expect(
        str_contains($header, 'if (!$user) {')
        && str_contains($header, '$public_nav_links = ['),
        'Navigation override remains limited to logged-out users'
    );
    $expect(
        is_string($loggedIn)
        && str_contains($header, "require dirname(__DIR__) . '/header-templates/logged-in.php'"),
        'Authenticated navigation remains separately rendered'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Logged-out header navigation validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Logged-out header navigation validation passed: {$passes} checks.\n";
