<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-recall.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)($user['id'] ?? 0);
$actorId = (int)($user['id'] ?? 0);
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if (!mg_public_donations_is_enabled_for($merchantId, $user)) {
    mg_fail('Public Donations recall controls are not enabled for this merchant.', 403);
}

$schemaReady = mg_public_donations_recall_schema_ready($pdo);
if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $batchRef = strtolower(trim((string)($_GET['batch_id'] ?? '')));
    $operationRef = strtolower(trim((string)($_GET['operation_id'] ?? '')));

    mg_ok([
        'schema_ready' => $schemaReady,
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
        ],
    ], $schemaReady
        ? 'Public Donations recall workspace loaded.'
        : 'Import the Phase 1 Public Donations installer to enable recall controls.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!$schemaReady) mg_fail('Public Donations recall schema is unavailable. Import the Phase 1 installer.', 503);

$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'recall')));
if ($action !== 'recall') mg_fail('Invalid recall action.', 422);

$batchRef = strtolower(trim((string)($input['batch_id'] ?? '')));
$quantity = mg_public_donations_recall_quantity($input['quantity'] ?? null);
$reason = mg_public_donations_recall_reason($input['reason'] ?? null);
$idempotencyKey = mg_public_donations_recall_idempotency_key($input['idempotency_key'] ?? null);

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

    if (function_exists('mg_audit')) {
        mg_audit('merchant.public_donations.recall', 'campaign_donation_operation', [
            'operation_id' => (string)$result['id'],
            'batch_id' => (string)$result['batch_id'],
            'campaign_id' => (string)$result['campaign']['id'],
            'reward_template_id' => (string)$result['reward_template']['id'],
            'quantity' => (int)$result['completed_quantity'],
            'reason' => (string)$result['reason'],
            'duplicate' => !empty($result['duplicate']),
        ], $actorId);
    }

    mg_ok([
        'operation' => $result,
        'duplicate' => !empty($result['duplicate']),
        'inventory_restored' => empty($result['duplicate']) ? (int)$result['completed_quantity'] : 0,
        'downstream_recipients_affected' => false,
    ], !empty($result['duplicate'])
        ? 'This recall was already completed; no additional rewards were changed.'
        : 'Untouched Public Donations rewards were recalled and inventory was restored.');
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'public_donations.recall_failed', 'Public Donations recall failed.', [
            'batch_id' => $batchRef,
            'quantity' => $quantity,
            'idempotency_key' => $idempotencyKey,
            'error' => mb_substr($error->getMessage(), 0, 500),
        ], $actorId);
    }
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 499) $status = 500;
    mg_fail(
        $status === 500 ? 'Unable to recall Public Donations rewards.' : $error->getMessage(),
        $status
    );
}
