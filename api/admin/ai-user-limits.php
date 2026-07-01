<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ai/_ai.php';
require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-admin-limits.php';

$admin = mg_require_permission('admin.settings.manage');
$adminId = (int)$admin['id'];
$pdo = mg_db();

function mg_admin_ai_limit_target_user(PDO $pdo, mixed $value): array
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || (int)$id < 1) mg_fail('Choose a valid user.', 422);
    $stmt = $pdo->prepare('SELECT id,email,full_name,display_name,status FROM users WHERE id=? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) mg_fail('User not found.', 404);
    return $row;
}

function mg_admin_ai_limit_public_user(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'display_name' => (string)($row['display_name'] ?: $row['full_name'] ?: $row['email']),
        'status' => (string)$row['status'],
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    mg_rate_limit('admin.ai_user_limits.read', 'user:' . $adminId, 120, 60);
    $target = mg_admin_ai_limit_target_user($pdo, $_GET['user_id'] ?? null);
    $providerKey = mg_agent_admin_limit_clean($_GET['provider_key'] ?? 'anthropic', 80) ?: 'anthropic';
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok([
        'user' => mg_admin_ai_limit_public_user($target),
        'limits' => mg_agent_admin_limit_public($pdo, (int)$target['id'], $providerKey),
    ]);
}

mg_require_method('POST');
mg_rate_limit('admin.ai_user_limits.write', 'user:' . $adminId, 40, 300);
$input = mg_input();
mg_require_csrf_for_write($input);
$target = mg_admin_ai_limit_target_user($pdo, $input['user_id'] ?? null);
$providerKey = mg_agent_admin_limit_clean($input['provider_key'] ?? 'anthropic', 80) ?: 'anthropic';

try {
    $limits = mg_agent_admin_limit_save($pdo, (int)$target['id'], $providerKey, $input, $adminId);
    mg_audit('admin.ai_user_limits_updated', 'ai_user_limits', [
        'target_user_id' => (int)$target['id'],
        'provider_key' => $providerKey,
        'requests_per_hour' => $limits['requests_per_hour'],
        'requests_per_day' => $limits['requests_per_day'],
        'enabled' => $limits['enabled'],
    ], $adminId);
    mg_security_log('info', 'admin.ai_user_limits.updated', 'Admin updated user AI limits.', [
        'target_user_id' => (int)$target['id'],
        'provider_key' => $providerKey,
        'requests_per_hour' => $limits['requests_per_hour'],
        'requests_per_day' => $limits['requests_per_day'],
        'enabled' => $limits['enabled'],
    ], $adminId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(['user' => mg_admin_ai_limit_public_user($target), 'limits' => $limits], 'AI user limits saved.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.ai_user_limits.failed', 'Unable to save user AI limits.', [
        'target_user_id' => (int)$target['id'],
        'provider_key' => $providerKey,
        'exception_class' => $error::class,
    ], $adminId);
    mg_fail('Unable to save AI user limits.', 500);
}
