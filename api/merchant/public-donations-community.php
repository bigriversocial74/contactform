<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-community-assignments.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$pdo = mg_db();
$governance = mg_public_donations_governance_context($pdo, $user, $method === 'GET' ? 'view' : 'assign');
$merchantId = (int)$governance['merchant_user_id'];
$actorId = (int)$governance['actor_user_id'];

$schemaReady = mg_public_donations_assignment_schema_ready($pdo);
$campaigns = mg_public_donations_assignment_campaigns($pdo, $merchantId);

if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $selectedCampaign = null;
    $assigned = [];
    $search = [];
    $summary = ['total' => 0, 'active' => 0, 'paused' => 0, 'removed' => 0];

    if ($campaignRef !== '') {
        $selectedCampaign = mg_public_donations_assignment_campaign($pdo, $merchantId, $campaignRef);
        if ($schemaReady) {
            $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
            $summary = mg_public_donations_assignment_summary($pdo, $merchantId, (int)$selectedCampaign['id']);
            $assigned = mg_public_donations_assignment_list(
                $pdo,
                $merchantId,
                (int)$selectedCampaign['id'],
                $status,
                mg_public_donations_assignment_limit($_GET['assigned_limit'] ?? 50, 50, 100)
            );
            if ((string)($_GET['include_search'] ?? '') === '1') {
                $search = mg_public_donations_assignment_search(
                    $pdo,
                    $merchantId,
                    (int)$selectedCampaign['id'],
                    (string)($_GET['q'] ?? ''),
                    mg_public_donations_assignment_limit($_GET['limit'] ?? 24)
                );
            }
        }
    }

    mg_ok([
        'schema_ready' => $schemaReady,
        'feature' => $governance['feature'],
        'governance' => [
            'permission' => $governance['permission'],
            'workspace_role' => $governance['workspace_role'],
            'merchant_scoped_to_workspace_owner' => true,
        ],
        'campaigns' => $campaigns,
        'selected_campaign' => $selectedCampaign ? [
            'id' => (string)$selectedCampaign['public_id'],
            'slug' => trim((string)$selectedCampaign['public_slug']) ?: null,
            'title' => (string)$selectedCampaign['title'],
            'status' => (string)$selectedCampaign['status'],
        ] : null,
        'summary' => $summary,
        'assigned' => $assigned,
        'search' => $search,
        'privacy' => array_merge(
            mg_public_donations_governance_privacy_contract(),
            [
                'public_identity_only' => true,
                'exact_location_excluded' => true,
                'private_contact_fields_excluded' => true,
            ]
        ),
        'operational_copy' => mg_public_donations_governance_operational_copy(),
        'reward_inventory_changed' => false,
    ], $schemaReady ? 'Community assignment workspace loaded.' : 'Import the Phase 1 Public Donations installer to enable Community assignments.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!$schemaReady) mg_fail('Community assignment schema is unavailable. Import the Phase 1 Public Donations installer.', 503);

mg_public_donations_governance_rate_limit('assign', $merchantId, $actorId);
$input = mg_input();
mg_require_csrf_for_write($input);
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? '')));
$action = strtolower(trim((string)($input['action'] ?? '')));
$communityAccountId = trim((string)($input['community_account_id'] ?? ''));
$assignmentPublicId = strtolower(trim((string)($input['assignment_id'] ?? '')));

$result = mg_public_donations_assignment_mutate(
    $pdo,
    $merchantId,
    $actorId,
    $campaignRef,
    $action,
    $communityAccountId,
    $assignmentPublicId
);
$campaign = mg_public_donations_assignment_campaign($pdo, $merchantId, $campaignRef);
$summary = mg_public_donations_assignment_summary($pdo, $merchantId, (int)$campaign['id']);
$assigned = mg_public_donations_assignment_list($pdo, $merchantId, (int)$campaign['id'], 'all', 100);

$message = match ($action) {
    'pause' => !empty($result['changed']) ? 'Community assignment paused.' : 'Community assignment was already paused.',
    'remove' => !empty($result['changed']) ? 'Community assignment removed.' : 'Community assignment was already removed.',
    'reactivate' => !empty($result['changed']) ? 'Community assignment reactivated.' : 'Community assignment was already active.',
    default => !empty($result['changed']) ? 'Community account added to the campaign.' : 'Community account was already assigned.',
};

mg_public_donations_governance_log_success('assign', $merchantId, $actorId, [
    'campaign_id' => (string)$campaign['public_id'],
    'assignment_id' => (string)($result['assignment']['id'] ?? $assignmentPublicId),
    'assignment_action' => $action !== '' ? $action : 'add',
    'changed' => !empty($result['changed']),
]);

mg_ok([
    'result' => $result,
    'summary' => $summary,
    'assigned' => $assigned,
    'reward_inventory_changed' => false,
    'existing_rewards_preserved' => true,
    'operational_copy' => mg_public_donations_governance_operational_copy(),
], $message);
