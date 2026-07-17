<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
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
    $view = $read('includes/gift-action-center.php');
    $controller = $read('assets/js/gift-action-center-user-search-v2.js');
    $styles = $read('assets/css/gift-action-center-user-search-fix.css');
    $redirect = $read('user-profile.php');

    $expect(
        str_contains($view, 'gift-action-center-user-search-v2.js?v=2.0.0')
        && !str_contains($view, 'gift-action-center-user-search-fix.js')
        && !str_contains($view, 'gift-action-center-user-search.js"'),
        'Gift center loads one cache-bumped user search runtime'
    );

    $expect(
        str_contains($controller, 'dataUserSearchProfileLink') === false
        && str_contains($controller, "dataset.userSearchProfileLink")
        && str_contains($controller, "'/profile.php?slug='")
        && str_contains($controller, "'/user-profile.php?user='"),
        'Search result names link to canonical profile routes'
    );

    $expect(
        str_contains($controller, "button.setAttribute('aria-pressed'")
        && str_contains($controller, "button.classList.toggle('is-following'")
        && str_contains($controller, "data.relationship && data.relationship.following"),
        'Follow buttons reconcile visible and accessible state from the relationship response'
    );

    $expect(
        str_contains($controller, "Microgifter.post('/api/social/relationship.php'")
        && str_contains($controller, "window.setTimeout(searchUsers, 120)"),
        'Follow changes use the canonical relationship endpoint and refresh search data'
    );

    $expect(
        str_contains($controller, "window.location.href = '/feed.php?chat='")
        && str_contains($controller, 'MicrogifterActionCenterUserSearch'),
        'User search exposes one message route and one runtime API'
    );

    $expect(
        str_contains($styles, '.mg-user-search-profile-link')
        && str_contains($styles, '.mg-user-search-action.is-following'),
        'Profile links and Following state retain explicit visual styling'
    );

    $expect(
        str_contains($redirect, 'SELECT pp.slug')
        && str_contains($redirect, "pp.visibility IN ('public','unlisted')")
        && str_contains($redirect, "'/profile.php?slug='"),
        'User reference redirect resolves only active visible profiles to the canonical profile page'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Inbox user search validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Inbox user search validation passed: {$passes} checks.\n";
