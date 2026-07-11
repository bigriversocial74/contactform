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
    $controller = $read('assets/js/gift-action-center-user-search-fix.js');
    $styles = $read('assets/css/gift-action-center-user-search-fix.css');
    $redirect = $read('user-profile.php');

    $expect(
        str_contains($view, 'gift-action-center-user-search-fix.css?v=1.0.0')
        && str_contains($view, 'gift-action-center-user-search-fix.js?v=1.0.0'),
        'Gift center loads cache-bumped user search correction assets'
    );

    $expect(
        str_contains($controller, "data-user-search-profile-link")
        && str_contains($controller, "'/profile.php?slug='")
        && str_contains($controller, "'/user-profile.php?user='"),
        'Search result names are upgraded to canonical profile links'
    );

    $expect(
        str_contains($controller, "event.stopImmediatePropagation()")
        && str_contains($controller, "window.location.href = profileLink.href"),
        'Profile links bypass the legacy row-selection click handler'
    );

    $expect(
        str_contains($controller, 'followingFromResponse(response, requestedFollowing)')
        && str_contains($controller, "candidate.dataset.following = following ? 'true' : 'false'")
        && str_contains($controller, "candidate.setAttribute('aria-pressed'"),
        'Follow buttons reconcile visible and accessible state after the relationship request'
    );

    $expect(
        str_contains($controller, "searchInput.dispatchEvent(new Event('input', { bubbles: true }))"),
        'Successful follow changes refresh search data from the server'
    );

    $expect(
        str_contains($styles, '.mg-user-search-profile-link')
        && str_contains($styles, '.mg-user-search-action.is-following'),
        'Profile links and Following state have explicit visual styling'
    );

    $expect(
        str_contains($redirect, "SELECT pp.slug")
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
