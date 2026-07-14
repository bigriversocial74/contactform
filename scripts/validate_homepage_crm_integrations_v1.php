<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

$required = [
    'index.php' => [
        '/assets/css/homepage-crm-integrations-v1.css?v=1.0.0',
        "require __DIR__ . '/includes/landing/homepage-crm-integrations.php'",
    ],
    'includes/landing/homepage-crm-integrations.php' => [
        'id="integrations"',
        'Bring your customer relationships with you.',
        'Squarespace',
        'WooCommerce',
        'Shopify',
        'Square',
        'HubSpot',
        'Mailchimp',
        'Klaviyo',
        'Consent is never invented',
        'Identity links survive email changes',
        'Sensitive location data stays out',
        'href="/signup.php"',
        'href="/learn-more.php"',
    ],
    'assets/css/homepage-crm-integrations-v1.css' => [
        'body[data-page-id="index"] .mg-lb-integrations',
        '.mg-lb-integrations-layout',
        '.mg-lb-provider-cloud',
        '.mg-lb-integration-hub',
        '.mg-lb-integration-rules',
        '@media(max-width:1180px)',
        '@media(max-width:820px)',
        '@media(max-width:620px)',
        '@media(prefers-reduced-motion:reduce)',
    ],
];

foreach ($required as $path => $markers) {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        $errors[] = "Missing {$path}";
        continue;
    }
    $content = (string) file_get_contents($file);
    foreach ($markers as $marker) {
        $checks++;
        if (!str_contains($content, $marker)) {
            $errors[] = "{$path} missing marker: {$marker}";
        }
    }
}

$sectionPath = $root . '/includes/landing/homepage-crm-integrations.php';
if (is_file($sectionPath)) {
    $section = (string) file_get_contents($sectionPath);
    $providerNames = ['Squarespace', 'WooCommerce', 'Shopify', 'Square', 'HubSpot', 'Mailchimp', 'Klaviyo'];
    foreach ($providerNames as $provider) {
        $checks++;
        if (substr_count($section, $provider) < 2) {
            $errors[] = "Provider {$provider} must appear in the integration map and provider strip.";
        }
    }
    $checks++;
    if (substr_count($section, '<section') !== 1 || substr_count($section, '</section>') !== 1) {
        $errors[] = 'Homepage CRM integrations include must own exactly one top-level section.';
    }
}

$indexPath = $root . '/index.php';
if (is_file($indexPath)) {
    $index = (string) file_get_contents($indexPath);
    $integrationPosition = strpos($index, 'homepage-crm-integrations.php');
    $platformPosition = strpos($index, 'mg-lb-platform');
    $workflowPosition = strpos($index, 'mg-lb-workflow');
    $checks++;
    if ($integrationPosition === false || $platformPosition === false || $workflowPosition === false || !($platformPosition < $integrationPosition && $integrationPosition < $workflowPosition)) {
        $errors[] = 'CRM integrations section must render between the platform and workflow sections.';
    }
}

if ($errors) {
    fwrite(STDERR, "Homepage CRM Integrations v1 validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Homepage CRM Integrations v1: {$checks} contract checks passed.\n";
