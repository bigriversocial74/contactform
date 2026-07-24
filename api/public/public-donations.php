<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-public.php';

mg_require_method('GET');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

$campaignRef = mg_public_donations_public_ref(
    $_GET['campaign'] ?? $_GET['c'] ?? $_GET['slug'] ?? $_GET['id'] ?? ''
);
if ($campaignRef === '') {
    mg_fail('Public Donations campaign not found.', 404);
}

try {
    $payload = mg_public_donations_public_payload(mg_db(), $campaignRef);
    if (!$payload) {
        mg_fail('Public Donations campaign not found.', 404);
    }

    mg_ok([
        'campaign' => $payload['campaign'],
        'merchant' => $payload['merchant'],
        'reward' => $payload['reward'],
        'impact' => $payload['impact'],
        'community_accounts' => $payload['community_accounts'],
        'governance' => $payload['governance'],
        'privacy' => $payload['privacy'],
        'seo' => $payload['seo'],
        'generated_at' => $payload['generated_at'],
    ], 'Public Donations campaign loaded.');
} catch (RuntimeException $error) {
    mg_fail('Public Donations reporting is not available yet.', 503);
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'public_donations.public_campaign.api_failure',
        'Public Donations campaign not available.',
        404,
        ['campaign_ref' => $campaignRef]
    );
}
