<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$cookiePage = $read('cookies.php');
$consentMarkup = $read('includes/cookie-consent.php');
$consentJs = $read('assets/js/cookie-consent.js');
$consentCss = $read('assets/css/cookie-consent.css');
$footer = $read('includes/footer.php');
$routePolicy = $read('config/security-route-policy.php');
$privacy = $read('privacy.php');
$terms = $read('terms.php');
$docs = $read('docs/privacy/cookie-consent-integration-v1.md');
$index = $read('index.php');
$publicHeader = $read('includes/header-components/public-header.php');

$optionalPrechecked = preg_match(
    '/<input[^>]+data-mg-consent-category="(?:functional|analytics|marketing|external_media)"[^>]*\bchecked\b/i',
    $consentMarkup
) === 1;

$knownTrackerNeedles = [
    'googletagmanager.com',
    'google-analytics.com',
    'connect.facebook.net',
    'facebook.com/tr',
    'analytics.tiktok.com',
    'snap.licdn.com',
];
$globalSurfaces = $footer . "\n" . $publicHeader . "\n" . $index;
$unguardedGlobalTracker = false;
foreach ($knownTrackerNeedles as $needle) {
    if (stripos($globalSurfaces, $needle) !== false && !str_contains($globalSurfaces, 'data-mg-consent')) {
        $unguardedGlobalTracker = true;
        break;
    }
}

