<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.delivery.schedule');
$input = mg_input();
mg_require_csrf_for_write($input);

$itemId = trim((string) ($input['id'] ?? ''));
$channel = mg_pppm_delivery_channel($input['channel'] ?? 'link');
$destination = mg_pppm_delivery_destination($input['destination'] ?? '');
$scheduledForInput = trim((string) ($input['scheduled_for'] ?? ''));
$timezone = trim((string) ($input['timezone'] ?? 'UTC')) ?: 'UTC';
$timestamp = strtotime($scheduledForInput);
if ($itemId === '' || !$timestamp || $timestamp < time() - 60) mg_fail('Invalid delivery schedule.', 422);
if (mb_strlen($timezone) > 64) mg_fail('Invalid timezone.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $item = mg_pppm_delivery_item_for_update($pdo, (int) $user['id'], $itemId);
    if (!in_array((string) $item['status'], ['assigned','scheduled'], true)) mg_fail('Assign the item before scheduling delivery.', 409);

    $pdo->prepare("UPDATE pppm_delivery_schedules SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE pppm_item_id = ? AND status = 'scheduled'")
        ->execute([(int) $item['id']]);

    $assignmentStmt = $pdo->prepare("SELECT id FROM pppm_assignments WHERE pppm_item_id = ? AND status IN ('pending','accepted') ORDER BY id DESC LIMIT 1");
    $assignmentStmt->execute([(int) $item['id']]);
    $assignmentId = $assignmentStmt->fetchColumn() ?: null;

    $publicId = mg_pppm_uuid();
    $scheduledFor = date('Y-m-d H:i:s', $timestamp);
    $pdo->prepare(
        "INSERT INTO pppm_delivery_schedules
         (public_id, pppm_item_id, assignment_id, channel, destination, scheduled_for, timezone, status, created_by_user_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW(), NOW())"
    )->execute([$publicId, (int) $item['id'], $assignmentId, $channel, $destination, $scheduledFor, $timezone, (int) $user['id']]);

    $updated = mg_pppm_delivery_set_status($pdo, $item, 'scheduled', (int) $user['id'], 'delivery_scheduled', [
        'schedule_id' => $publicId,
        'channel' => $channel,
        'scheduled_for' => $scheduledFor,
        'timezone' => $timezone,
    ]);
    $pdo->commit();
    mg_ok(['schedule_id' => $publicId, 'item' => mg_pppm_public_item($updated), 'scheduled_for' => $scheduledFor], 'Delivery scheduled.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to schedule delivery.', 500);
}
