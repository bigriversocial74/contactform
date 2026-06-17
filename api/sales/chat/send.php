<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_crm_require_sales_access('sales.leads.view_own');

$recipientUserId = (int) ($input['recipient_user_id'] ?? 0);
$message = trim((string) ($input['message'] ?? ''));
if ($recipientUserId <= 0 || $recipientUserId === (int) $user['id']) {
    mg_fail('Valid recipient is required.', 422);
}
if ($message === '') {
    mg_fail('Message is required.', 422, ['message' => 'Message is required.']);
}
if (mb_strlen($message) > 4000) {
    mg_fail('Message is too long.', 422, ['message' => 'Maximum 4000 characters.']);
}

$roster = mg_db()->prepare('SELECT user_id FROM sales_roster WHERE user_id = ? AND status IN ("active","paused") LIMIT 1');
$roster->execute([$recipientUserId]);
if (!$roster->fetch()) {
    mg_fail('Recipient is not on the sales roster.', 422);
}

$presence = mg_db()->prepare('SELECT status, last_seen_at FROM sales_presence WHERE user_id = ? LIMIT 1');
$presence->execute([$recipientUserId]);
$presenceRow = $presence->fetch();
$isOnline = $presenceRow && (string) $presenceRow['status'] === 'online' && strtotime((string) $presenceRow['last_seen_at']) >= time() - 120;

$stmt = mg_db()->prepare(
    'INSERT INTO employee_chat_messages (public_id, sender_user_id, recipient_user_id, message, sent_while_offline, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    mg_crm_public_id('ecm'),
    (int) $user['id'],
    $recipientUserId,
    $message,
    $isOnline ? 0 : 1,
]);

mg_ok(['message_id' => (int) mg_db()->lastInsertId(), 'sent_while_offline' => !$isOnline], $isOnline ? 'Message sent.' : 'Offline note saved.', 201);
