<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/rewards/_wallet_pppm_bridge.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-community-assignments.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-governance-locks.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-allocation.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$pdo = mg_db();
$governance = mg_public_donations_governance_context($pdo, $user, $method === 'GET' ? 'view' : 'allocate');
$merchantId = (int)$governance['merchant_user_id'];
$actorId = (int)$governance['actor_user_id'];

$schemaReady = mg_public_donations_allocation_schema_ready($pdo);
if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $operationRef = strtolower(trim((string)($_GET['operation_id'] ?? '')));
    $campaign = null;
    $assignments = [];
    $recent = [];

    if ($schemaReady && $campaignRef !== '') {
        $campaign = mg_public_donations_allocation_campaign($pdo, $merchantId, $campaignRef, false);
        $assignments = mg_public_donations_assignment_list($pdo, $merchantId, (int)$campaign['id'], 'active', 100);
        $recent = mg_public_donations_allocation_recent($pdo, $merchantId, (int)$campaign['id'], 20);
    }

    mg_ok([
        'schema_ready' => $schemaReady,
        'feature' => $governance['feature'],
        'governance' => [
            'permission' => $governance['permission'],
            'workspace_role' => $governance['workspace_role'],
            'merchant_scoped_to_workspace_owner' => true,
            'hourly_allocation_units' => mg_public_donations_governance_hourly_limit('allocation'),
            'concurrent_operations_serialized' => true,
        ],
        'campaigns' => mg_public_donations_assignment_campaigns($pdo, $merchantId),
        'selected_campaign' => $campaign ? [
            'id' => (string)$campaign['public_id'],
            'slug' => trim((string)$campaign['public_slug']) ?: null,
            'title' => (string)$campaign['title'],
            'status' => (string)$campaign['status'],
            'quantity_limit' => $campaign['quantity_limit'] !== null ? (int)$campaign['quantity_limit'] : null,
            'issued_count' => (int)$campaign['issued_count'],
        ] : null,
        'reward_templates' => $schemaReady ? mg_public_donations_allocation_templates($pdo, $merchantId) : [],
        'active_assignments' => $assignments,
        'recent_operations' => $recent,
        'operation' => $schemaReady && $operationRef !== ''
            ? mg_public_donations_allocation_tracking($pdo, $merchantId, $operationRef)
            : null,
        'limits' => [
            'max_recipients' => 50,
            'max_units' => 1000,
            'large_units' => 100,
            'large_value_cents' => 100000,
            'hourly_units' => mg_public_donations_governance_hourly_limit('allocation'),
        ],
        'architecture' => [
            'wallet' => true,
            'pppm' => true,
            'microgift' => true,
            'inbox' => true,
            'public_purchase' => false,
            'completed_replay_issues_new_rewards' => false,
        ],
        'privacy' => mg_public_donations_governance_privacy_contract(),
        'operational_copy' => mg_public_donations_governance_operational_copy(),
    ], $schemaReady
        ? 'Public Donations allocation workspace loaded.'
        : 'Import the Phase 1 Public Donations installer to enable reward allocation.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!$schemaReady) mg_fail('Public Donations allocation schema is unavailable. Import the Phase 1 installer.', 503);

mg_public_donations_governance_rate_limit('allocate', $merchantId, $actorId);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'preflight')));
if (!in_array($action, ['preflight', 'allocate'], true)) mg_fail('Invalid allocation action.', 422);

$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? '')));
$templateRef = strtolower(trim((string)($input['reward_template_id'] ?? '')));
$recipients = mg_public_donations_allocation_recipients($input['recipients'] ?? null);
$message = mg_public_donations_allocation_text($input['message'] ?? null, 1000, 'Recipient message');
$internalNote = mg_public_donations_allocation_text($input['internal_note'] ?? null, 2000, 'Internal note');
$idempotencyKey = $action === 'allocate'
    ? mg_public_donations_allocation_idempotency_key($input['idempotency_key'] ?? null)
    : null;
$result = null;
$operationLock = null;

