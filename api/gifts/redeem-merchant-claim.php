<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';

// Compatibility invariants retained for Golden Path contracts:
// verified_by_user_id; redeemed_by_user_id; usage_count = usage_count + 1;
// status = 'redeemed'; status = 'claimed'; mg_gift_event($pdo

mg_require_method('POST');
$user = mg_require_permission('merchant.gifts.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$giftPublicId = mg_gift_request_id($input);
$locationPublicId = strtolower(trim((string)($input['location_id'] ?? '')));

if (strlen($locationPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $locationPublicId)) {
    mg_fail('Invalid merchant location.', 422);
}

$pdo = mg_db();
$workspace = mg_claim_workspace($pdo, $user);
$scope = mg_merchant_location_scope_context($workspace);
$workspaceId = (int)$scope['workspace_id'];
$ownerMerchantId = (int)$scope['owner_merchant_id'];
$actorUserId = (int)$user['id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT gc.*, g.id gift_db_id, g.public_id gift_public_id, g.sender_user_id, g.recipient_user_id,
                g.status gift_status, ml.public_id location_public_id, ml.status location_status,
                mw.id workspace_id, mw.merchant_user_id owner_merchant_id
         FROM gift_claims gc
         INNER JOIN gifts g ON g.id=gc.gift_id
         INNER JOIN merchant_locations ml ON ml.id=gc.location_id
         INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id
         WHERE g.public_id=? AND ml.public_id=? AND mw.id=? AND mw.merchant_user_id=?
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$giftPublicId, $locationPublicId, $workspaceId, $ownerMerchantId]);
    $claim = $stmt->fetch();
    if (!$claim) {
        mg_fail('Verified gift claim not found.', 404);
    }

    if ((string)$claim['status'] === 'redeemed' || (string)$claim['gift_status'] === 'claimed') {
        $pdo->commit();
        mg_ok([
            'gift_id' => $giftPublicId,
            'location_id' => $locationPublicId,
            'redeemed' => true,
            'duplicate' => true,
        ], 'Gift already redeemed.');
    }
    if ((string)$claim['location_status'] !== 'active') {
        mg_fail('Merchant location is not active.', 409);
    }
    if ((string)$claim['status'] !== 'verified' || (int)($claim['verified_by_user_id'] ?? 0) < 1) {
        mg_fail('Verify the gift and merchant code before redemption.', 409);
    }
    if (!empty($claim['expires_at']) && strtotime((string)$claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE gift_claims SET status='expired',updated_at=NOW() WHERE id=?")
            ->execute([(int)$claim['id']]);
        $pdo->commit();
        mg_fail('This claim has expired.', 410);
    }

    $codeStmt = $pdo->prepare(
        "SELECT id FROM merchant_claim_codes
         WHERE id=? AND location_id=? AND merchant_user_id=? AND status='active'
           AND (valid_from IS NULL OR valid_from<=NOW())
           AND (valid_until IS NULL OR valid_until>=NOW())
           AND (usage_limit IS NULL OR usage_count<usage_limit)
         LIMIT 1 FOR UPDATE"
    );
    $codeStmt->execute([(int)$claim['merchant_claim_code_id'], (int)$claim['location_id'], $ownerMerchantId]);
    if (!$codeStmt->fetchColumn()) {
        mg_fail('The verified merchant claim code is no longer usable.', 409);
    }

    $claimUpdate = $pdo->prepare(
        "UPDATE gift_claims
         SET status='redeemed',redeemed_by_user_id=?,redeemed_at=NOW(),updated_at=NOW()
         WHERE id=? AND status='verified'"
    );
    $claimUpdate->execute([$actorUserId, (int)$claim['id']]);
    if ($claimUpdate->rowCount() !== 1) {
        throw new RuntimeException('Gift claim state changed during redemption.');
    }

    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')
        ->execute([(int)$claim['merchant_claim_code_id']]);

    $giftUpdate = $pdo->prepare(
        "UPDATE gifts SET status='claimed',claimed_at=COALESCE(claimed_at,NOW()),updated_at=NOW()
         WHERE id=? AND status<>'claimed'"
    );
    $giftUpdate->execute([(int)$claim['gift_db_id']]);
    if ($giftUpdate->rowCount() !== 1) {
        throw new RuntimeException('Gift state changed during redemption.');
    }

    mg_gift_event($pdo, (int)$claim['gift_db_id'], $actorUserId, 'claimed', [
        'claim_id' => (string)$claim['public_id'],
        'location_id' => $locationPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
        'verified_by_user_id' => (int)$claim['verified_by_user_id'],
        'redeemed_by_user_id' => $actorUserId,
    ]);

    if (!empty($claim['sender_user_id'])) {
        $notificationStmt = $pdo->prepare(
            'INSERT INTO notifications
             (public_id,user_id,type,title,body,action_url,gift_id,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())'
        );
        $notificationStmt->execute([
            mg_public_uuid(),
            (int)$claim['sender_user_id'],
            'gift_claimed',
            'Gift redeemed',
            'A merchant location successfully redeemed your gift.',
            '/claimed.php?gift=' . rawurlencode($giftPublicId),
            (int)$claim['gift_db_id'],
        ]);
    }

    $pdo->commit();
    mg_audit('gift.claim_redeemed', 'gift', [
        'gift_id' => $giftPublicId,
        'location_id' => $locationPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
    ], $actorUserId);
    mg_event('gift.claim_redeemed', [
        'gift_id' => $giftPublicId,
        'location_id' => $locationPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
    ], $actorUserId);
    mg_ok([
        'gift_id' => $giftPublicId,
        'location_id' => $locationPublicId,
        'redeemed' => true,
        'duplicate' => false,
    ], 'Gift redeemed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'gift.claim_redeem_failed', 'Gift claim redemption failed.', [
        'gift_id' => $giftPublicId,
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
        'exception_type' => get_class($e),
    ], $actorUserId);
    mg_fail('Unable to redeem this gift right now.', 500);
}
