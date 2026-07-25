<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-reconciliation.php';

final class MgAdminPublicDonationsOperationsException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function mg_admin_public_donations_require_user(): array
{
    return mg_require_permission('admin.settings.manage');
}

function mg_admin_public_donations_actor_can_repair(array $actor): bool
{
    $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
    return in_array('super_admin', $roles, true)
        || mg_admin_permission_user_has($actor, 'admin.public_donations_operations.repair')
        || mg_admin_permission_user_has($actor, 'admin.admin_agent.execute');
}

function mg_admin_public_donations_text(mixed $value, int $min, int $max, string $label): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        throw new MgAdminPublicDonationsOperationsException($label . " must be between {$min} and {$max} characters.");
    }
    return $text;
}

function mg_admin_public_donations_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_public_donations_required_tables(): array
{
    return [
        'campaigns',
        'reward_templates',
        'campaign_community_assignments',
        'campaign_donation_operations',
        'campaign_donation_batches',
        'campaign_donation_rewards',
        'wallet_items',
        'pppm_items',
        'microgift_instances',
        'microgift_inbox_items',
        'users',
        'user_roles',
        'roles',
    ];
}

function mg_admin_public_donations_schema_status(PDO $pdo): array
{
    $tables = [];
    foreach (mg_admin_public_donations_required_tables() as $table) {
        $tables[$table] = mg_admin_public_donations_table($pdo, $table);
    }
    $tables['public_donations_operations_settings'] = mg_admin_public_donations_table($pdo, 'public_donations_operations_settings');
    $tables['public_donations_reconciliation_receipts'] = mg_admin_public_donations_table($pdo, 'public_donations_reconciliation_receipts');
    return $tables;
}

function mg_admin_public_donations_selected_merchants(PDO $pdo, array $ids): array
{
    if ($ids === []) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT u.id,u.email,u.full_name,u.display_name,u.status,
                COUNT(DISTINCT c.id) AS campaign_count,
                SUM(c.campaign_type='public_donation') AS public_donation_campaign_count
           FROM users u
           LEFT JOIN campaigns c ON c.merchant_user_id=u.id
          WHERE u.id IN ({$placeholders})
          GROUP BY u.id
          ORDER BY COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email),u.id"
    );
    $stmt->execute($ids);
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'display_name' => (string)($row['display_name'] ?: $row['full_name'] ?: $row['email']),
        'status' => (string)$row['status'],
        'campaign_count' => (int)$row['campaign_count'],
        'public_donation_campaign_count' => (int)$row['public_donation_campaign_count'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_public_donations_search_merchants(PDO $pdo, string $query): array
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
            AND (? > 0 AND u.id=? OR u.email LIKE ? OR u.full_name LIKE ? OR u.display_name LIKE ?)
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

function mg_admin_public_donations_validate_merchants(PDO $pdo, array $ids): array
{
    $ids = mg_public_donations_parse_merchant_ids($ids);
    if (count($ids) > 100) {
        throw new MgAdminPublicDonationsOperationsException('Selected rollout is limited to 100 merchants.');
    }
    if ($ids === []) return [];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id
           FROM users u
           LEFT JOIN user_roles ur ON ur.user_id=u.id
           LEFT JOIN roles r ON r.id=ur.role_id
           LEFT JOIN campaigns c ON c.merchant_user_id=u.id
          WHERE u.id IN ({$placeholders}) AND u.status='active' AND (r.slug='merchant' OR c.id IS NOT NULL)"
    );
    $stmt->execute($ids);
    $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($valid, SORT_NUMERIC);
    if ($valid !== $ids) {
        throw new MgAdminPublicDonationsOperationsException('One or more selected accounts are not active merchant accounts.', 409);
    }
    return $valid;
}

