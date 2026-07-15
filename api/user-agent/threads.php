<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$user = mg_require_api_user();
$pdo = mg_db();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $threadId = mg_personal_agent_text($_GET['thread_id'] ?? '', 80);
    mg_user_agent_api_run(static function () use ($pdo, $user, $threadId): array {
        if ($threadId !== '') return mg_personal_agent_thread_detail($pdo, (int) $user['id'], $threadId);
        return ['threads' => mg_personal_agent_threads($pdo, (int) $user['id'])];
    });
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);

$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(mg_personal_agent_text($input['action'] ?? '', 32));

mg_user_agent_api_run(static function () use ($pdo, $user, $input, $action): array {
    $userId = (int) $user['id'];
    return match ($action) {
        'create' => ['thread' => mg_personal_agent_create_thread($pdo, $userId)],
        'delete' => mg_personal_agent_delete_thread($pdo, $userId, (string) ($input['thread_id'] ?? '')),
        default => throw new InvalidArgumentException('Unsupported chat action.'),
    };
});
