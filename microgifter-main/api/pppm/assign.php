<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.assign');
$input = mg_input();
mg_require_csrf_for_write($input);

$itemId = trim((string) ($input['id'] ?? ''));
$toUserId = isset($input['to_user_id']) && $input['to_user_id'] !== '' ? (int) $input['to_user_id'] : null;
$externalId = trim((string) ($input['to_external_id'] ?? '')) ?: null;
$toName = trim((string) ($input['to_name'] ?? '')) ?: null;
if ($itemId === '' || ($toUserId === null && $externalId === null)) {
    mg_fail('Choose a recipient.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $item = mg_pppm_delivery_item_for_update($pdo, (int) $user['id'], $itemId);
    if (!in_array((string) $item['status'], ['created','available','assigned'], true)) {
        mg_fail('Item cannot be assigned in its current state.', 409);
    }

    $pdo->prepare("UPDATE pppm_assignments SET status = 'replaced', updated_at = NOW() WHERE pppm_item_id = ? AND status IN ('pending','accepted')")
        ->execute([(int) $item['id']]);

    $assignmentId = mg_pppm_uuid();
    $accepted = $toUserId !== null;
    $pdo->prepare(
        'INSERT INTO pppm_assignments
         (public_id, pppm_item_id, assignment_type, from_user_id, to_user_id, to_external_id, to_name,
          status, created_by_user_id, accepted_by_user_id, accepted_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    )->execute([
        $assignmentId, (int) $item['id'], 'direct', $item['owner_user_id'] ?? $item['issuer_user_id'] ?? null,
        $toUserId, $externalId, $toName, $accepted ? 'accepted' : 'pending', (int) $user['id'],
        $accepted ? $toUserId : null, $accepted ? date('Y-m-d H:i:s') : null,
    ]);

    $pdo->prepare(
        "UPDATE pppm_items SET recipient_user_id = ?, recipient_external_id = ?, status = 'assigned',
         assigned_at = NOW(), version_no = version_no + 1, updated_at = NOW() WHERE id = ?"
    )->execute([$toUserId, $externalId, (int) $item['id']]);

    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $item['id']]);
    $updated = $stmt->fetch();
    mg_pppm_record_event($pdo, $updated, 'assigned', (string) $item['status'], 'assigned', (int) $user['id'], null, [
        'assignment_id' => $assignmentId,
        'recipient_user_id' => $toUserId,
        'recipient_external_id' => $externalId,
    ]);
    $pdo->commit();

    mg_audit('pppm.item_assigned', 'pppm_item', ['item_id' => $itemId, 'assignment_id' => $assignmentId], (int) $user['id']);
    mg_ok(['item' => mg_pppm_public_item($updated), 'assignment_id' => $assignmentId], 'PPPM item assigned.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to assign this PPPM item.', 500);
}