function mg_admin_public_donations_summary(PDO $pdo, array $schema): array
{
    $foundationReady = !in_array(false, array_intersect_key($schema, array_flip(mg_admin_public_donations_required_tables())), true);
    if (!$foundationReady) {
        return [
            'campaigns' => 0,
            'active_campaigns' => 0,
            'assignments' => 0,
            'gross_allocated' => 0,
            'recalled' => 0,
            'net_allocated' => 0,
            'failed_operations' => 0,
            'receipts' => 0,
            'receipts_with_drift' => 0,
        ];
    }

    $campaigns = $pdo->query(
        "SELECT COUNT(*) AS total,SUM(status='active') AS active FROM campaigns WHERE campaign_type='public_donation'"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $lifecycle = $pdo->query(
        "SELECT COUNT(*) AS gross,SUM(status='recalled') AS recalled,SUM(status='allocated') AS net FROM campaign_donation_rewards"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $assignments = (int)$pdo->query(
        "SELECT COUNT(*) FROM campaign_community_assignments WHERE status IN ('active','paused')"
    )->fetchColumn();
    $failed = (int)$pdo->query(
        "SELECT COUNT(*) FROM campaign_donation_operations WHERE status='failed'"
    )->fetchColumn();
    $receiptTotal = 0;
    $receiptDrift = 0;
    if (!empty($schema['public_donations_reconciliation_receipts'])) {
        $row = $pdo->query(
            'SELECT COUNT(*) AS total,SUM(unexplained_drift_after>0) AS drift FROM public_donations_reconciliation_receipts'
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $receiptTotal = (int)($row['total'] ?? 0);
        $receiptDrift = (int)($row['drift'] ?? 0);
    }

    return [
        'campaigns' => (int)($campaigns['total'] ?? 0),
        'active_campaigns' => (int)($campaigns['active'] ?? 0),
        'assignments' => $assignments,
        'gross_allocated' => (int)($lifecycle['gross'] ?? 0),
        'recalled' => (int)($lifecycle['recalled'] ?? 0),
        'net_allocated' => (int)($lifecycle['net'] ?? 0),
        'failed_operations' => $failed,
        'receipts' => $receiptTotal,
        'receipts_with_drift' => $receiptDrift,
    ];
}

function mg_admin_public_donations_recent_operations(PDO $pdo, array $schema): array
{
    if (empty($schema['campaign_donation_operations']) || empty($schema['campaigns']) || empty($schema['users'])) return [];
    $stmt = $pdo->query(
        "SELECT operation.public_id,operation.operation_kind,operation.operation_mode,operation.status,
                operation.requested_quantity,operation.completed_quantity,operation.error_code,
                operation.created_at,operation.completed_at,campaign.public_id AS campaign_public_id,
                campaign.name AS campaign_name,user.email AS merchant_email,user.display_name AS merchant_display_name,
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

function mg_admin_public_donations_receipts(PDO $pdo, array $schema): array
{
    if (empty($schema['public_donations_reconciliation_receipts'])) return [];
    $stmt = $pdo->query(
        "SELECT receipt_id,actor_user_id,merchant_user_id,campaign_reference,operation_reference,
                execution_mode,repair_modes_json,issues_before,repairable_before,report_only_before,
                repairs_applied,issues_after,unexplained_drift_after,checksum,reason,created_at
           FROM public_donations_reconciliation_receipts
          ORDER BY id DESC LIMIT 50"
    );
    return array_map(static function (array $row): array {
        $modes = json_decode((string)($row['repair_modes_json'] ?? '[]'), true);
        return [
            'id' => (string)$row['receipt_id'],
            'actor_user_id' => (int)$row['actor_user_id'],
            'merchant_user_id' => (int)$row['merchant_user_id'],
            'campaign_reference' => $row['campaign_reference'] !== null ? (string)$row['campaign_reference'] : null,
            'operation_reference' => $row['operation_reference'] !== null ? (string)$row['operation_reference'] : null,
            'mode' => (string)$row['execution_mode'],
            'repair_modes' => is_array($modes) ? array_values($modes) : [],
            'before' => [
                'issues' => (int)$row['issues_before'],
                'repairable' => (int)$row['repairable_before'],
                'report_only' => (int)$row['report_only_before'],
            ],
            'repairs_applied' => (int)$row['repairs_applied'],
            'issues_after' => (int)$row['issues_after'],
            'unexplained_drift_after' => (int)$row['unexplained_drift_after'],
            'checksum' => (string)$row['checksum'],
            'reason' => (string)$row['reason'],
            'created_at' => (string)$row['created_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_public_donations_read(PDO $pdo, array $actor, string $merchantQuery = ''): array
{
    $schema = mg_admin_public_donations_schema_status($pdo);
    $foundation = array_intersect_key($schema, array_flip(mg_admin_public_donations_required_tables()));
    $rollout = mg_public_donations_rollout_config(true);
    $environment = mg_public_donations_environment_rollout();
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
        'merchant_search' => mg_admin_public_donations_search_merchants($pdo, $merchantQuery),
        'recent_operations' => mg_admin_public_donations_recent_operations($pdo, $schema),
        'receipts' => mg_admin_public_donations_receipts($pdo, $schema),
        'permissions' => [
            'manage' => true,
            'repair' => mg_admin_public_donations_actor_can_repair($actor),
        ],
        'repair_modes' => MG_PUBLIC_DONATIONS_REPAIR_MODES,
        'confirmation' => [
            'rollout' => 'UPDATE PUBLIC DONATIONS ROLLOUT',
            'environment' => 'RETURN TO ENVIRONMENT CONFIG',
            'repair' => 'REPAIR PUBLIC DONATIONS',
        ],
    ];
}

function mg_admin_public_donations_update_rollout(PDO $pdo, array $actor, array $input): array
{
    if (!mg_admin_public_donations_table($pdo, 'public_donations_operations_settings')) {
        throw new MgAdminPublicDonationsOperationsException('Import the Public Donations Operations Admin SQL before changing rollout controls.', 409);
    }
    $actorId = (int)$actor['id'];
    $action = strtolower(trim((string)($input['action'] ?? '')));
    $reason = mg_admin_public_donations_text($input['reason'] ?? '', 8, 240, 'Action reason');
    $confirmation = trim((string)($input['confirmation'] ?? ''));

    if ($action === 'return_to_environment') {
        if ($confirmation !== 'RETURN TO ENVIRONMENT CONFIG') {
            throw new MgAdminPublicDonationsOperationsException('Type RETURN TO ENVIRONMENT CONFIG to confirm.');
        }
        $pdo->beginTransaction();
        try {
            $before = mg_public_donations_rollout_config(true);
            $stmt = $pdo->prepare(
                'UPDATE public_donations_operations_settings
                 SET override_active=0,configuration_version=configuration_version+1,change_reason=?,updated_by_user_id=?,updated_at=NOW()
                 WHERE id=1'
            );
            $stmt->execute([$reason, $actorId]);
            $after = mg_public_donations_rollout_config(true);
            $metadata = ['action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason];
            mg_audit('admin_public_donations_rollout_environment', 'public_donations_rollout', $metadata, $actorId);
            mg_event('admin.public_donations.rollout.environment', $metadata + ['admin_user_id' => $actorId], $actorId);
            mg_security_log('warning', 'admin.public_donations.rollout_environment', 'Admin returned Public Donations rollout to environment configuration.', $metadata, $actorId);
            $pdo->commit();
            return $after;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    if ($action !== 'update_rollout') {
        throw new MgAdminPublicDonationsOperationsException('Invalid rollout action.');
    }
    if ($confirmation !== 'UPDATE PUBLIC DONATIONS ROLLOUT') {
        throw new MgAdminPublicDonationsOperationsException('Type UPDATE PUBLIC DONATIONS ROLLOUT to confirm.');
    }
    $state = strtolower(trim((string)($input['feature_state'] ?? 'disabled')));
    if (!in_array($state, MG_PUBLIC_DONATIONS_FEATURE_STATES, true)) {
        throw new MgAdminPublicDonationsOperationsException('Invalid Public Donations feature state.');
    }
    $merchantIds = mg_admin_public_donations_validate_merchants($pdo, $input['selected_merchant_ids'] ?? []);
    if ($state === 'selected_merchants' && $merchantIds === []) {
        throw new MgAdminPublicDonationsOperationsException('Select at least one merchant before enabling selected-merchants rollout.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM public_donations_operations_settings WHERE id=1 FOR UPDATE')->fetchColumn();
        $before = mg_public_donations_rollout_config(true);
        $stmt = $pdo->prepare(
            'UPDATE public_donations_operations_settings
             SET override_active=1,feature_state=?,selected_merchant_ids_json=?,configuration_version=configuration_version+1,
                 change_reason=?,updated_by_user_id=?,updated_at=NOW()
             WHERE id=1'
        );
        $stmt->execute([
            $state,
            json_encode($merchantIds, JSON_THROW_ON_ERROR),
            $reason,
            $actorId,
        ]);
        $after = mg_public_donations_rollout_config(true);
        $metadata = ['action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason];
        mg_audit('admin_public_donations_rollout_update', 'public_donations_rollout', $metadata, $actorId);
        mg_event('admin.public_donations.rollout.updated', $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('warning', 'admin.public_donations.rollout_updated', 'Admin changed Public Donations rollout configuration.', $metadata, $actorId);
        $pdo->commit();
        return $after;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_admin_public_donations_store_receipt(PDO $pdo, array $actor, array $result, string $reason): void
{
    if (!mg_admin_public_donations_table($pdo, 'public_donations_reconciliation_receipts')) return;
    $receipt = $result['receipt'];
    $report = $result['report'];
    $filters = $receipt['filters'];
    $before = $receipt['before'];
    $after = $receipt['after'];
    $stmt = $pdo->prepare(
        'INSERT INTO public_donations_reconciliation_receipts
         (receipt_id,actor_user_id,merchant_user_id,campaign_reference,operation_reference,execution_mode,repair_modes_json,
          issues_before,repairable_before,report_only_before,repairs_applied,issues_after,unexplained_drift_after,
          checksum,reason,receipt_json,report_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
    );
    $stmt->execute([
        (string)$receipt['receipt_id'],
        (int)$actor['id'],
        (int)$filters['merchant_id'],
        $filters['campaign'],
        $filters['operation'],
        (string)$receipt['mode'],
        json_encode($receipt['repair_modes'], JSON_THROW_ON_ERROR),
        (int)$before['issues'],
        (int)$before['repairable'],
        (int)$before['report_only'],
        (int)$receipt['repairs_applied'],
        (int)$after['issues'],
        (int)$receipt['unexplained_drift_after'],
        (string)$receipt['checksum'],
        $reason,
        json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
}

function mg_admin_public_donations_reconcile(PDO $pdo, array $actor, array $input): array
{
    if (!mg_public_donations_reconcile_schema_ready($pdo)) {
        throw new MgAdminPublicDonationsOperationsException('The Public Donations foundation schema is incomplete.', 409);
    }
    $reason = mg_admin_public_donations_text($input['reason'] ?? '', 8, 240, 'Action reason');
    $repair = $input['repair'] ?? null;
    $modes = mg_public_donations_reconcile_modes($repair);
    if ($modes !== []) {
        if (!mg_admin_public_donations_actor_can_repair($actor)) {
            throw new MgAdminPublicDonationsOperationsException('Your account cannot execute reconciliation repairs.', 403);
        }
        if (trim((string)($input['confirmation'] ?? '')) !== 'REPAIR PUBLIC DONATIONS') {
            throw new MgAdminPublicDonationsOperationsException('Type REPAIR PUBLIC DONATIONS to confirm deterministic repairs.');
        }
    }

    $options = [
        'merchant_id' => $input['merchant_id'] ?? null,
        'campaign' => $input['campaign'] ?? null,
        'operation' => $input['operation'] ?? null,
        'limit' => $input['limit'] ?? 100,
        'repair' => $modes === [] ? null : implode(',', $modes),
        'actor_id' => (int)$actor['id'],
    ];
    $result = mg_public_donations_reconcile_apply($pdo, $options);
    mg_admin_public_donations_store_receipt($pdo, $actor, $result, $reason);

    $metadata = [
        'receipt_id' => (string)$result['receipt']['receipt_id'],
        'merchant_user_id' => (int)$result['receipt']['filters']['merchant_id'],
        'mode' => (string)$result['receipt']['mode'],
        'repair_modes' => $result['receipt']['repair_modes'],
        'repairs_applied' => (int)$result['receipt']['repairs_applied'],
        'unexplained_drift_after' => (int)$result['receipt']['unexplained_drift_after'],
        'reason' => $reason,
    ];
    mg_audit('admin_public_donations_reconcile', 'public_donations_reconciliation', $metadata, (int)$actor['id']);
    mg_event('admin.public_donations.reconciled', $metadata + ['admin_user_id' => (int)$actor['id']], (int)$actor['id']);
    mg_security_log(
        $modes === [] ? 'info' : 'warning',
        'admin.public_donations.reconciled',
        $modes === [] ? 'Admin ran a Public Donations reconciliation dry run.' : 'Admin executed deterministic Public Donations repairs.',
        $metadata,
        (int)$actor['id']
    );
    return $result;
}
