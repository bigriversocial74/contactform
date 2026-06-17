<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.items.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = trim((string) ($input['id'] ?? ''));
$target = trim((string) ($input['status'] ?? ''));
$metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];

$allowed = [
    'created' => ['available','assigned','cancelled','voided'],
    'available' => ['assigned','scheduled','cancelled','voided'],
    'assigned' => ['scheduled','sent','cancelled','voided'],
    'scheduled' => ['sent','cancelled','voided'],
    'sent' => ['delivered','viewed','claim_pending','expired','cancelled'],
    'delivered' => ['viewed','claim_pending','expired','cancelled'],
    'viewed' => ['claim_pending','verified','expired','cancelled'],
    'claim_pending' => ['verified','expired','cancelled'],
    'verified' => ['redeemed','expired','cancelled'],
    'redeemed' => [],
    'expired' => [],
    'cancelled' => [],
    'refunded' => [],
    'voided' => [],
];

if ($id === '' || !isset($allowed[$target])) {
    mg_fail('Invalid PPPM transition.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT * FROM pppm_items
         WHERE public_id = ? AND (issuer_user_id = ? OR merchant_user_id = ? OR owner_user_id = ?)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$id, (int) $user['id'], (int) $user['id'], (int) $user['id']]);
    $item = $stmt->fetch();
    if (!$item) {
        mg_fail('PPPM item not found.', 404);
    }

    $from = (string) $item['status'];
    if ($from === $target) {
        $pdo->commit();
        mg_ok(['item' => mg_pppm_public_item($item)], 'PPPM status unchanged.');
    }
    if (!in_array($target, $allowed[$from] ?? [], true)) {
        mg_fail('This PPPM lifecycle transition is not allowed.', 409);
    }

    $timestampColumn = match ($target) {
        'assigned' => 'assigned_at',
        'sent' => 'sent_at',
        'delivered' => 'delivered_at',
        'viewed' => 'viewed_at',
        'claim_pending' => 'claimed_at',
        'redeemed' => 'redeemed_at',
        'cancelled', 'voided' => 'cancelled_at',
        default => null,
    };

    $sql = 'UPDATE pppm_items SET status = ?, version_no = version_no + 1, updated_at = NOW()';
    if ($timestampColumn !== null) {
        $sql .= ', ' . $timestampColumn . ' = NOW()';
    }
    $sql .= ' WHERE id = ?';
    $pdo->prepare($sql)->execute([$target, (int) $item['id']]);

    $updatedStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $updatedStmt->execute([(int) $item['id']]);
    $updated = $updatedStmt->fetch();
    mg_pppm_record_event($pdo, $updated, 'status_changed', $from, $target, (int) $user['id'], null, $metadata);
    $pdo->commit();

    mg_audit('pppm.item_transitioned', 'pppm_item', ['item_id' => $id, 'from' => $from, 'to' => $target], (int) $user['id']);
    mg_ok(['item' => mg_pppm_public_item($updated)], 'PPPM status updated.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'pppm.transition_failed', 'PPPM transition failed.', ['item_id' => $id, 'target' => $target], (int) $user['id']);
    mg_fail('Unable to update the PPPM item.', 500);
}
