<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once __DIR__ . '/_crm_ops.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('gift.message.send');
$pdo = mg_db();

try {
    if ($method === 'GET') {
        $threadPublicId = trim((string)($_GET['thread_id'] ?? $_GET['id'] ?? ''));
        $thread = mg_message_crm_ops_thread($pdo, $threadPublicId, (int)$user['id']);
        mg_ok(['crm_ops' => mg_message_crm_ops_get($pdo, (int)$thread['id'], (int)$user['id'])]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    $input = mg_input();
    mg_require_csrf_for_write($input);
    $threadPublicId = trim((string)($input['thread_id'] ?? $input['id'] ?? ''));
    $thread = mg_message_crm_ops_thread($pdo, $threadPublicId, (int)$user['id']);
    $action = strtolower(trim((string)($input['action'] ?? 'get')));

    if ($action === 'get') {
        mg_ok(['crm_ops' => mg_message_crm_ops_get($pdo, (int)$thread['id'], (int)$user['id'])]);
    }

    if ($action === 'save_draft') {
        $body = mg_message_crm_ops_clean_body($input['body'] ?? '', 4000, 'Draft');
        mg_message_crm_ops_save_draft($pdo, (int)$thread['id'], (int)$user['id'], $body);
        mg_ok(['crm_ops' => mg_message_crm_ops_get($pdo, (int)$thread['id'], (int)$user['id'])]);
    }

    if ($action === 'save_note') {
        $body = mg_message_crm_ops_clean_body($input['note'] ?? $input['body'] ?? '', 12000, 'Internal note');
        mg_message_crm_ops_save_note($pdo, (int)$thread['id'], (int)$user['id'], $body);
        mg_ok(['crm_ops' => mg_message_crm_ops_get($pdo, (int)$thread['id'], (int)$user['id'])]);
    }

    if ($action === 'update_state') {
        mg_message_crm_ops_update_state($pdo, (int)$thread['id'], (int)$user['id'], $input);
        mg_ok(['crm_ops' => mg_message_crm_ops_get($pdo, (int)$thread['id'], (int)$user['id'])]);
    }

    if ($action === 'clear_draft') {
        mg_message_crm_ops_save_draft($pdo, (int)$thread['id'], (int)$user['id'], '');
        mg_ok(['crm_ops' => mg_message_crm_ops_get($pdo, (int)$thread['id'], (int)$user['id'])]);
    }

    mg_fail('Unknown CRM message operation.', 422);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_fail('Unable to save message CRM operation.', 500, ['detail' => $error->getMessage()]);
}
