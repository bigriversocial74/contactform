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
$headerCss = $read('assets/css/public-logged-out-header-unified.css');
$header = $read('includes/header-components/public-header.php');
$loggedInHeader = $read('includes/header-templates/logged-in.php');
$footer = $read('includes/footer.php');
$homepageJs = $read('assets/js/homepage-parallax-exact-v2.js');
$signin = $read('signin.php');
$signup = $read('signup.php');
$forgot = $read('forgot-password.php');
$reset = $read('reset-password.php');

$checks = [
    'shared public pricing renderer exists' => str_contains($pricingCards, 'function mg_render_public_pricing_cards(array $options = []): void'),
    'renderer uses published package authority' => str_contains($pricingCards, '$plans = mg_public_pricing_packages()')
        && str_contains($pricingCards, '$plan[\'name\']')
        && str_contains($pricingCards, '$plan[\'price_label\']')
        && str_contains($pricingCards, '$plan[\'billing_label\']')
        && str_contains($pricingCards, '$plan[\'description\']')
        && str_contains($pricingCards, '$plan[\'included_features\']')
        && str_contains($pricingCards, '$plan[\'excluded_features\']')
        && str_contains($pricingCards, '$plan[\'cta_label\']')
        && str_contains($pricingCards, '$plan[\'cta_href\']'),
    'package source retains limits and AI allowances' => str_contains($packages, 'function mg_public_pricing_packages(): array')
        && str_contains($packages, '\'ai_tokens_monthly_included\'')
        && str_contains($packages, '\'monthly_stamps_included\'')
        && str_contains($packages, '\'max_active_campaigns\''),
    'pricing and homepage require shared renderer' => str_contains($pricing, "require_once __DIR__ . '/includes/pricing-cards.php'")
        && str_contains($index, "require_once __DIR__ . '/includes/pricing-cards.php'"),
    'pricing and homepage call identical renderer' => str_contains($pricing, 'mg_render_public_pricing_cards();')
        && str_contains($index, 'mg_render_public_pricing_cards('),
    'pricing page still uses package authority for comparison' => str_contains($pricing, '$plans = mg_public_pricing_packages()')
        && str_contains($pricing, '$plan[\'limits\'][$key]')
        && str_contains($pricing, 'Monthly AI Tokens'),
    'homepage has no hard-coded duplicate package cards' => !str_contains($index, '$25<span>/month</span>')
        && !str_contains($index, '$79<span>/month</span>')
        && !str_contains($index, '$149<span>/month</span>')
        && !str_contains($index, 'pricing-card__label')
        && !str_contains($index, 'Choose Professional'),
    'footer pricing hydration is removed' => !str_contains($footer, '$homepage_pricing_plans')
        && !str_contains($footer, 'mg-homepage-live-pricing')
        && !str_contains($footer, 'pricingGrid.replaceChildren')
        && !str_contains($footer, 'removedLinks'),
    'pricing surfaces load canonical stylesheet' => str_contains($pricing, '/assets/css/pricing-local-business-v1.css?v=1.2.0')
        && str_contains($index, '/assets/css/pricing-local-business-v1.css?v=1.2.0'),
    'pricing CTA default is footer blue with white text' => str_contains($pricingCss, '.mg-pricing-v1 a.mg-price-plan-action,.mg-pricing-v1 a.mg-price-button')
        && str_contains($pricingCss, 'background:var(--price-blue)!important')
        && str_contains($pricingCss, 'color:#fff!important')
        && str_contains($pricingCss, '-webkit-text-fill-color:#fff'),
    'pricing CTA interaction states remain white' => str_contains($pricingCss, 'a.mg-price-plan-action:hover')
        && str_contains($pricingCss, 'a.mg-price-plan-action:focus')
        && str_contains($pricingCss, 'a.mg-price-plan-action:focus-visible')
        && str_contains($pricingCss, 'a.mg-price-plan-action:active')
        && str_contains($pricingCss, 'a.mg-price-plan-action:visited')
        && str_contains($pricingCss, 'a.mg-price-button:visited')
        && str_contains($pricingCss, 'outline:3px solid #8db8df'),
    'pricing CTA CSS contains no green treatment' => !str_contains($pricingCss, '#72d43f')
        && !str_contains($pricingCss, '#4cae24')
        && !str_contains($pricingCss, 'green'),
    'logged-out header removes business and case study links' => !str_contains($header, "['label' => 'For Businesses'")
        && !str_contains($header, "['label' => 'Case Studies'")
        && !str_contains($header, '/merchant-landing.php')
        && !str_contains($header, '/featured-case-studies.php'),
    'desktop and mobile public nav share cleaned link collection' => substr_count($header, 'foreach ($public_nav_links as $public_header_link)') >= 2,
    'logged-in navigation remains separately rendered' => $loggedInHeader !== ''
        && str_contains($header, "require dirname(__DIR__) . '/header-templates/logged-in.php'"),
    'public mobile header styling remains present' => str_contains($headerCss, '.mg-public-mobile-menu .mg-public-mobile-panel'),
    'pricing page remains plans-first without opening hero' => !str_contains($pricing, 'mg-price-hero')
        && str_contains($pricing, '<section class="mg-price-plans"')
        && str_contains($pricing, 'Choose the right operating level for your business.'),
    'comparison table and public sales paths remain' => str_contains($pricing, 'mg-price-table')
        && str_contains($packages, '/signup.php?plan=starter')
        && str_contains($packages, '/signup.php?plan=growth')
        && str_contains($packages, '/signup.php?plan=pro')
        && str_contains($packages, '/learn-more.php?plan=enterprise'),
    'pricing responsive breakpoints remain' => str_contains($pricingCss, '@media(max-width:1120px)')
        && str_contains($pricingCss, '@media(max-width:760px)')
        && str_contains($pricingCss, '@media(max-width:430px)'),
    'homepage animated and sticky sections remain wired' => str_contains($index, 'class="hero-scroll"')
        && str_contains($index, 'class="hero-sticky"')
        && str_contains($index, 'class="story story-scroll"')
        && str_contains($index, 'class="pppm-event"')
        && str_contains($index, 'The Future of Gifting Has Arrived.')
        && str_contains($homepageJs, 'requestAnimationFrame'),
    'sign-in and registration contracts remain intact' => str_contains($signin, '/api/auth/login.php')
        && str_contains($signin, 'data-success-redirect="/agent.php"')
        && str_contains($signup, '/api/auth/register.php')
        && str_contains($signup, 'name="selected_plan"'),
    'password recovery contracts remain intact' => str_contains($forgot, '/api/auth/password/forgot.php')
        && str_contains($reset, '/api/auth/password/reset.php'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Public pricing and navigation score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Public pricing and navigation validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Public pricing and navigation contract passed at 10.0/10.\n";
