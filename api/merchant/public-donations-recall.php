<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance-locks.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-recall.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$pdo = mg_db();
$governance = mg_public_donations_governance_context($pdo, $user, $method === 'GET' ? 'view' : 'recall');
$merchantId = (int)$governance['merchant_user_id'];
$actorId = (int)$governance['actor_user_id'];

$schemaReady = mg_public_donations_recall_schema_ready($pdo);
if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $batchRef = strtolower(trim((string)($_GET['batch_id'] ?? '')));
    $operationRef = strtolower(trim((string)($_GET['operation_id'] ?? '')));

    mg_ok([
        'schema_ready' => $schemaReady,
        'feature' => $governance['feature'],
        'governance' => [
            'permission' => $governance['permission'],
            'workspace_role' => $governance['workspace_role'],
            'merchant_scoped_to_workspace_owner' => true,
            'hourly_recall_units' => mg_public_donations_governance_hourly_limit('recall'),
            'concurrent_operations_serialized' => true,
        ],
        'batches' => $schemaReady
            ? mg_public_donations_recall_batches($pdo, $merchantId, $campaignRef !== '' ? $campaignRef : null, 100)
            : [],
        'preview' => $schemaReady && $batchRef !== ''
            ? mg_public_donations_recall_preview($pdo, $merchantId, $batchRef)
            : null,
        'operation' => $schemaReady && $operationRef !== ''
            ? mg_public_donations_recall_tracking($pdo, $merchantId, $operationRef)
            : null,
        'eligibility' => [
            'original_owner_required' => true,
            'regifted_excluded' => true,
            'claimed_excluded' => true,
            'redeemed_excluded' => true,
            'expired_excluded' => true,
            'cancelled_excluded' => true,
            'downstream_recipients_protected' => true,
            'role_removal_invalidates_existing_rewards' => false,
        ],
        'privacy' => mg_public_donations_governance_privacy_contract(),
        'operational_copy' => mg_public_donations_governance_operational_copy(),
    ], $schemaReady
        ? 'Public Donations recall workspace loaded.'
        : 'Import the Phase 1 Public Donations installer to enable recall controls.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!$schemaReady) mg_fail('Public Donations recall schema is unavailable. Import the Phase 1 installer.', 503);

mg_public_donations_governance_rate_limit('recall', $merchantId, $actorId);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'recall')));
if ($action !== 'recall') mg_fail('Invalid recall action.', 422);

$batchRef = strtolower(trim((string)($input['batch_id'] ?? '')));
$quantity = mg_public_donations_recall_quantity($input['quantity'] ?? null);
$reason = mg_public_donations_recall_reason($input['reason'] ?? null);
$idempotencyKey = mg_public_donations_recall_idempotency_key($input['idempotency_key'] ?? null);
$requestHash = mg_public_donations_recall_request_hash($batchRef, $quantity, $reason);
$operationLock = null;

try {
    // Resolve completed replays before the concurrency lock and hourly budget.
    // The immutable completed operation is returned without touching rewards.
    $replay = mg_public_donations_recall_operation($pdo, $merchantId, $idempotencyKey, false);
    if ($replay) {
        if ((string)$replay['operation_kind'] !== 'recall' || !hash_equals((string)$replay['request_hash'], $requestHash)) {
            mg_fail('This idempotency key belongs to a different operation.', 409);
        }
        if ((string)$replay['status'] === 'completed') {
            $result = mg_public_donations_recall_tracking($pdo, $merchantId, (string)$replay['public_id']);
            $result['duplicate'] = true;
        } else {
            mg_fail('This recall is already processing.', 409);
        }
    } else {
        $operationLock = mg_public_donations_governance_admit_operation(
            $pdo,
            $merchantId,
            'recall',
            $quantity
        );
        try {
            $result = mg_public_donations_recall_execute(
                $pdo,
                $merchantId,
                $actorId,
                $batchRef,
                $quantity,
                $reason,
                $idempotencyKey
            );
        } finally {
            mg_public_donations_governance_release_operation_lock($pdo, $operationLock);
            $operationLock = null;
        }
    }

    mg_public_donations_governance_log_success('recall', $merchantId, $actorId, [
        'operation_id' => (string)$result['id'],
        'batch_id' => (string)$result['batch_id'],
        'campaign_id' => (string)$result['campaign']['id'],
        'reward_template_id' => (string)$result['reward_template']['id'],
        'quantity' => (int)$result['completed_quantity'],
        'reason' => (string)$result['reason'],
        'duplicate' => !empty($result['duplicate']),
    ]);

    mg_ok([
        'operation' => $result,
        'duplicate' => !empty($result['duplicate']),
        'inventory_restored' => empty($result['duplicate']) ? (int)$result['completed_quantity'] : 0,
        'downstream_recipients_affected' => false,
        'existing_nonrecallable_rewards_preserved' => true,
        'operational_copy' => mg_public_donations_governance_operational_copy(),
    ], !empty($result['duplicate'])
        ? 'This recall was already completed; no additional rewards were changed.'
        : 'Untouched Public Donations rewards were recalled and inventory was restored.');
} catch (Throwable $error) {
    if ($operationLock !== null) {
        mg_public_donations_governance_release_operation_lock($pdo, $operationLock);
    }
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'public_donations.recall_failed', 'Public Donations recall failed.', [
            'merchant_user_id' => $merchantId,
            'batch_id' => $batchRef,
            'quantity' => $quantity,
            'error' => mb_substr($error->getMessage(), 0, 500),
        ], $actorId);
    }

    $status = (int)$error->getCode();
    if ($status < 400 || $status > 499) $status = 500;
    $publicMessage = match ($status) {
        403 => 'You are not authorized to recall this donation batch.',
        404 => 'The requested donation batch or recall operation was not found.',
        409 => 'The recall state changed or another operation is running. Refresh and try again.',
        422 => 'The recall request is no longer eligible. Refresh the preview and adjust the quantity.',
        429 => 'The Public Donations operation limit has been reached. Try again later.',
        default => 'Unable to recall Public Donations rewards.',
    };

    mg_fail($publicMessage, $status);
}
