<?php
declare(strict_types=1);

require_once __DIR__ . '/_conversations.php';

$user = mg_require_api_user();
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'POST') {
        $input = mg_input();
        mg_require_csrf_for_write($input);
        mg_rate_limit('world_canvas.conversation_resolve', 'user:' . (int)$user['id'], 120, 60);
        $conversation = mg_world_conversation_resolve($pdo, $user, $input);
        mg_ok(['conversation' => $conversation], 'Conversation ready.');
    }

    if ($method === 'GET') {
        mg_rate_limit('world_canvas.conversation_read', 'user:' . (int)$user['id'], 180, 60);
        $conversation = mg_world_conversation_load_by_public_id($pdo, (string)($_GET['conversation_id'] ?? $_GET['id'] ?? ''));
        mg_ok(['conversation' => mg_world_conversation_project($pdo, $conversation, null)]);
    }

    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.conversations_failed', 'World Canvas conversation endpoint failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to load World Canvas conversation.', 500);
}
