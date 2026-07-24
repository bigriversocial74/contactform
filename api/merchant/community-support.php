<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-community-support.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method !== 'GET') {
    mg_fail('Method not allowed.', 405);
}
if (!mg_public_donations_is_enabled_for($merchantId, $user)) {
    mg_fail('Public Donations Community Support is not enabled for this merchant.', 403);
}

try {
    if (!mg_community_support_schema_ready($pdo)) {
        mg_ok([
            'schema_ready' => false,
            'summary' => [],
            'attention' => [],
            'campaigns' => [],
            'community_accounts' => [],
            'donation_batches' => [],
            'activity' => [],
            'privacy' => [
                'original_community_accounts_only' => true,
                'downstream_recipient_identity_exposed' => false,
                'merchant_scoped' => true,
            ],
        ], 'Import the Public Donations Community installer to enable Community Support reporting.');
    }

    $dashboard = mg_community_support_dashboard($pdo, $merchantId);
    $dashboard['schema_ready'] = true;
    mg_ok($dashboard, 'Community Support dashboard loaded.');
} catch (InvalidArgumentException $error) {
    mg_fail('Unable to load the requested Community Support dashboard.', 422);
} catch (RuntimeException $error) {
    mg_fail('Community Support reporting is not available yet.', 503);
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'public_donations.community_support.api_failure',
        'Unable to load Community Support reporting.',
        500,
        ['method' => $method],
        $merchantId
    );
}
