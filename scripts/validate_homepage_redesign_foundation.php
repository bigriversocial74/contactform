<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$indexPath = $root . '/index.php';
$merchantPath = $root . '/merchant-landing.php';

if (!is_file($indexPath) || !is_file($merchantPath)) {
    fwrite(STDERR, "Homepage files are missing.\n");
    exit(1);
}

$index = (string) file_get_contents($indexPath);
$merchant = (string) file_get_contents($merchantPath);
$bundle = (string) file_get_contents($root . '/assets/css/homepage-saas-v1.css');
$coreCss = (string) file_get_contents($root . '/assets/css/homepage-saas-core-v1.css');
$responsiveCss = (string) file_get_contents($root . '/assets/css/homepage-saas-responsive-v1.css');

$checks = [
    'index shared header' => str_contains($index, "require __DIR__ . '/includes/header.php'"),
    'index shared footer' => str_contains($index, "require __DIR__ . '/includes/footer.php'"),
    'index page manifest' => str_contains($index, "'id' => 'homepage-saas'")
        && str_contains($index, "'header_mode' => \$header_mode"),
    'index SaaS homepage shell' => str_contains($index, 'class="mg-home"')
        && str_contains($index, 'data-mg-home-saas-v1')
        && str_contains($index, 'class="mg-home-hero"')
        && str_contains($index, 'class="mg-home-section'),
    'index homepage stylesheet bundle' => str_contains($index, '/assets/css/homepage-saas-v1.css')
        && str_contains($bundle, 'homepage-saas-core-v1.css')
        && str_contains($bundle, 'homepage-saas-visuals-v1.css')
        && str_contains($bundle, 'homepage-saas-sections-v1.css')
        && str_contains($bundle, 'homepage-saas-responsive-v1.css'),
    'index desktop product artwork' => str_contains($index, '/assets/images/home/microgifter-home-desktop-dashboard.svg')
        && is_file($root . '/assets/images/home/microgifter-home-desktop-dashboard.svg'),
    'index phone product artwork' => str_contains($index, '/assets/images/home/microgifter-home-phone.svg')
        && is_file($root . '/assets/images/home/microgifter-home-phone.svg'),
    'index spacious section rhythm' => str_contains($coreCss, 'padding-block: clamp(104px, 11vw, 168px)'),
    'index responsive breakpoints' => str_contains($responsiveCss, '@media (max-width: 980px)')
        && str_contains($responsiveCss, '@media (max-width: 680px)'),
    'index coming soon integration plan' => str_contains($index, 'Coming soon')
        && str_contains($index, 'Gusto')
        && str_contains($index, 'Square')
        && str_contains($index, 'Toast')
        && str_contains($index, 'Other POS Systems'),
    'index standalone CRM showcase removed' => !str_contains($index, 'Build relationships with Microgifter CRM')
        && !str_contains($index, 'id="merchant-crm"')
        && !str_contains($index, 'mg-core-crm'),
    'index legacy parallax removed' => !str_contains($index, 'homepage-parallax-exact-v2')
        && !str_contains($index, 'homepage-core-positioning-v1')
        && !str_contains($index, 'class="hero-scroll"')
        && !str_contains($index, 'data-core-chapter'),
    'index no duplicate document shell' => !str_contains($index, '<!doctype html>') && !str_contains($index, '<body>'),
    'index no duplicate demo header' => !str_contains($index, 'class="os-bar"'),
    'index no duplicate demo footer' => !str_contains($index, 'class="site-footer"'),
    'index no superseded v1 assets' => !str_contains($index, 'homepage-parallax-agent-v1.css') && !str_contains($index, 'homepage-parallax-agent-v1.js'),
    'index no placeholder root' => !str_contains($index, 'data-homepage-redesign-root'),
    'index no merchant shell' => !str_contains($index, 'class="mg-lb-home"'),
    'index no merchant stylesheet' => !str_contains($index, 'homepage-local-business-v1.css'),
    'merchant shell' => str_contains($merchant, 'class="mg-lb-home"'),
    'merchant business section' => str_contains($merchant, 'id="businesses"'),
    'merchant workflow section' => str_contains($merchant, 'id="how-it-works"'),
    'merchant rewards section' => str_contains($merchant, 'id="rewards"'),
    'merchant CRM integration' => str_contains($merchant, 'includes/landing/homepage-crm-integrations.php'),
    'merchant stylesheet' => str_contains($merchant, 'homepage-local-business-v1.css'),
    'merchant shared header' => str_contains($merchant, "require __DIR__ . '/includes/header.php'"),
    'merchant shared footer' => str_contains($merchant, "require __DIR__ . '/includes/footer.php'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Homepage foundation validation failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Homepage foundation validation passed (' . count($checks) . " checks).\n";
