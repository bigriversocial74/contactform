<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$pricing = $read('pricing.php');
$pricingCss = $read('assets/css/pricing-local-business-v1.css');
$themeCss = $read('assets/css/public-local-business-theme-v1.css');
$headerCss = $read('assets/css/public-logged-out-header-unified.css');
$header = $read('includes/header-components/public-header.php');
$footerCss = $read('assets/css/universal-footer.css');
$authCss = $read('assets/css/auth-page.css');
$pageDefinitions = $read('includes/page.php');
$footer = $read('includes/footer.php');
$signin = $read('signin.php');
$signup = $read('signup.php');
$forgot = $read('forgot-password.php');
$reset = $read('reset-password.php');
$verify = $read('verify-email.php');

$checks = [
    'shared logged-out public theme exists' => $themeCss !== ''
        && str_contains($themeCss, '--mg-public-navy:#0b2d2a')
        && str_contains($themeCss, '--mg-public-green:#72d43f'),
    'logged-out header imports shared public theme' => str_contains($headerCss, "@import url('/assets/css/public-local-business-theme-v1.css?v=1.0.0')"),
    'header containment beats legacy public shell' => str_contains($headerCss, 'html body[data-authenticated="false"] .mg-site-header.mg-market-universal-header')
        && str_contains($headerCss, 'display:inline-flex!important'),
    'shared public header omits demo actions' => !str_contains($header, 'class="mg-public-demo"')
        && !str_contains($header, '$show_demo_button')
        && !str_contains($header, '$public_demo_href'),
    'header uses white navy green palette' => str_contains($headerCss, 'background:rgba(255,255,255,.96)!important')
        && str_contains($headerCss, 'background:#72d43f!important')
        && str_contains($headerCss, 'color:#0b2d2a!important'),
    'mobile public menu has high-specificity treatment' => str_contains($headerCss, '.mg-public-mobile-menu .mg-public-mobile-panel'),
    'footer uses high-specificity navy theme with white type' => str_contains($footerCss, 'html body[data-authenticated="false"] .mg-site-footer.mg-universal-footer')
        && str_contains($footerCss, 'linear-gradient(135deg,#091a31 0%,#102d4c 100%)!important')
        && str_contains($footerCss, '.mg-footer-column h2{margin:0 0 7px!important;color:#fff!important')
        && str_contains($footerCss, 'a:not(.mg-footer-logo){color:#fff!important')
        && str_contains($footerCss, '.mg-footer-bottom p{margin:0!important;color:#fff!important'),
    'footer content and links remain intact' => str_contains($footer, 'Rewards, tokenized local experiences, and agent-ready gifting tools for local commerce.')
        && str_contains($footer, '<h2>Platform</h2>')
        && str_contains($footer, '<h2>Developers</h2>')
        && str_contains($footer, '<h2>Account</h2>')
        && str_contains($footer, '<h2>Workspace</h2>')
        && str_contains($footer, '/developer-docs.php')
        && str_contains($footer, '/investors.php')
        && str_contains($footer, '/pricing.php'),
    'pricing page uses published package authority' => str_contains($pricing, 'mg_public_pricing_packages()')
        && str_contains($pricing, 'mg_pricing_package_summary()')
        && str_contains($pricing, '$plan[\'limits\'][$key]'),
    'pricing page uses external footer-blue stylesheet' => str_contains($pricing, '/assets/css/pricing-local-business-v1.css?v=1.1.0')
        && !str_contains($pricing, '<style>'),
    'pricing opens directly on published plans without hero' => !str_contains($pricing, 'mg-price-hero')
        && !str_contains($pricing, 'Start small.')
        && str_contains($pricing, '<section class="mg-price-plans"')
        && str_contains($pricing, 'Choose the right operating level for your business.'),
    'pricing uses footer blue actions with white type' => str_contains($pricingCss, '--price-navy:#091a31')
        && str_contains($pricingCss, '--price-blue:#102d4c')
        && str_contains($pricingCss, 'background:var(--price-blue)')
        && str_contains($pricingCss, 'color:#fff!important')
        && !str_contains($pricingCss, '#72d43f')
        && !str_contains($pricingCss, '#4cae24'),
    'pricing includes plan cards and comparison table' => str_contains($pricing, 'mg-price-grid')
        && str_contains($pricing, 'mg-price-table')
        && str_contains($pricing, 'Compare plan capacity'),
    'pricing keeps real signup and sales routes' => str_contains($pricing, '$plan[\'cta_href\']')
        && str_contains($pricing, '/signup.php?type=merchant')
        && str_contains($pricing, '/learn-more.php'),
    'homepage pricing hydrates from published package authority' => str_contains($footer, '$homepage_pricing_plans = mg_public_pricing_packages()')
        && str_contains($footer, 'id="mg-homepage-live-pricing"')
        && str_contains($footer, 'pricingGrid.replaceChildren')
        && str_contains($footer, '$plan[\'price_label\']')
        && str_contains($footer, '$plan[\'cta_href\']'),
    'pricing responsive styles cover desktop tablet and mobile' => str_contains($pricingCss, '@media(max-width:1120px)')
        && str_contains($pricingCss, '@media(max-width:760px)')
        && str_contains($pricingCss, '@media(max-width:430px)'),
    'legacy pricing language and black shell are removed' => !str_contains($pricing, 'Powder your growth')
        && !str_contains($pricing, 'Admin synced source')
        && !str_contains($pricing, 'Package moderation ready')
        && !str_contains($pricing, 'header_gradient_bg.png'),
    'auth family uses one shared blue component' => str_contains($authCss, 'Public auth pages v3')
        && str_contains($authCss, 'linear-gradient(180deg,#fff 0%,#f2f7fd 62%,#fff 100%)')
        && str_contains($authCss, 'background:#102d4c!important')
        && str_contains($authCss, 'color:#0b2540'),
    'signin keeps login authority and Agent redirect' => str_contains($signin, '/api/auth/login.php')
        && str_contains($signin, 'data-success-redirect="/agent.php"')
        && str_contains($signin, 'mg_csrf_field()'),
    'signup keeps customer and package-selection authority' => str_contains($signup, '/api/auth/register.php')
        && str_contains($signup, "['customer', 'merchant']")
        && str_contains($signup, 'name="selected_plan"')
        && str_contains($signup, '/account-subscriptions.php')
        && str_contains($signup, 'mg_csrf_field()'),
    'password recovery endpoints remain unchanged' => str_contains($forgot, '/api/auth/password/forgot.php')
        && str_contains($reset, '/api/auth/password/reset.php')
        && str_contains($forgot, 'mg_csrf_field()')
        && str_contains($reset, 'mg_csrf_field()'),
    'email verification receives auth assets and keeps endpoint' => str_contains($pageDefinitions, "'verify-email' => [")
        && str_contains($pageDefinitions, "'assets'=>\$authAssets")
        && str_contains($verify, '/api/auth/email/verify.php'),
    'auth pages include accessible labels and live statuses' => str_contains($signin, 'aria-labelledby="signin-title"')
        && str_contains($signup, 'aria-labelledby="signup-title"')
        && str_contains($forgot, 'aria-labelledby="forgot-title"')
        && str_contains($reset, 'aria-labelledby="reset-title"')
        && str_contains($verify, 'aria-labelledby="verify-title"')
        && str_contains($signin, 'aria-live="polite"'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Public site local business theme score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Public site local business validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "Public site local business theme contract passed at 10.0/10.\n";