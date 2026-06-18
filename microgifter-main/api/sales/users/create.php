<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/crm.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$salesUser = mg_crm_require_sales_access('sales.leads.update_status');

$email = strtolower(trim((string) ($input['email'] ?? '')));
$fullName = trim((string) ($input['full_name'] ?? $input['name'] ?? ''));
$sourceLeadId = (int) ($input['lead_id'] ?? 0);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_fail('Valid email is required.', 422, ['email' => 'Valid email is required.']);
}
if ($fullName === '') {
    mg_fail('Full name is required.', 422, ['full_name' => 'Full name is required.']);
}

try {
    $pdo = mg_db();
    $existing = $pdo->prepare('SELECT id, email, full_name, display_name, status FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    $user = $existing->fetch();

    if (!$user) {
        $pdo->beginTransaction();
        $randomPassword = bin2hex(random_bytes(24));
        $hash = password_hash($randomPassword, PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, display_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, "active", NOW(), NOW())');
        $insert->execute([$email, $hash, $fullName, $fullName]);
        $userId = (int) $pdo->lastInsertId();
        mg_assign_default_role($userId, 'customer');
        $profile = $pdo->prepare('INSERT IGNORE INTO user_profiles (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())');
        $profile->execute([$userId]);
        $pdo->commit();
        $user = ['id' => $userId, 'email' => $email, 'full_name' => $fullName, 'display_name' => $fullName, 'status' => 'active'];
        mg_audit('sales.user.created', 'user', ['created_user_id' => $userId, 'source_lead_id' => $sourceLeadId], (int) $salesUser['id']);
    }

    if ($sourceLeadId > 0) {
        mg_crm_record_event($sourceLeadId, 'crm_lead.user_created', null, null, (int) $salesUser['id'], 'Sales created/linked a user.', ['user_id' => (int) $user['id']]);
    }

    mg_ok(['user' => $user], 'User record ready.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'sales.user_create_failed', 'Sales user create failed.', ['exception' => $e->getMessage()], (int) $salesUser['id']);
    mg_fail('Unable to create user.', 500);
}
