<?php
declare(strict_types=1);

require_once __DIR__ . '/_public_donations_operations.php';

function mg_admin_public_donations_require_operations_user(bool $manage = false): array
{
    $actor = mg_current_user();
    if (!$actor) mg_fail('Authentication required.', 401);
    $permission = $manage
        ? 'admin.public_donations_operations.manage'
        : 'admin.public_donations_operations.view';
    if (!mg_admin_permission_user_has($actor, $permission)) {
        mg_security_log('warning', 'admin.public_donations_operations.permission_denied', 'Public Donations operations access was denied.', [
            'required_permission' => $permission,
            'access_mode' => $manage ? 'manage' : 'view',
        ], (int)$actor['id']);
        mg_fail(
            $manage
                ? 'You do not have permission to manage Public Donations operations.'
                : 'You do not have permission to view Public Donations operations.',
            403
        );
    }
    return $actor;
}

function mg_admin_public_donations_search_merchants_projection(PDO $pdo, string $query): array
{
    $query = trim($query);
    if ($query === '') return [];
    $like = '%' . mb_substr($query, 0, 100) . '%';
    $numeric = preg_match('/^[1-9][0-9]{0,18}$/', $query) === 1 ? (int)$query : 0;
    $stmt = $pdo->prepare(
        "SELECT u.id,u.email,u.full_name,u.display_name,u.status,
                COUNT(DISTINCT c.id) AS campaign_count,
                MAX(CASE WHEN r.slug='merchant' THEN 1 ELSE 0 END) AS merchant_role
           FROM users u
           LEFT JOIN user_roles ur ON ur.user_id=u.id
           LEFT JOIN roles r ON r.id=ur.role_id
           LEFT JOIN campaigns c ON c.merchant_user_id=u.id
          WHERE u.status='active'
            AND (r.slug='merchant' OR c.id IS NOT NULL)
            AND ((? > 0 AND u.id=?) OR u.email LIKE ? OR u.full_name LIKE ? OR u.display_name LIKE ?)
          GROUP BY u.id
          ORDER BY merchant_role DESC,campaign_count DESC,u.id DESC
          LIMIT 20"
    );
    $stmt->execute([$numeric, $numeric, $like, $like, $like]);
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'display_name' => (string)($row['display_name'] ?: $row['full_name'] ?: $row['email']),
        'status' => (string)$row['status'],
        'campaign_count' => (int)$row['campaign_count'],
        'merchant_role' => (bool)$row['merchant_role'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_public_donations_recent_operations_projection(PDO $pdo, array $schema): array
{
    if (empty($schema['campaign_donation_operations']) || empty($schema['campaigns']) || empty($schema['users'])) return [];
    $stmt = $pdo->query(
        "SELECT operation.public_id,operation.operation_kind,operation.operation_mode,operation.status,
                operation.requested_quantity,operation.completed_quantity,operation.error_code,
                operation.created_at,operation.completed_at,campaign.public_id AS campaign_public_id,
                campaign.title AS campaign_name,user.email AS merchant_email,user.display_name AS merchant_display_name,
                user.full_name AS merchant_full_name
           FROM campaign_donation_operations operation
           INNER JOIN campaigns campaign ON campaign.id=operation.campaign_id
           INNER JOIN users user ON user.id=operation.merchant_user_id
          ORDER BY operation.id DESC LIMIT 30"
    );
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'kind' => (string)$row['operation_kind'],
        'mode' => (string)$row['operation_mode'],
        'status' => (string)$row['status'],
        'requested_quantity' => (int)$row['requested_quantity'],
        'completed_quantity' => (int)$row['completed_quantity'],
        'error_code' => $row['error_code'] !== null ? (string)$row['error_code'] : null,
        'created_at' => (string)$row['created_at'],
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
        'campaign' => [
            'id' => (string)$row['campaign_public_id'],
            'name' => (string)$row['campaign_name'],
        ],
        'merchant' => [
            'email' => (string)$row['merchant_email'],
            'display_name' => (string)($row['merchant_display_name'] ?: $row['merchant_full_name'] ?: $row['merchant_email']),
        ],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_public_donations_read_projection(PDO $pdo, array $actor, string $merchantQuery = ''): array
{
    $schema = mg_admin_public_donations_schema_status($pdo);
    $foundation = array_intersect_key($schema, array_flip(mg_admin_public_donations_required_tables()));
    $rollout = mg_public_donations_rollout_config(true);
    $environment = mg_public_donations_environment_rollout();
    $canManage = mg_admin_permission_user_has($actor, 'admin.public_donations_operations.manage');
    $checks = [
        ['key' => 'operations_schema', 'label' => 'Operations schema', 'ready' => !empty($schema['public_donations_operations_settings']) && !empty($schema['public_donations_reconciliation_receipts'])],
        ['key' => 'foundation_schema', 'label' => 'Public Donations foundation', 'ready' => !in_array(false, $foundation, true)],
        ['key' => 'reconciliation_engine', 'label' => 'Reconciliation engine', 'ready' => function_exists('mg_public_donations_reconcile_apply')],
        ['key' => 'rollout_configuration', 'label' => 'Rollout configuration', 'ready' => in_array($rollout['state'], MG_PUBLIC_DONATIONS_FEATURE_STATES, true)],
        ['key' => 'audit_logging', 'label' => 'Audit logging', 'ready' => mg_admin_public_donations_table($pdo, 'audit_logs') && mg_admin_public_donations_table($pdo, 'security_logs')],
    ];

    return [
        'version' => 'public-donations-operations-admin-v1',
        'summary' => mg_admin_public_donations_summary($pdo, $schema),
        'readiness' => [
            'ready' => !in_array(false, array_column($checks, 'ready'), true),
            'checks' => $checks,
            'tables' => $schema,
        ],
        'rollout' => $rollout + [
            'selected_merchants' => mg_admin_public_donations_selected_merchants($pdo, $rollout['selected_merchant_ids']),
        ],
        'environment' => $environment,
        'merchant_search' => $canManage
            ? mg_admin_public_donations_search_merchants_projection($pdo, $merchantQuery)
            : [],
        'recent_operations' => mg_admin_public_donations_recent_operations_projection($pdo, $schema),
        'receipts' => mg_admin_public_donations_receipts($pdo, $schema),
        'permissions' => [
            'view' => true,
            'manage' => $canManage,
            'repair' => $canManage && mg_admin_public_donations_actor_can_repair($actor),
        ],
        'repair_modes' => MG_PUBLIC_DONATIONS_REPAIR_MODES,
        'confirmation' => [
            'rollout' => 'UPDATE PUBLIC DONATIONS ROLLOUT',
            'environment' => 'RETURN TO ENVIRONMENT CONFIG',
            'repair' => 'REPAIR PUBLIC DONATIONS',
        ],
    ];
}
