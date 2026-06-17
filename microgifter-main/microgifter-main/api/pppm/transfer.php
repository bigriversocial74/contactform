<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.transfer');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string) ($input['action'] ?? 'create'));
$pdo = mg_db();

try {
    $pdo->beginTransaction();

    if ($action === 'create') {
        $itemId = trim((string) ($input['id'] ?? ''));
        $toUserId = isset($input['to_user_id']) && $input['to_user_id'] !== '' ? (int) $input['to_user_id'] : null;
        $externalId = trim((string) ($input['to_external_id'] ?? '')) ?: null;
        if ($itemId === '' || ($toUserId === null && $externalId === null)) mg_fail('Choose a transfer recipient.', 422);

        $item = mg_pppm_delivery_item_for_update($pdo, (int) $user['id'], $itemId);
        if ((int) ($item['owner_user_id'] ?? 0) !== (int) $user['id']) mg_fail('Only the current owner can transfer this item.', 403);
        if (in_array((string) $item['status'], ['redeemed','expired','cancelled','refunded','voided'], true)) mg_fail('This item cannot be transferred.', 409);

        $pdo->prepare("UPDATE pppm_transfer_requests SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE pppm_item_id = ? AND status = 'pending'")
            ->execute([(int) $item['id']]);

        $publicId = mg_pppm_uuid();
        $token = $externalId ? bin2hex(random_bytes(24)) : null;
        $tokenHash = $token ? hash('sha256', $token) : null;
        $expiresAt = date('Y-m-d H:i:s', time() + 604800);
        $pdo->prepare(
            "INSERT INTO pppm_transfer_requests
             (public_id, pppm_item_id, from_user_id, to_user_id, to_external_id, transfer_token_hash, status, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())"
        )->execute([$publicId, (int) $item['id'], (int) $user['id'], $toUserId, $externalId, $tokenHash, $expiresAt]);

        mg_pppm_record_event($pdo, $item, 'transfer_requested', (string) $item['status'], (string) $item['status'], (int) $user['id'], null, [
            'transfer_id' => $publicId,
            'to_user_id' => $toUserId,
            'to_external_id' => $externalId,
        ]);
        $pdo->commit();
        mg_ok(['transfer_id' => $publicId, 'transfer_token' => $token, 'expires_at' => $expiresAt], 'Transfer created.', 201);
    }

    $transferId = strtolower(trim((string) ($input['transfer_id'] ?? '')));
    $stmt = $pdo->prepare('SELECT tr.*, p.status AS item_status, p.owner_user_id FROM pppm_transfer_requests tr INNER JOIN pppm_items p ON p.id = tr.pppm_item_id WHERE tr.public_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$transferId]);
    $transfer = $stmt->fetch();
    if (!$transfer) mg_fail('Transfer not found.', 404);

    if ($action === 'cancel') {
        if ((int) $transfer['from_user_id'] !== (int) $user['id']) mg_fail('Only the sender can cancel this transfer.', 403);
        $pdo->prepare("UPDATE pppm_transfer_requests SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'")
            ->execute([(int) $transfer['id']]);
        $pdo->commit();
        mg_ok(['transfer_id' => $transferId, 'status' => 'cancelled'], 'Transfer cancelled.');
    }

    if ($action !== 'accept') mg_fail('Invalid transfer action.', 422);
    if ((string) $transfer['status'] !== 'pending') mg_fail('Transfer is no longer pending.', 409);
    if (!empty($transfer['expires_at']) && strtotime((string) $transfer['expires_at']) < time()) mg_fail('Transfer has expired.', 410);
    if (!empty($transfer['to_user_id']) && (int) $transfer['to_user_id'] !== (int) $user['id']) mg_fail('Transfer recipient does not match.', 403);
    if (!empty($transfer['transfer_token_hash'])) {
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '' || !hash_equals((string) $transfer['transfer_token_hash'], hash('sha256', $token))) mg_fail('Invalid transfer token.', 403);
    }

    $pdo->prepare("UPDATE pppm_transfer_requests SET status = 'accepted', to_user_id = ?, accepted_at = NOW(), updated_at = NOW() WHERE id = ?")
        ->execute([(int) $user['id'], (int) $transfer['id']]);
    $pdo->prepare("UPDATE pppm_items SET owner_user_id = ?, recipient_user_id = ?, status = 'assigned', assigned_at = NOW(), version_no = version_no + 1, updated_at = NOW() WHERE id = ?")
        ->execute([(int) $user['id'], (int) $user['id'], (int) $transfer['pppm_item_id']]);
    $itemStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ?');
    $itemStmt->execute([(int) $transfer['pppm_item_id']]);
    $item = $itemStmt->fetch();
    mg_pppm_record_event($pdo, $item, 'transfer_accepted', (string) $transfer['item_status'], 'assigned', (int) $user['id'], null, ['transfer_id' => $transferId]);
    $pdo->commit();
    mg_ok(['transfer_id' => $transferId, 'item' => mg_pppm_public_item($item)], 'Transfer accepted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to process this transfer.', 500);
}
