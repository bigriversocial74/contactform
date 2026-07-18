<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$index = $read('index.php');
$pricing = $read('pricing.php');
$packages = $read('includes/pricing-packages.php');
$pricingCards = $read('includes/pricing-cards.php');
$pricingCss = $read('assets/css/pricing-local-business-v1.css');
$themeCss = $read('assets/css/public-local-business-theme-v1.css');
$headerCss = $read('assets/css/public-logged-out-header-unified.css');
$header = $read('includes/header-components/public-header.php');
$loggedInHeader = $read('includes/header-templates/logged-in.php');
$footerCss = $read('assets/css/universal-footer.css');
$authCss = $read('assets/css/auth-page.css');
$pageDefinitions = $read('includes/page.php');
$footer = $read('includes/footer.php');
$homepageJs = $read('assets/js/homepage-parallax-exact-v2.js');
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
    'logged-out navigation removes business and case study links globally' => !str_contains($header, "['label' => 'For Businesses'")
        && !str_contains($header, "['label' => 'Case Studies'")
        && !str_contains($header, '/merchant-landing.php')
        && !str_contains($header, '/featured-case-studies.php'),
    'desktop and mobile public navigation use the same cleaned source' => str_contains($header, 'foreach ($public_nav_links as $public_header_link)')
        && substr_count($header, 'foreach ($public_nav_links as $public_header_link)') >= 2,
    'authenticated navigation remains separate and unchanged in source' => $loggedInHeader !== ''
        && str_contains($header, "require dirname(__DIR__) . '/header-templates/logged-in.php'"),
    'footer uses high-specificity navy theme with white type' => str_contains($footerCss, 'html body[data-authenticated="false"] .mg-site-footer.mg-universal-footer')
        && str_contains($footerCss, 'linear-gradient(135deg,#091a31 0%,#102d4c 100%)!important')
        && str_contains($footerCss, 'a:not(.mg-footer-logo){color:#fff!important'),
    'footer content and links remain intact' => str_contains($footer, 'Rewards, tokenized local experiences, and agent-ready gifting tools for local commerce.')
        && str_contains($footer, '<h2>Platform</h2>')
        && str_contains($footer, '<h2>Developers</h2>')
        && str_contains($footer, '<h2>Account</h2>')
        && str_contains($footer, '<h2>Workspace</h2>'),
    'published package authority remains canonical' => str_contains($packages, 'function mg_public_pricing_packages(): array')
        && str_contains($packages, "'ai_tokens_monthly_included'")
        && str_contains($packages, "'cta_href'"),
    'shared pricing renderer uses published package authority' => str_contains($pricingCards, 'function mg_render_public_pricing_cards(array $options = []): void')
        && str_contains($pricingCards, '$plans = mg_public_pricing_packages()')
        && str_contains($pricingCards, "$plan['included_features']")
        && str_contains($pricingCards, "$plan['excluded_features']")
        && str_contains($pricingCards, "$plan['cta_href']")
        && str_contains($pricingCards, "$plan['limits']") === false,
    'pricing and homepage call the same shared renderer' => str_contains($pricing, "require_once __DIR__ . '/includes/pricing-cards.php'")
        && str_contains($index, "require_once __DIR__ . '/includes/pricing-cards.php'")
        && str_contains($pricing, 'mg_render_public_pricing_cards();')
        && str_contains($index, 'mg_render_public_pricing_cards('),
    'both pricing surfaces are backed by the published package function' => str_contains($pricing, '$plans = mg_public_pricing_packages()')
        && str_contains($pricingCards, '$plans = mg_public_pricing_packages()'),
    'homepage contains no duplicate hard-coded package values' => !str_contains($index, '$25<span>/month</span>')
        && !str_contains($index, '$79<span>/month</span>')
        && !str_contains($index, '$149<span>/month</span>')
        && !str_contains($index, 'pricing-card__label')
        && !str_contains($index, 'Choose Professional'),
    'footer pricing hydration and link cleanup are removed' => !str_contains($footer, '$homepage_pricing_plans')
        && !str_contains($footer, 'mg-homepage-live-pricing')
        && !str_contains($footer, 'pricingGrid.replaceChildren')
        && !str_contains($footer, 'removedLinks'),
    'pricing page uses external footer-blue stylesheet' => str_contains($pricing, '/assets/css/pricing-local-business-v1.css?v=1.2.0')
        && str_contains($index, '/assets/css/pricing-local-business-v1.css?v=1.2.0')
        && !str_contains($pricing, '<style>'),
    'pricing opens directly on published plans without hero' => !str_contains($pricing, 'mg-price-hero')
        && !str_contains($pricing, 'Start small.')
        && str_contains($pricing, '<section class="mg-price-plans"')
        && str_contains($pricing, 'Choose the right operating level for your business.'),
    'pricing CTA states always use footer blue and white type' => str_contains($pricingCss, '--price-navy:#091a31')
        && str_contains($pricingCss, '--price-blue:#102d4c')
        && str_contains($pricingCss, 'a.mg-price-plan-action:visited')
        && str_contains($pricingCss, 'a.mg-price-plan-action:focus-visible')
        && str_contains($pricingCss, 'a.mg-price-plan-action:active')
        && str_contains($pricingCss, 'a.mg-price-button:visited')
        && str_contains($pricingCss, '-webkit-text-fill-color:#fff')
        && str_contains($pricingCss, 'outline:3px solid #8db8df')
        && !str_contains($pricingCss, '#72d43f')
        && !str_contains($pricingCss, '#4cae24'),
    'pricing includes canonical cards and comparison table' => str_contains($pricingCards, 'mg-price-card')
        && str_contains($pricing, 'mg-price-table')
        && str_contains($pricing, 'Compare plan capacity'),
    'pricing keeps real signup and sales routes through package authority' => str_contains($packages, '/signup.php?plan=starter')
        && str_contains($packages, '/signup.php?plan=growth')
        && str_contains($packages, '/signup.php?plan=pro')
        && str_contains($packages, '/learn-more.php?plan=enterprise'),
    'pricing responsive styles cover desktop tablet and mobile' => str_contains($pricingCss, '@media(max-width:1120px)')
        && str_contains($pricingCss, '@media(max-width:760px)')
        && str_contains($pricingCss, '@media(max-width:430px)'),
    'homepage animations and sticky chapters remain wired' => str_contains($index, 'class="hero-scroll"')
        && str_contains($index, 'class="hero-sticky"')
        && str_contains($index, 'class="story story-scroll"')
        && str_contains($index, 'class="pppm-event"')
        && str_contains($index, 'The Future of Gifting Has Arrived.')
        && str_contains($homepageJs, 'requestAnimationFrame'),
    'auth family uses one shared blue component' => str_contains($authCss, 'Public auth pages v3')
        && str_contains($authCss, 'background:#102d4c!important'),
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
