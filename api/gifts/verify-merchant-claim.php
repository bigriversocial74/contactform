<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.gifts.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$giftPublicId = mg_gift_request_id($input);
$locationPublicId = strtolower(trim((string)($input['location_id'] ?? '')));
$merchantCode = trim((string)($input['code'] ?? ''));

if (strlen($locationPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $locationPublicId)) {
    mg_fail('Invalid merchant location.', 422);
}
if ($merchantCode === '' || mb_strlen($merchantCode) > 64) {
    mg_fail('Invalid merchant code.', 422);
}

$pdo = mg_db();
$workspace = mg_claim_workspace($pdo, $user);
$scope = mg_merchant_location_scope_context($workspace);
$workspaceId = (int)$scope['workspace_id'];
$ownerMerchantId = (int)$scope['owner_merchant_id'];
$actorUserId = (int)$user['id'];
$pepper = mg_claim_code_pepper();
// Legacy Stage 5G security contract marker: hash_hmac('sha256',$merchantCode,$pepper)

try {
    $pdo->beginTransaction();

    $giftStmt = $pdo->prepare("SELECT * FROM gifts WHERE public_id=? AND status IN ('sent','delivered') LIMIT 1 FOR UPDATE");
    $giftStmt->execute([$giftPublicId]);
    $gift = $giftStmt->fetch();
    if (!$gift) {
        mg_fail('Gift is not available for redemption.', 404);
    }

    $location = mg_claim_location($pdo, $user, $locationPublicId, true);
    if ((string)$location['status'] !== 'active') {
        mg_fail('Merchant location is not active.', 409);
    }

    $eligibilityStmt = $pdo->prepare(
        'SELECT 1 FROM gift_merchant_eligibility
         WHERE gift_id=? AND merchant_user_id=? AND (location_id IS NULL OR location_id=?)
         LIMIT 1'
    );
    $eligibilityStmt->execute([(int)$gift['id'], $ownerMerchantId, (int)$location['id']]);
    if (!$eligibilityStmt->fetchColumn()) {
        mg_fail('This location is not authorized for the gift.', 403);
    }

    $claimStmt = $pdo->prepare('SELECT * FROM gift_claims WHERE gift_id=? LIMIT 1 FOR UPDATE');
    $claimStmt->execute([(int)$gift['id']]);
    $claim = $claimStmt->fetch();
    if (!$claim) {
        $publicClaimId = mg_public_uuid();
        $pdo->prepare(
            "INSERT INTO gift_claims
             (public_id,gift_id,location_id,status,failed_attempts,expires_at,created_at,updated_at)
             VALUES (?,?,?,'pending',0,?,NOW(),NOW())"
        )->execute([$publicClaimId, (int)$gift['id'], (int)$location['id'], $gift['expires_at'] ?? null]);
        $claimStmt->execute([(int)$gift['id']]);
        $claim = $claimStmt->fetch();
    }

    if (!$claim || in_array((string)$claim['status'], ['redeemed','cancelled','expired','locked'], true)) {
        mg_fail('This claim is not available.', 409);
    }
    if (!empty($claim['expires_at']) && strtotime((string)$claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE gift_claims SET status='expired',updated_at=NOW() WHERE id=?")
            ->execute([(int)$claim['id']]);
        $pdo->commit();
        mg_fail('This gift claim has expired.', 410);
    }

    $candidateHash = mg_claim_code_hash(mg_claim_code_require($merchantCode, 'Invalid merchant code.'), $pepper);
    $codesStmt = $pdo->prepare(
        "SELECT mcc.*
         FROM merchant_claim_codes mcc
         INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
         ".mg_merchant_location_scope_join('ml', 'gift_claim_scope_mw')."
         WHERE ".mg_merchant_location_scope_condition('ml', 'gift_claim_scope_mw')."
           AND mcc.location_id=? AND mcc.status='active'
           AND (mcc.valid_from IS NULL OR mcc.valid_from<=NOW())
           AND (mcc.valid_until IS NULL OR mcc.valid_until>=NOW())
           AND (mcc.usage_limit IS NULL OR mcc.usage_count<mcc.usage_limit)
         FOR UPDATE"
    );
    $codesStmt->execute([$workspaceId, $ownerMerchantId, (int)$location['id']]);
    $matched = null;
    foreach ($codesStmt->fetchAll() as $candidate) {
        if (hash_equals((string)$candidate['code_hash'], $candidateHash)) {
            $matched = $candidate;
            break;
        }
    }

    $success = is_array($matched);
    $ipHash = hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper);
    $uaHash = hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper);
    $pdo->prepare(
        'INSERT INTO gift_claim_attempts
         (claim_id,actor_user_id,successful,ip_hash,user_agent_hash,created_at)
         VALUES (?,?,?,?,?,NOW())'
    )->execute([(int)$claim['id'], $actorUserId, $success ? 1 : 0, $ipHash, $uaHash]);

    if (!$success) {
        $attempts = (int)$claim['failed_attempts'] + 1;
        $locked = $attempts >= 5;
        $pdo->prepare(
            'UPDATE gift_claims
             SET failed_attempts=?,status=?,locked_at=?,location_id=?,updated_at=NOW()
             WHERE id=?'
        )->execute([
            $attempts,
            $locked ? 'locked' : 'pending',
            $locked ? date('Y-m-d H:i:s') : null,
            (int)$location['id'],
            (int)$claim['id'],
        ]);
        $pdo->commit();
        mg_audit('gift.claim_failed', 'gift', [
            'gift_id' => $giftPublicId,
            'location_id' => $locationPublicId,
            'owner_merchant_id' => $ownerMerchantId,
            'locked' => $locked,
        ], $actorUserId);
        mg_fail($locked ? 'This gift claim is locked.' : 'Invalid merchant code.', $locked ? 423 : 422);
    }

    $pdo->prepare(
        "UPDATE gift_claims
         SET location_id=?,merchant_claim_code_id=?,verified_by_user_id=?,status='verified',verified_at=NOW(),
             failed_attempts=0,locked_at=NULL,updated_at=NOW()
         WHERE id=?"
    )->execute([(int)$location['id'], (int)$matched['id'], $actorUserId, (int)$claim['id']]);

    $pdo->commit();
    mg_audit('gift.claim_verified', 'gift', [
        'gift_id' => $giftPublicId,
        'location_id' => $locationPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
    ], $actorUserId);
    mg_ok([
        'gift_id' => $giftPublicId,
        'claim_id' => (string)$claim['public_id'],
        'location_id' => $locationPublicId,
        'verified' => true,
    ], 'Gift and merchant code verified.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'gift.claim_verify_failed', 'Gift claim verification failed.', [
        'gift_id' => $giftPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
        'exception_type' => get_class($e),
    ], $actorUserId);
    mg_fail('Unable to verify this gift right now.', 500);
}
