<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/public-donations-public.php';

$campaignRef = mg_public_donations_public_ref(
    $_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? ''
);
$publicDonationsPayload = null;
$publicDonationsUnavailable = '';

try {
    if ($campaignRef !== '') {
        $publicDonationsPayload = mg_public_donations_public_payload(mg_db(), $campaignRef);
    }
} catch (RuntimeException $error) {
    $publicDonationsUnavailable = 'Public Donations reporting is not available yet.';
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'public_donations.public_campaign.page_failure', 'Unable to load Public Donations campaign page.', [
            'campaign_ref' => $campaignRef,
            'exception_class' => $error::class,
        ]);
    }
    $publicDonationsUnavailable = 'Public Donations campaign not available.';
}

$seo = is_array($publicDonationsPayload['seo'] ?? null) ? $publicDonationsPayload['seo'] : [];
if (!$publicDonationsPayload) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
} elseif ((string)($seo['robots'] ?? '') === 'noindex,nofollow') {
    header('X-Robots-Tag: noindex, nofollow');
}

$page_title = (string)($seo['title'] ?? 'Public Donations | Microgifter');
$page_section = 'campaign';
$header_mode = 'public';
$page_body_class = 'mg-public-donations-page';
$page_styles = ['/assets/css/public-donations-public-v1.css?v=1.0.0'];
$page_scripts = [];
$page_meta = [
    'description' => (string)($seo['description'] ?? 'Merchant-directed Community reward support powered by Microgifter.'),
    'canonical' => (string)($seo['canonical'] ?? ''),
    'og_title' => (string)($seo['title'] ?? $page_title),
    'og_description' => (string)($seo['description'] ?? ''),
    'og_image' => (string)($seo['image_url'] ?? ''),
    'robots' => (string)($seo['robots'] ?? 'noindex,nofollow'),
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-donations-public-view.php';
require __DIR__ . '/includes/footer.php';