try {
    // Resolve a completed retry before mutable campaign, template, assignment,
    // inventory, concurrency, or hourly-budget validation. A replay never issues
    // or budgets another reward lifecycle.
    if ($idempotencyKey !== null) {
        $requestHash = mg_public_donations_allocation_request_hash(
            $campaignRef,
            $templateRef,
            $recipients,
            $message,
            $internalNote
        );
        $completedReplay = mg_public_donations_allocation_operation($pdo, $merchantId, $idempotencyKey, false);
        if ($completedReplay) {
            if (!hash_equals((string)$completedReplay['request_hash'], $requestHash)) {
                mg_fail('This idempotency key belongs to a different allocation request.', 409);
            }
            if ((string)$completedReplay['status'] === 'completed') {
                $result = mg_public_donations_allocation_tracking(
                    $pdo,
                    $merchantId,
                    (string)$completedReplay['public_id']
                );
                $result['duplicate'] = true;
            } elseif ((string)$completedReplay['status'] === 'processing') {
                mg_fail('This allocation request is already processing.', 409);
            }
        }
    }

    if ($result === null) {
        $preflight = mg_public_donations_allocation_preflight(
            $pdo,
            $merchantId,
            $campaignRef,
            $templateRef,
            $recipients,
            $message,
            $internalNote
        );

        if ($action === 'preflight') {
            mg_ok([
                'preflight' => $preflight,
                'inventory_reserved' => false,
                'reward_inventory_changed' => false,
                'operational_copy' => mg_public_donations_governance_operational_copy(),
            ], 'Allocation preview ready. Inventory has not been reserved.');
        }

        $operationLock = mg_public_donations_governance_admit_operation(
            $pdo,
            $merchantId,
            'allocation',
            (int)$preflight['requested_quantity']
        );
        try {
            $confirmLarge = filter_var($input['confirm_large_operation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $result = mg_public_donations_allocation_execute(
                $pdo,
                $merchantId,
                $actorId,
                $campaignRef,
                $templateRef,
                $recipients,
                (string)$idempotencyKey,
                $message,
                $internalNote,
                $confirmLarge
            );
        } finally {
            mg_public_donations_governance_release_operation_lock($pdo, $operationLock);
            $operationLock = null;
        }
    }

    mg_public_donations_governance_log_success('allocate', $merchantId, $actorId, [
        'campaign_id' => (string)$result['campaign']['id'],
        'operation_id' => (string)$result['id'],
        'reward_template_id' => (string)$result['reward_template']['id'],
        'recipient_count' => (int)$result['recipient_count'],
        'quantity' => (int)$result['completed_quantity'],
        'duplicate' => !empty($result['duplicate']),
    ]);

    mg_ok([
        'operation' => $result,
        'duplicate' => !empty($result['duplicate']),
        'reward_inventory_changed' => empty($result['duplicate']),
        'operational_copy' => mg_public_donations_governance_operational_copy(),
    ], !empty($result['duplicate'])
        ? 'This allocation was already completed; no duplicate rewards were issued.'
        : 'Rewards were allocated through Wallet, PPPM, Microgift, and Inbox.');
} catch (Throwable $error) {
    if ($operationLock !== null) {
        mg_public_donations_governance_release_operation_lock($pdo, $operationLock);
    }
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'public_donations.allocation_failed', 'Public Donations allocation failed.', [
            'merchant_user_id' => $merchantId,
            'campaign_id' => $campaignRef,
            'reward_template_id' => $templateRef,
            'recipient_count' => count($recipients),
            'requested_quantity' => array_sum(array_column($recipients, 'quantity')),
            'error' => mb_substr($error->getMessage(), 0, 500),
        ], $actorId);
    }
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 499) $status = 500;
    $publicMessage = match ($status) {
        403 => 'You are not authorized to allocate Public Donations rewards.',
        404 => 'The selected campaign, reward, or Community assignment was not found.',
        409 => 'The allocation state changed or another operation is running. Refresh and try again.',
        422 => 'The allocation request is invalid or no longer eligible.',
        429 => 'The Public Donations operation limit has been reached. Try again later.',
        default => 'Unable to allocate Public Donations rewards.',
    };
    mg_fail($publicMessage, $status);
}
