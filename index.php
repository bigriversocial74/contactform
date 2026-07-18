<?php
declare(strict_types=1);

$page_title = 'Microgifter | Customer Relationship Agent for Social Gifting';
$page_section = 'public';
$header_mode = 'public';
$page_body_class = 'mg-parallax-home';
$page_styles = [
    '/assets/css/homepage-parallax-exact-v2/styles.php?v=2.0.0',
];
$page_scripts = [
    '/assets/js/homepage-parallax-exact-v2/app.php?v=2.0.0',
];
$page_meta = [
    'description' => 'Create a personal social gifting and customer service agent that connects gifting, loyalty, service, follow-up, and post-purchase commerce.',
    'canonical' => 'https://microgifter.com/index.php',
    'og_title' => 'Microgifter — Customer Relationship Agent',
    'og_description' => 'One intelligent relationship system for social gifting, customer service, loyalty, and post-purchase commerce.',
    'og_image' => 'https://microgifter.com/assets/images/homepage-parallax-exact-v2/image.php?name=mountains',
];
$page_manifest = [
    'id' => 'index',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'How It Works', 'href' => '/index.php#relationship-system'],
            ['label' => 'Solutions', 'href' => '/index.php#agent-in-action'],
            ['label' => 'Features', 'href' => '/index.php#pppm-presentation'],
            ['label' => 'For Businesses', 'href' => '/merchant-landing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'home',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';

$homepagePayloadPath = __DIR__ . '/includes/landing/homepage-parallax-exact-v2/page.html.gz.b64';
$homepagePayload = is_file($homepagePayloadPath) ? trim((string) file_get_contents($homepagePayloadPath)) : '';
$homepageMarkup = $homepagePayload !== '' ? gzdecode((string) base64_decode($homepagePayload, true)) : false;
if (!is_string($homepageMarkup) || $homepageMarkup === '') {
    http_response_code(500);
    echo '<main><p>Homepage presentation is temporarily unavailable.</p>';
} else {
    echo $homepageMarkup;
}

require __DIR__ . '/includes/footer.php';
