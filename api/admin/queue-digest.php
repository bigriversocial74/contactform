<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';

$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$canRead = mg_admin_account_actor_has($actor, 'admin.support_queue.view') || mg_admin_account_actor_has($actor, 'admin.user_notes.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage');
if (!$canRead) {
    mg_fail('Permission denied.', 403);
}

if ($method === 'GET') {
    mg_rate_limit('admin.queue_digest.read', 'user:' . $actorId, 120, 60);
    $created = mg_queue_seed_due_notices($pdo, $actorId);
    $payload = mg_queue_digest($pdo, $actorId);
    $payload['notifications_created'] = $created;
    mg_ok($payload, 'Queue digest loaded.');
}

if ($method === 'POST') {
    mg_rate_limit('admin.queue_digest.write', 'user:' . $actorId, 30, 60);
    mg_require_csrf_for_write(mg_input());
    $created = mg_queue_seed_due_notices($pdo, $actorId);
    $payload = mg_queue_digest($pdo, $actorId);
    mg_queue_notice_create($pdo, [
        'actor_user_id' => $actorId,
        'notification_type' => 'digest',
        'severity' => 'info',
        'title' => 'Daily queue digest generated',
        'message' => 'An admin generated the daily follow-up queue digest.',
        'metadata' => ['notifications_created' => $created, 'alerts' => $payload['alerts']],
    ]);
    mg_audit('admin_queue_digest_generate', 'user', ['notifications_created' => $created], $actorId);
    mg_event('admin.queue_digest.generate', ['admin_user_id' => $actorId, 'notifications_created' => $created], $actorId);
    mg_security_log('info', 'admin.queue_digest.generated', 'Admin queue digest generated.', ['notifications_created' => $created], $actorId);
    $payload['notifications_created'] = $created;
    mg_ok($payload, 'Queue digest generated.');
}

mg_fail('Method not allowed.', 405);
