<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-community-support.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission('merchant.campaigns.view');
$pdo = mg_db();
$governance = mg_public_donations_governance_context($pdo, $user, 'report');
$merchantId = (int)$governance['merchant_user_id'];
$actorId = (int)$governance['actor_user_id'];

if ($method !== 'GET') {
    mg_fail('Method not allowed.', 405);
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
            'governance' => [
                'feature' => $governance['feature'],
                'permission' => $governance['permission'],
                'workspace_role' => $governance['workspace_role'],
                'merchant_scoped_to_workspace_owner' => true,
            ],
            'integrity' => [],
            'privacy' => mg_public_donations_governance_privacy_contract(),
            'operational_copy' => mg_public_donations_governance_operational_copy(),
        ], 'Import the Public Donations Community installer to enable Community Support reporting.');
    }

    $dashboard = mg_community_support_dashboard($pdo, $merchantId);
    $dashboard['schema_ready'] = true;
    $dashboard['governance'] = [
        'feature' => $governance['feature'],
        'permission' => $governance['permission'],
        'workspace_role' => $governance['workspace_role'],
        'merchant_scoped_to_workspace_owner' => true,
    ];
    $dashboard['integrity'] = mg_public_donations_governance_integrity($pdo, $merchantId);
    $dashboard['privacy'] = array_merge(
        is_array($dashboard['privacy'] ?? null) ? $dashboard['privacy'] : [],
        mg_public_donations_governance_privacy_contract()
    );
    $dashboard['operational_copy'] = mg_public_donations_governance_operational_copy();

    mg_public_donations_governance_log_success('report', $merchantId, $actorId, [
        'campaign_count' => (int)($dashboard['summary']['campaigns'] ?? 0),
        'community_account_count' => (int)($dashboard['summary']['community_accounts'] ?? 0),
    ]);

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
        ['method' => $method, 'merchant_user_id' => $merchantId],
        $actorId
    );
}
