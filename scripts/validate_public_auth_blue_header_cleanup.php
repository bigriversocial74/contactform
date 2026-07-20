<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $content;
};

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
    $header = $read('includes/header-components/public-header.php');
    $authCss = $read('assets/css/auth-page.css');
    $page = $read('includes/page.php');
    $signin = $read('signin.php');

    $expect(
        !str_contains($header, 'class="mg-public-demo"')
        && !str_contains($header, '$show_demo_button')
        && !str_contains($header, '$public_demo_href'),
        'Desktop Book A Demo header action is removed globally'
    );

    preg_match('/<nav class="mg-public-mobile-nav"[^>]*>(.*?)<\/nav>/s', $header, $mobileNavMatch);
    $mobileNav = (string) ($mobileNavMatch[1] ?? '');
    $expect(
        $mobileNav !== '' && !str_contains($mobileNav, 'Book A Demo') && !str_contains($mobileNav, 'Book Demo'),
        'Mobile public navigation no longer renders a demo action'
    );

    $expect(
        str_contains($authCss, "/assets/images/mountains.png?v=2.0.0")
        && str_contains($authCss, "/assets/images/foreground.png?v=2.0.0")
        && !str_contains($authCss, 'rgba(96,165,250,.16)')
        && !str_contains($authCss, 'linear-gradient(180deg,#fff 0%,#f2f7fd 62%,#fff 100%)'),
        'Auth page background uses the homepage mountain and foreground artwork without the retired gradient'
    );

    $expect(
        str_contains($authCss, 'font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif')
        && str_contains($authCss, 'font:inherit'),
        'Auth pages and form controls use the homepage Inter typography'
    );

    $expect(
        str_contains($authCss, 'background:#102d4c!important')
        && str_contains($authCss, 'border:1px solid #102d4c!important')
        && str_contains($authCss, 'color:#fff!important'),
        'Auth primary button uses the footer navy color'
    );

    foreach (['#72d43f', '#4cae24', '#82df50', '#326f1d', '#245515', '#0b2d2a'] as $greenToken) {
        $expect(
            !str_contains(strtolower($authCss), strtolower($greenToken)),
            'Legacy green auth token removed: ' . $greenToken
        );
    }

    $expect(
        str_contains($page, "'auth-pages'=>['styles'=>['/assets/css/auth-page.css?v=3.0.0']]")
        && str_contains($signin, 'class="mg-auth-shell"'),
        'Auth pages load the cache-busted shared stylesheet without changing the sign-in structure'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Public auth blue/header cleanup validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Public auth blue/header cleanup validation passed: {$passes} checks.\n";
