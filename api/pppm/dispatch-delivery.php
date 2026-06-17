<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.delivery.dispatch');
$input = mg_input();
mg_require_csrf_for_write($input);
$scheduleId = strtolower(trim((string) ($input['schedule_id'] ?? '')));
$provider = trim((string) ($input['provider'] ?? 'internal')) ?: 'internal';
if ($scheduleId === '' || mb_strlen($provider) > 100) mg_fail('Invalid dispatch request.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT s.*, p.issuer_user_id, p.owner_user_id
         FROM pppm_delivery_schedules s
         INNER JOIN pppm_items p ON p.id = s.pppm_item_id
         WHERE s.public_id = ? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$scheduleId]);
    $schedule = $stmt->fetch();
    if (!$schedule) mg_fail('Delivery schedule not found.', 404);
    if (!in_array((int) $user['id'], [(int) $schedule['created_by_user_id'], (int) $schedule['issuer_user_id'], (int) $schedule['owner_user_id']], true)) mg_fail('Delivery access denied.', 403);
    if ((string) $schedule['status'] !== 'scheduled') mg_fail('Delivery schedule is not dispatchable.', 409);

    $deliveryId = mg_pppm_uuid();
    $pdo->prepare(
        "INSERT INTO pppm_deliveries
         (public_id, pppm_item_id, channel, destination, status, provider, queued_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'queued', ?, NOW(), NOW(), NOW())"
    )->execute([$deliveryId, (int) $schedule['pppm_item_id'], $schedule['channel'], $schedule['destination'], $provider]);
    $deliveryDbId = (int) $pdo->lastInsertId();

    $attemptId = mg_pppm_uuid();
    $pdo->prepare(
        "INSERT INTO pppm_delivery_attempts
         (public_id, delivery_id, schedule_id, attempt_number, provider, status, created_at, updated_at)
         VALUES (?, ?, ?, 1, ?, 'queued', NOW(), NOW())"
    )->execute([$attemptId, $deliveryDbId, (int) $schedule['id'], $provider]);

    $pdo->prepare("UPDATE pppm_delivery_schedules SET status = 'processing', processed_at = NOW(), updated_at = NOW() WHERE id = ?")
        ->execute([(int) $schedule['id']]);
    $itemStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ?');
    $itemStmt->execute([(int) $schedule['pppm_item_id']]);
    $item = $itemStmt->fetch();
    mg_pppm_record_event($pdo, $item, 'delivery_queued', (string) $item['status'], (string) $item['status'], (int) $user['id'], null, [
        'schedule_id' => $scheduleId,
        'delivery_id' => $deliveryId,
        'attempt_id' => $attemptId,
    ]);
    $pdo->commit();
    mg_ok(['schedule_id' => $scheduleId, 'delivery_id' => $deliveryId, 'attempt_id' => $attemptId, 'status' => 'queued'], 'Delivery queued.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to dispatch delivery.', 500);
}