$checks = [
    'public Cookie Policy page exists' => str_contains($cookiePage, "'id' => 'cookies'")
        && str_contains($cookiePage, 'Cookie Policy')
        && str_contains($cookiePage, '/assets/css/legal-pages.css?v=1.0.0'),
    'Cookie Policy route is public' => str_contains($routePolicy, "'privacy.php','terms.php','cookies.php'"),
    'shared footer loads consent assets and interface' => str_contains($footer, '/assets/css/cookie-consent.css?v=1.0.0')
        && str_contains($footer, '/assets/js/cookie-consent.js?v=1.0.0')
        && str_contains($footer, "require __DIR__ . '/cookie-consent.php'")
        && str_contains($footer, '<a href="/cookies.php">Cookies</a>')
        && str_contains($footer, 'data-mg-cookie-settings'),
    'first layer provides accept reject and preferences' => str_contains($consentMarkup, 'data-mg-consent-action="accept"')
        && str_contains($consentMarkup, 'data-mg-consent-action="reject"')
        && str_contains($consentMarkup, 'data-mg-consent-action="settings"')
        && str_contains($consentMarkup, 'Accept all')
        && str_contains($consentMarkup, 'Reject non-essential')
        && str_contains($consentMarkup, 'Manage preferences'),
    'accept and reject share equal choice styling' => substr_count($consentMarkup, 'mg-cookie-consent__button--choice') >= 4
        && str_contains($consentCss, '.mg-cookie-consent__button--choice')
        && !str_contains($consentCss, '.mg-cookie-consent__button--accept'),
    'optional categories are not preselected' => !$optionalPrechecked
        && str_contains($consentMarkup, 'data-mg-consent-category="functional"')
        && str_contains($consentMarkup, 'data-mg-consent-category="analytics"')
        && str_contains($consentMarkup, 'data-mg-consent-category="marketing"')
        && str_contains($consentMarkup, 'data-mg-consent-category="external_media"'),
    'necessary category is locked active' => str_contains($consentMarkup, 'checked disabled data-mg-consent-category="necessary"')
        && str_contains($consentMarkup, 'Always active'),
    'runtime defaults every optional category off' => str_contains($consentJs, 'functional: false')
        && str_contains($consentJs, 'analytics: false')
        && str_contains($consentJs, 'marketing: false')
        && str_contains($consentJs, 'external_media: false')
        && str_contains($consentJs, "if (category === 'necessary') return true"),
    'consent receipt has version id timestamps and categories' => str_contains($consentJs, "var STORAGE_KEY = 'mg_cookie_consent_v1'")
        && str_contains($consentJs, "var policyVersion = '2026-07-23.1'")
        && str_contains($consentJs, 'id: previous && previous.id')
        && str_contains($consentJs, 'decided_at:')
        && str_contains($consentJs, 'updated_at:')
        && str_contains($consentJs, 'categories: normalized'),
    'consent is first party and expires after 180 days' => str_contains($consentJs, 'COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 180')
        && str_contains($consentJs, '; Path=/; Max-Age=')
        && str_contains($consentJs, '; SameSite=Lax')
        && str_contains($consentJs, "window.location.protocol === 'https:' ? '; Secure' : ''"),
    'runtime activates only categorized deferred content' => str_contains($consentJs, "querySelectorAll('[data-mg-consent]')")
        && str_contains($consentJs, "node.getAttribute('data-mg-consent')")
        && str_contains($consentJs, 'if (CATEGORIES.indexOf(category) === -1 || !hasConsent(category))')
        && str_contains($consentJs, "node.getAttribute('data-src')")
        && str_contains($consentJs, 'node.parentNode.replaceChild(script, node)'),
    'withdrawal clears known identifiers and reloads' => str_contains($consentJs, 'clearCategoryStorage(category)')
        && str_contains($consentJs, 'window.location.reload()')
        && str_contains($consentJs, 'previous.categories[category] === true')
        && str_contains($consentJs, 'normalized[category] !== true'),
    'preference manager is reusable and accessible' => str_contains($consentMarkup, 'role="dialog"')
        && str_contains($consentMarkup, 'aria-modal="true"')
        && str_contains($consentMarkup, 'aria-live="polite"')
        && str_contains($consentJs, "event.key === 'Escape'")
        && str_contains($consentJs, "event.key !== 'Tab'")
        && str_contains($consentJs, 'window.MicrogifterConsent'),
    'consent change events are available' => str_contains($consentJs, "dispatch('mg:consent-ready'")
        && str_contains($consentJs, "dispatch('mg:consent-changed'"),
    'Cookie Policy documents all five categories' => str_contains($cookiePage, 'Strictly necessary')
        && str_contains($cookiePage, 'Functional')
        && str_contains($cookiePage, 'Analytics')
        && str_contains($cookiePage, 'Marketing')
        && str_contains($cookiePage, 'External media'),
    'Cookie Policy includes current inventory and no-sale commitment' => str_contains($cookiePage, '<code>mg_session</code>')
        && str_contains($cookiePage, '<code>mg_cookie_consent_v1</code>')
        && str_contains($cookiePage, 'No site-wide analytics, marketing pixel, or external-media tracker is enabled globally at publication.')
        && str_contains($cookiePage, 'Microgifter does not sell customer data, Merchant Data, or cookie-derived personal information'),
    'Cookie Policy supports withdrawal' => substr_count($cookiePage, 'data-mg-cookie-settings') >= 2
        && str_contains($cookiePage, 'change or withdraw')
        && str_contains($cookiePage, 'up to 180 days'),
    'legal pages remain available together' => $privacy !== '' && $terms !== ''
        && str_contains($cookiePage, 'href="/privacy.php"')
        && str_contains($cookiePage, 'href="/terms.php"'),
    'responsive and reduced-motion styles exist' => str_contains($consentCss, '@media(max-width:960px)')
        && str_contains($consentCss, '@media(max-width:680px)')
        && str_contains($consentCss, '@media(prefers-reduced-motion:reduce)')
        && str_contains($consentCss, ':focus-visible'),
    'developer integration contract exists' => str_contains($docs, 'data-mg-consent="analytics"')
        && str_contains($docs, 'type="text/plain"')
        && str_contains($docs, 'data-mg-consent="external_media"')
        && str_contains($docs, 'Optional categories default to `false`.'),
    'no known unguarded tracker is added globally' => !$unguardedGlobalTracker,
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 100);
echo 'Cookie consent compliance score: ' . $score . '/100' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Cookie consent validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Cookie consent compliance contract passed at 100/100.\n";
