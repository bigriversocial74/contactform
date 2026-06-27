<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/intelligence/_intelligence.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

$mgDesignStudioEndpoint = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (in_array($mgDesignStudioEndpoint, ['brand-kit.php', 'design-export.php', 'design-studio-assets.php', 'qr-library.php'], true)) {
    require_once __DIR__ . '/_design_studio_guard.php';
    if (function_exists('mg_db') && function_exists('mg_design_studio_require_tables')) {
        try {
            mg_design_studio_require_tables(mg_db(), mg_design_studio_core_tables());
        } catch (Throwable $e) {
            if (function_exists('mg_security_log')) {
                mg_security_log('error', 'merchant.design_studio_setup_check_failed', 'Design Studio setup check failed.', ['exception_type' => get_class($e)], null);
            }
            if (function_exists('mg_fail')) mg_fail('Design Studio setup is incomplete. Import database/stage_19_design_studio_qr_library.sql before using this endpoint.', 503);
            throw $e;
        }
    }
}

function mg_merchant_uuid(): string
{
    return mg_intelligence_uuid();
}

function mg_merchant_email_hash(string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) mg_fail('Invalid team email address.', 422);
    $secret = trim((string) getenv('MG_MERCHANT_INVITE_SECRET')) ?: trim((string) getenv('MG_DISTRIBUTION_HASH_SECRET'));
    if ($secret === '') mg_fail('Merchant invitation hashing is not configured.', 503);
    return hash_hmac('sha256', $email, $secret);
}

function mg_merchant_package_context(PDO $pdo, array $user): array
{
    return mg_user_package_context($pdo, $user);
}

function mg_merchant_require_access(PDO $pdo, array $user): array
{
    return mg_package_require_merchant_access($pdo, $user, 'Upgrade to a paid Microgifter package to use merchant tools.');
}

function mg_merchant_require_permission(string $permission): array
{
    $user = mg_require_api_user();
    $pdo = mg_db();
    $hasPermission = mg_api_user_has_permission($user, $permission);
    $hasPackageAccess = mg_user_has_merchant_access($user, $pdo);
    if ($hasPermission || $hasPackageAccess) {
        mg_merchant_require_access($pdo, $user);
        return $user;
    }
    mg_audit('permission_denied', 'security', ['permission' => $permission], (int) $user['id']);
    mg_security_log('warning', 'permission.denied', 'Permission denied.', ['permission' => $permission], (int) $user['id']);
    mg_fail('Merchant access is not enabled for this account.', 403);
}

function mg_merchant_workspace(PDO $pdo, int $userId, bool $forUpdate = false): array
{
    $sql = 'SELECT * FROM merchant_workspaces WHERE merchant_user_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $workspace = $stmt->fetch();
    if (!$workspace) mg_fail('Merchant workspace has not been created.', 404);
    return $workspace;
}

function mg_merchant_ensure_workspace(PDO $pdo, array $user): array
{
    mg_merchant_require_access($pdo, $user);

    $stmt = $pdo->prepare('SELECT * FROM merchant_workspaces WHERE merchant_user_id = ? LIMIT 1');
    $stmt->execute([(int) $user['id']]);
    $workspace = $stmt->fetch();
    if ($workspace) return $workspace;

    $displayName = trim((string) ($user['display_name'] ?? $user['full_name'] ?? 'Merchant workspace')) ?: 'Merchant workspace';
    $publicId = mg_merchant_uuid();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO merchant_workspaces (public_id,merchant_user_id,display_name,status,eligibility_status,onboarding_percent,created_at,updated_at) VALUES (?, ?, ?, \'draft\', \'not_started\', 0, NOW(), NOW())')
            ->execute([$publicId, (int) $user['id'], $displayName]);
        $workspaceId = (int) $pdo->lastInsertId();
        $steps = [
            ['business_profile', 1], ['eligibility', 2], ['first_location', 3], ['claim_configuration', 4],
            ['first_product', 5], ['storefront', 6], ['payment_readiness', 7], ['test_pppm', 8],
            ['test_claim', 9], ['analytics_verification', 10], ['beta_readiness', 11],
        ];
        $insert = $pdo->prepare('INSERT INTO merchant_onboarding_steps (workspace_id,step_key,step_order,status,created_at,updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        foreach ($steps as $index => $step) $insert->execute([$workspaceId, $step[0], $step[1], $index === 0 ? 'available' : 'locked']);
        $pdo->prepare('INSERT INTO merchant_payment_readiness (workspace_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([$workspaceId]);
        $pdo->prepare('INSERT INTO merchant_team_members (public_id,workspace_id,user_id,display_name,role_key,status,invited_by_user_id,invited_at,accepted_at,created_at,updated_at) VALUES (?,?,?,?,\'owner\',\'active\',?,NOW(),NOW(),NOW(),NOW())')
            ->execute([mg_merchant_uuid(), $workspaceId, (int) $user['id'], $displayName, (int) $user['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail('Unable to initialize merchant workspace.', 500);
    }
    return mg_merchant_workspace($pdo, (int) $user['id']);
}

function mg_merchant_recalculate_onboarding(PDO $pdo, int $workspaceId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) total_steps,SUM(status = \'completed\') completed_steps FROM merchant_onboarding_steps WHERE workspace_id = ?');
    $stmt->execute([$workspaceId]);
    $counts = $stmt->fetch() ?: ['total_steps' => 0, 'completed_steps' => 0];
    $percent = (int) round(100 * ((int) $counts['completed_steps']) / max(1, (int) $counts['total_steps']));
    $pdo->prepare('UPDATE merchant_workspaces SET onboarding_percent=?,updated_at=NOW() WHERE id=?')->execute([$percent, $workspaceId]);
    return $percent;
}
