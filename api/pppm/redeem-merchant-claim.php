<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$itemPublicId = trim((string) ($input['id'] ?? ''));
$locationPublicId = strtolower(trim((string) ($input['location_id'] ?? '')));

if ($itemPublicId === '' || strlen($itemPublicId) > 32 || !preg_match('/^(GFT|PPPM)-[A-Z0-9-]+$/', $itemPublicId)) {
    mg_fail('Invalid PPPM item identifier.', 422);
}
if (strlen($locationPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $locationPublicId)) {
    mg_fail('Invalid merchant location.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT pc.*, p.id AS item_db_id, p.public_id AS item_public_id, p.status AS item_status,
                p.value_cents_snapshot, p.currency_snapshot, p.issuer_user_id, p.recipient_user_id,
                ml.public_id AS location_public_id, ml.merchant_user_id
         FROM pppm_claims pc
         INNER JOIN pppm_items p ON p.id = pc.pppm_item_id
         INNER JOIN merchant_locations ml ON ml.id = pc.merchant_location_id
         WHERE p.public_id = ? AND ml.public_id = ?
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$itemPublicId, $locationPublicId]);
    $claim = $stmt->fetch();

    if (!$claim || (int) $claim['merchant_user_id'] !== (int) $user['id']) {
        mg_fail('Verified PPPM claim not found.', 404);
    }
    if ((string) $claim['status'] === 'redeemed' || (string) $claim['item_status'] === 'redeemed') {
        $pdo->commit();
        mg_ok([
            'item_id' => $itemPublicId,
            'location_id' => $locationPublicId,
            'redeemed' => true,
        ], 'PPPM item already redeemed.');
    }
    if ((string) $claim['status'] !== 'verified' || (int) ($claim['verified_by_user_id'] ?? 0) !== (int) $user['id']) {
        mg_fail('Verify the PPPM item and merchant code before redemption.', 409);
    }
    if (!empty($claim['expires_at']) && strtotime((string) $claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE pppm_claims SET status = 'expired', updated_at = NOW() WHERE id = ?")
            ->execute([(int) $claim['id']]);
        $pdo->prepare("UPDATE pppm_items SET status = 'expired', version_no = version_no + 1, updated_at = NOW() WHERE id = ?")
            ->execute([(int) $claim['item_db_id']]);
        $pdo->commit();
        mg_fail('This PPPM item has expired.', 410);
    }

    $redemptionPublicId = mg_pppm_uuid();
    $pdo->prepare(
        'INSERT INTO pppm_redemptions
         (public_id, pppm_item_id, claim_id, merchant_user_id, merchant_location_id,
          merchant_claim_code_id, redeemed_by_user_id, value_cents_snapshot, currency_snapshot,
          metadata_json, redeemed_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    )->execute([
        $redemptionPublicId,
        (int) $claim['item_db_id'],
        (int) $claim['id'],
        (int) $user['id'],
        (int) $claim['merchant_location_id'],
        (int) $claim['merchant_claim_code_id'],
        (int) $user['id'],
        (int) $claim['value_cents_snapshot'],
        (string) $claim['currency_snapshot'],
        json_encode(['location_id' => $locationPublicId], JSON_UNESCAPED_SLASHES),
    ]);

    $pdo->prepare(
        "UPDATE pppm_claims
         SET status = 'redeemed', redeemed_by_user_id = ?, redeemed_at = NOW(), updated_at = NOW()
         WHERE id = ?"
    )->execute([(int) $user['id'], (int) $claim['id']]);

    $pdo->prepare(
        'UPDATE merchant_claim_codes SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = ?'
    )->execute([(int) $claim['merchant_claim_code_id']]);

    $pdo->prepare(
        "UPDATE pppm_items
         SET status = 'redeemed', redeemed_at = NOW(), version_no = version_no + 1, updated_at = NOW()
         WHERE id = ?"
    )->execute([(int) $claim['item_db_id']]);

    $itemStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $itemStmt->execute([(int) $claim['item_db_id']]);
    $updated = $itemStmt->fetch();
    mg_pppm_record_event($pdo, $updated, 'merchant_redeemed', 'verified', 'redeemed', (int) $user['id'], null, [
        'redemption_id' => $redemptionPublicId,
        'location_id' => $locationPublicId,
    ]);

    if (!empty($claim['issuer_user_id'])) {
        $pdo->prepare(
            'INSERT INTO notifications
             (public_id, user_id, type, title, body, action_url, pppm_item_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            mg_pppm_uuid(),
            (int) $claim['issuer_user_id'],
            'pppm_redeemed',
            'PPPM item redeemed',
            'A merchant location successfully redeemed an issued item.',
            '/claimed.php?item=' . rawurlencode($itemPublicId),
            (int) $claim['item_db_id'],
        ]);
    }

    $pdo->commit();
    mg_audit('pppm.claim_redeemed', 'pppm_item', [
        'item_id' => $itemPublicId,
        'redemption_id' => $redemptionPublicId,
        'location_id' => $locationPublicId,
    ], (int) $user['id']);
    mg_ok([
        'item_id' => $itemPublicId,
        'redemption_id' => $redemptionPublicId,
        'location_id' => $locationPublicId,
        'redeemed' => true,
    ], 'PPPM item redeemed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'pppm.claim_redeem_failed', 'PPPM redemption failed.', [
        'item_id' => $itemPublicId,
        'exception_type' => get_class($e),
    ], (int) $user['id']);
    mg_fail('Unable to redeem this PPPM item right now.', 500);
}
