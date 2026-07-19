<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$itemPublicId = trim((string) ($input['id'] ?? ''));
$locationPublicId = strtolower(trim((string) ($input['location_id'] ?? '')));
$merchantCode = trim((string) ($input['code'] ?? ''));

if ($itemPublicId === '' || strlen($itemPublicId) > 32 || !preg_match('/^(GFT|PPPM)-[A-Z0-9-]+$/', $itemPublicId)) {
    mg_fail('Invalid PPPM item identifier.', 422);
}
if (strlen($locationPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $locationPublicId)) {
    mg_fail('Invalid merchant location.', 422);
}
if ($merchantCode === '' || mb_strlen($merchantCode) > 64) {
    mg_fail('Invalid merchant code.', 422);
}

$pdo = mg_db();
$workspace = mg_claim_workspace($pdo, $user);
$scope = mg_merchant_location_scope_context($workspace);
$workspaceId = (int) $scope['workspace_id'];
$ownerMerchantId = (int) $scope['owner_merchant_id'];
$actorUserId = (int) $user['id'];
$pepper = mg_claim_code_pepper();

try {
    $pdo->beginTransaction();

    $itemStmt = $pdo->prepare(
        "SELECT * FROM pppm_items
         WHERE public_id = ? AND status IN ('sent','delivered','viewed','claim_pending')
         LIMIT 1 FOR UPDATE"
    );
    $itemStmt->execute([$itemPublicId]);
    $item = $itemStmt->fetch();
    if (!$item) {
        mg_fail('PPPM item is not available for redemption.', 404);
    }

    $location = mg_claim_location($pdo, $user, $locationPublicId, true);
    if ((string) $location['status'] !== 'active') {
        mg_fail('Merchant location is not active.', 409);
    }

    $eligibilityStmt = $pdo->prepare(
        'SELECT 1 FROM pppm_merchant_eligibility
         WHERE pppm_item_id = ? AND merchant_user_id = ?
           AND (merchant_location_id IS NULL OR merchant_location_id = ?)
         LIMIT 1'
    );
    $eligibilityStmt->execute([(int) $item['id'], $ownerMerchantId, (int) $location['id']]);
    if (!$eligibilityStmt->fetchColumn()) {
        mg_fail('This merchant location is not authorized for the PPPM item.', 403);
    }

    $claimStmt = $pdo->prepare('SELECT * FROM pppm_claims WHERE pppm_item_id = ? LIMIT 1 FOR UPDATE');
    $claimStmt->execute([(int) $item['id']]);
    $claim = $claimStmt->fetch();
    if (!$claim) {
        $claimPublicId = mg_pppm_uuid();
        $pdo->prepare(
            "INSERT INTO pppm_claims
             (public_id, pppm_item_id, claimant_user_id, claimant_external_id, status, failed_attempts,
              merchant_location_id, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', 0, ?, ?, NOW(), NOW())"
        )->execute([
            $claimPublicId,
            (int) $item['id'],
            $item['recipient_user_id'] ?? null,
            $item['recipient_external_id'] ?? null,
            (int) $location['id'],
            $item['expires_at'] ?? null,
        ]);
        $claimStmt->execute([(int) $item['id']]);
        $claim = $claimStmt->fetch();
    }

    if (!$claim || in_array((string) $claim['status'], ['redeemed','cancelled','expired','locked'], true)) {
        mg_fail('This PPPM claim is not available.', 409);
    }
    if (!empty($claim['expires_at']) && strtotime((string) $claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE pppm_claims SET status = 'expired', updated_at = NOW() WHERE id = ?")
            ->execute([(int) $claim['id']]);
        $pdo->prepare("UPDATE pppm_items SET status = 'expired', version_no = version_no + 1, updated_at = NOW() WHERE id = ?")
            ->execute([(int) $item['id']]);
        $pdo->commit();
        mg_fail('This PPPM item has expired.', 410);
    }

    $candidateHash = mg_claim_code_hash(mg_claim_code_require($merchantCode, 'Invalid merchant code.'), $pepper);
    $codesStmt = $pdo->prepare(
        "SELECT mcc.*
         FROM merchant_claim_codes mcc
         INNER JOIN merchant_locations ml ON ml.id = mcc.location_id
         ".mg_merchant_location_scope_join('ml', 'claim_scope_mw')."
         WHERE ".mg_merchant_location_scope_condition('ml', 'claim_scope_mw')."
           AND mcc.location_id = ? AND mcc.status = 'active'
           AND (mcc.valid_from IS NULL OR mcc.valid_from <= NOW())
           AND (mcc.valid_until IS NULL OR mcc.valid_until >= NOW())
           AND (mcc.usage_limit IS NULL OR mcc.usage_count < mcc.usage_limit)
         FOR UPDATE"
    );
    $codesStmt->execute([$workspaceId, $ownerMerchantId, (int) $location['id']]);
    $matched = null;
    foreach ($codesStmt->fetchAll() as $candidate) {
        if (hash_equals((string) $candidate['code_hash'], $candidateHash)) {
            $matched = $candidate;
            break;
        }
    }

    $success = is_array($matched);
    $ipHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $pepper);
    $uaHash = hash_hmac('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper);
    $pdo->prepare(
        'INSERT INTO pppm_claim_attempts
         (claim_id, actor_user_id, successful, ip_hash, user_agent_hash, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    )->execute([(int) $claim['id'], $actorUserId, $success ? 1 : 0, $ipHash, $uaHash]);

    if (!$success) {
        $attempts = (int) $claim['failed_attempts'] + 1;
        $locked = $attempts >= 5;
        $pdo->prepare(
            'UPDATE pppm_claims
             SET failed_attempts = ?, status = ?, locked_at = ?, merchant_location_id = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $attempts,
            $locked ? 'locked' : 'pending',
            $locked ? date('Y-m-d H:i:s') : null,
            (int) $location['id'],
            (int) $claim['id'],
        ]);
        $pdo->commit();
        mg_audit('pppm.claim_failed', 'pppm_item', [
            'item_id' => $itemPublicId,
            'location_id' => $locationPublicId,
            'owner_merchant_id' => $ownerMerchantId,
            'locked' => $locked,
        ], $actorUserId);
        mg_fail($locked ? 'This PPPM claim is locked.' : 'Invalid merchant code.', $locked ? 423 : 422);
    }

    $pdo->prepare(
        "UPDATE pppm_claims
         SET merchant_location_id = ?, merchant_claim_code_id = ?, verified_by_user_id = ?,
             status = 'verified', verified_at = NOW(), failed_attempts = 0, locked_at = NULL, updated_at = NOW()
         WHERE id = ?"
    )->execute([(int) $location['id'], (int) $matched['id'], $actorUserId, (int) $claim['id']]);

    $fromStatus = (string) $item['status'];
    $pdo->prepare(
        "UPDATE pppm_items SET status = 'verified', version_no = version_no + 1, updated_at = NOW() WHERE id = ?"
    )->execute([(int) $item['id']]);
    $updated = mg_pppm_refresh($pdo, (int) $item['id']);
    mg_pppm_record_event($pdo, $updated, 'merchant_verified', $fromStatus, 'verified', $actorUserId, null, [
        'location_id' => $locationPublicId,
        'claim_id' => (string) $claim['public_id'],
        'owner_merchant_id' => $ownerMerchantId,
    ]);

    $pdo->commit();
    mg_audit('pppm.claim_verified', 'pppm_item', [
        'item_id' => $itemPublicId,
        'location_id' => $locationPublicId,
        'owner_merchant_id' => $ownerMerchantId,
    ], $actorUserId);
    mg_ok([
        'item_id' => $itemPublicId,
        'claim_id' => (string) $claim['public_id'],
        'location_id' => $locationPublicId,
        'verified' => true,
    ], 'PPPM item and merchant code verified.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'pppm.claim_verify_failed', 'PPPM claim verification failed.', [
        'item_id' => $itemPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
        'exception_type' => get_class($e),
    ], $actorUserId);
    mg_fail('Unable to verify this PPPM item right now.', 500);
}
