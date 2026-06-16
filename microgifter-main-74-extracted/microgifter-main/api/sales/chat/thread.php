<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

$user = mg_crm_require_sales_access('sales.leads.view_own');
$peerUserId = (int) ($_GET['user_id'] ?? 0);
if ($peerUserId <= 0 || $peerUserId === (int) $user['id']) {
    mg_fail('Valid sales user is required.', 422);
}

$stmt = mg_db()->prepare(
    'SELECT id, public_id, sender_user_id, recipient_user_id, message, sent_while_offline, read_at, created_at
     FROM employee_chat_messages
     WHERE (sender_user_id = ? AND recipient_user_id = ?)
        OR (sender_user_id = ? AND recipient_user_id = ?)
     ORDER BY created_at ASC, id ASC
     LIMIT 200'
);
$stmt->execute([(int) $user['id'], $peerUserId, $peerUserId, (int) $user['id']]);
$messages = $stmt->fetchAll() ?: [];

$mark = mg_db()->prepare('UPDATE employee_chat_messages SET read_at = NOW() WHERE sender_user_id = ? AND recipient_user_id = ? AND read_at IS NULL');
$mark->execute([$peerUserId, (int) $user['id']]);

mg_ok(['messages' => $messages], 'Employee chat thread.');
