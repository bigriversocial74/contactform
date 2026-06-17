<?php
/**
 * Microgifter super-admin bootstrap utility.
 *
 * Usage from project root or public_html with PHP CLI:
 *   php scripts/bootstrap_super_admin.php
 *
 * Safety rules:
 * - Promotes user #1 only if no super_admin exists yet.
 * - Does not hardcode super_admin access forever; the role table remains truth.
 * - Writes an audit log when the bootstrap is applied.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/api/security.php';

try {
    $pdo = mg_db();

    $existing = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM user_roles ur
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE r.slug = 'super_admin'"
    )->fetch();

    if ((int) ($existing['total'] ?? 0) > 0) {
        echo "Super admin already exists. No changes made.\n";
        exit(0);
    }

    $userStmt = $pdo->prepare('SELECT id, email FROM users WHERE id = 1 LIMIT 1');
    $userStmt->execute();
    $user = $userStmt->fetch();
    if (!$user) {
        echo "User #1 does not exist. Create the platform owner account first.\n";
        exit(1);
    }

    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'super_admin' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch();
    if (!$role) {
        echo "Role super_admin does not exist. Import the Stage 1 schema/seeds first.\n";
        exit(1);
    }

    $pdo->beginTransaction();
    $assign = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())');
    $assign->execute([1, (int) $role['id']]);

    $audit = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, metadata_json, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $audit->execute([
        1,
        'system.bootstrap_super_admin',
        'user',
        json_encode(['bootstrap_user_id' => 1, 'role' => 'super_admin', 'source' => 'scripts/bootstrap_super_admin.php'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'system',
        'cli-bootstrap',
    ]);

    $pdo->commit();
    echo "User #1 promoted to super_admin: " . ($user['email'] ?? 'unknown') . "\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[microgifter-bootstrap-super-admin] ' . $e->getMessage());
    echo "Unable to complete super admin bootstrap. Check PHP error logs.\n";
    exit(1);
}
