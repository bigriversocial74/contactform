<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';

mg_require_method('POST');
$user = mg_require_permission('gift.create');
$input = mg_input();
mg_require_csrf_for_write($input);
$giftPublicId = mg_gift_request_id($input);
$merchantUserId = (int) ($input['merchant_user_id'] ?? 0);
$locationPublicId = strtolower(trim((string) ($input['location_id'] ?? '')));

if ($merchantUserId < 1) {
    mg_fail('Invalid merchant.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $gift = mg_gift_require_accessible((int) $user['id'], $giftPublicId);
    if ((int) $gift['sender_user_id'] !== (int) $user['id']) {
        mg_fail('Only the gift owner can assign merchants.', 403);
    }

    $locationId = null;
    if ($locationPublicId !== '') {
        $locationStmt = $pdo->prepare(
            "SELECT id FROM merchant_locations
             WHERE public_id = ? AND merchant_user_id = ? AND status = 'active'
             LIMIT 1"
        );
        $locationStmt->execute([$locationPublicId, $merchantUserId]);
        $locationId = $locationStmt->fetchColumn();
        if (!$locationId) {
            mg_fail('Merchant location not found.', 404);
        }
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT id FROM gift_merchant_eligibility
         WHERE gift_id = ? AND merchant_user_id = ?
           AND ((location_id IS NULL AND ? IS NULL) OR location_id = ?)
         LIMIT 1 FOR UPDATE'
    );
    $locationValue = $locationId !== null ? (int) $locationId : null;
    $duplicateStmt->execute([(int) $gift['id'], $merchantUserId, $locationValue, $locationValue]);
    $existingId = $duplicateStmt->fetchColumn();

    if (!$existingId) {
        $stmt = $pdo->prepare(
            'INSERT INTO gift_merchant_eligibility
             (gift_id, merchant_user_id, location_id, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([(int) $gift['id'], $merchantUserId, $locationValue]);
    }

    $pdo->commit();
    mg_audit('gift.merchant_added', 'gift', [
        'gift_id' => $giftPublicId,
        'merchant_user_id' => $merchantUserId,
        'location_id' => $locationPublicId ?: null,
        'duplicate' => (bool) $existingId,
    ], (int) $user['id']);
    mg_ok([
        'gift_id' => $giftPublicId,
        'merchant_user_id' => $merchantUserId,
        'location_id' => $locationPublicId ?: null,
        'duplicate' => (bool) $existingId,
    ], $existingId ? 'Merchant already assigned.' : 'Merchant added.', $existingId ? 200 : 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail('Unable to update the gift merchant.', 500);
}
