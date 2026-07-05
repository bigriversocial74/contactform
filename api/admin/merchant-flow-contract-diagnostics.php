<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

function mg_mf_diag_root(): string
{
    return dirname(__DIR__, 2);
}

function mg_mf_diag_file_check(string $key, string $label, string $relativePath, array $requiredMarkers): array
{
    $path = mg_mf_diag_root() . '/' . ltrim($relativePath, '/');
    $exists = is_file($path) && is_readable($path);
    $content = $exists ? (string)file_get_contents($path) : '';
    $missing = [];
    foreach ($requiredMarkers as $marker) {
        if ($content === '' || strpos($content, $marker) === false) {
            $missing[] = $marker;
        }
    }
    return [
        'key' => $key,
        'label' => $label,
        'status' => !$exists ? 'critical' : ($missing ? 'warning' : 'healthy'),
        'severity' => !$exists ? 'critical' : 'warning',
        'summary' => !$exists ? 'Required flow file is missing or unreadable.' : ($missing ? 'Required contract markers need review.' : 'Required endpoint contract markers are present.'),
        'path' => $relativePath,
        'exists' => $exists,
        'missing_markers' => $missing,
    ];
}

function mg_mf_diag_table_exists(PDO $pdo, string $table): bool
{
    try {
        return mg_admin_system_health_table_exists($pdo, $table);
    } catch (Throwable) {
        return false;
    }
}

function mg_mf_diag_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1 || preg_match('/^[a-z0-9_]{1,64}$/', $column) !== 1) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_mf_diag_schema_check(PDO $pdo, string $key, string $label, array $tables, array $columns = []): array
{
    $missingTables = [];
    $missingColumns = [];
    foreach ($tables as $table) {
        if (!mg_mf_diag_table_exists($pdo, $table)) $missingTables[] = $table;
    }
    foreach ($columns as $table => $items) {
        foreach ((array)$items as $column) {
            if (!mg_mf_diag_column_exists($pdo, (string)$table, (string)$column)) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }
    $critical = $missingTables || $missingColumns;
    return [
        'key' => $key,
        'label' => $label,
        'status' => $critical ? 'critical' : 'healthy',
        'severity' => 'critical',
        'summary' => $critical ? 'Required schema dependencies are missing.' : 'Required schema dependencies are present.',
        'missing_tables' => $missingTables,
        'missing_columns' => $missingColumns,
    ];
}

function mg_mf_diag_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function mg_mf_diag_sample(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_mf_diag_data_check(string $key, string $label, string $severity, bool $available, int $count, string $summary, array $sample = [], array $details = []): array
{
    $status = !$available ? 'not_available' : ($count > 0 ? $severity : 'healthy');
    return [
        'key' => $key,
        'label' => $label,
        'status' => $status,
        'severity' => in_array($severity, ['warning', 'critical'], true) ? $severity : 'warning',
        'available' => $available,
        'count' => $available ? max(0, $count) : null,
        'summary' => $summary,
        'sample' => array_slice($sample, 0, 8),
        'details' => $details,
    ];
}

function mg_mf_diag_endpoint_contracts(): array
{
    return [
        mg_mf_diag_file_check('crm_campaign_send_contract', 'CRM campaign reward send contract', 'api/merchant/crm-campaign-send.php', [
            "mg_require_method('POST')",
            "mg_require_permission('merchant.campaigns.manage')",
            'mg_require_csrf_for_write',
            'FOR UPDATE',
            'mg_public_campaign_enforce_reward_limits',
            'crm_idempotency_key',
            'mg_zero_reward_issue_from_wallet',
        ]),
        mg_mf_diag_file_check('customer_refund_send_contract', 'Customer Refund send contract', 'api/merchant/customer-refund-send.php', [
            "mg_require_method('POST')",
            "mg_require_permission('merchant.campaigns.manage')",
            'mg_require_csrf_for_write',
            "campaign_type=\'customer_refund\'",
            'FOR UPDATE',
            'mg_public_campaign_enforce_reward_limits',
            'crm_idempotency_key',
            'mg_zero_reward_issue_from_wallet',
        ]),
        mg_mf_diag_file_check('action_center_send_contract', 'Action Center send/regift contract', 'api/account/action-center-send.php', [
            "mg_require_method('POST')",
            'mg_require_api_user',
            'mg_require_csrf_for_write',
            'idempotency_key',
            'mg_stamp_debit_send',
            'mg_pppm_transfer_owner_canonical',
        ]),
        mg_mf_diag_file_check('pppm_canonical_ownership_contract', 'PPPM canonical ownership contract', 'api/pppm/_ownership.php', [
            'function mg_pppm_transfer_owner_canonical',
            'FOR UPDATE',
            'duplicate',
            'owner_user_id',
            'recipient_user_id',
        ]),
        mg_mf_diag_file_check('wallet_to_gift_bridge_contract', 'Wallet-to-gift bridge contract', 'api/rewards/_zero_value_bridge.php', [
            'function mg_zero_reward_issue_from_wallet',
            'wallet_item_public_id',
            'source_reference',
            "INSERT INTO gifts",
        ]),
    ];
}

function mg_mf_diag_schema_contracts(PDO $pdo): array
{
    return [
        mg_mf_diag_schema_check($pdo, 'campaign_reward_schema', 'Campaign reward issuance schema', ['campaigns', 'campaign_contacts', 'reward_templates', 'wallet_items', 'campaign_events', 'users'], [
            'campaigns' => ['id', 'public_id', 'merchant_user_id', 'campaign_type', 'reward_template_id', 'status', 'issued_count', 'quantity_limit'],
            'campaign_contacts' => ['id', 'public_id', 'merchant_user_id', 'campaign_id', 'user_id', 'email'],
            'reward_templates' => ['id', 'public_id', 'status', 'issued_count', 'quantity_limit', 'per_user_limit'],
            'wallet_items' => ['id', 'public_id', 'user_id', 'merchant_user_id', 'reward_template_id', 'campaign_id', 'status', 'metadata_json'],
            'campaign_events' => ['id', 'public_id', 'merchant_user_id', 'campaign_id', 'wallet_item_id', 'contact_id', 'event_type'],
        ]),
        mg_mf_diag_schema_check($pdo, 'pppm_schema', 'PPPM ownership schema', ['pppm_items', 'pppm_issuance_requests', 'pppm_item_events'], [
            'pppm_items' => ['id', 'public_id', 'issuance_request_id', 'owner_user_id', 'recipient_user_id', 'status', 'version_no'],
            'pppm_issuance_requests' => ['id', 'public_id', 'quantity', 'issued_count', 'status'],
            'pppm_item_events' => ['id', 'pppm_item_id', 'event_type', 'from_status', 'to_status'],
        ]),
        mg_mf_diag_schema_check($pdo, 'commerce_ledger_schema', 'Commerce ledger and stamp debit schema', ['ledger_entries', 'ledger_transaction_groups', 'ledger_accounts'], [
            'ledger_entries' => ['id', 'transaction_group_id', 'ledger_account_id', 'entry_type', 'amount_cents'],
            'ledger_transaction_groups' => ['id', 'public_id', 'idempotency_key', 'status', 'currency'],
            'ledger_accounts' => ['id', 'wallet_id', 'account_code', 'normal_side', 'currency', 'status'],
        ]),
    ];
}

function mg_mf_diag_runtime_data(PDO $pdo): array
{
    $checks = [];

    if (mg_mf_diag_table_exists($pdo, 'campaigns') && mg_mf_diag_table_exists($pdo, 'reward_templates')) {
        $count = mg_mf_diag_count($pdo, "SELECT COUNT(*) FROM campaigns c LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id WHERE c.deleted_at IS NULL AND c.status = 'active' AND c.reward_template_id IS NOT NULL AND (rt.id IS NULL OR rt.status <> 'active')");
        $sample = $count > 0 ? mg_mf_diag_sample($pdo, "SELECT c.id,c.public_id,c.title,c.campaign_type,c.status,c.reward_template_id,rt.status reward_status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id WHERE c.deleted_at IS NULL AND c.status = 'active' AND c.reward_template_id IS NOT NULL AND (rt.id IS NULL OR rt.status <> 'active') ORDER BY c.updated_at DESC,c.id DESC LIMIT 8") : [];
        $checks[] = mg_mf_diag_data_check('active_campaign_inactive_reward', 'Active campaigns with inactive/missing reward', 'critical', true, $count, 'Active reward-backed campaigns should point to active reward templates.', $sample);

        $count = mg_mf_diag_count($pdo, "SELECT COUNT(*) FROM campaigns c INNER JOIN reward_templates rt ON rt.id = c.reward_template_id WHERE c.deleted_at IS NULL AND c.status = 'active' AND rt.status = 'active' AND ((c.quantity_limit IS NOT NULL AND c.issued_count > c.quantity_limit) OR (rt.quantity_limit IS NOT NULL AND rt.issued_count > rt.quantity_limit))");
        $sample = $count > 0 ? mg_mf_diag_sample($pdo, "SELECT c.id,c.public_id,c.title,c.campaign_type,c.issued_count,c.quantity_limit,rt.issued_count reward_issued_count,rt.quantity_limit reward_quantity_limit FROM campaigns c INNER JOIN reward_templates rt ON rt.id = c.reward_template_id WHERE c.deleted_at IS NULL AND c.status = 'active' AND rt.status = 'active' AND ((c.quantity_limit IS NOT NULL AND c.issued_count > c.quantity_limit) OR (rt.quantity_limit IS NOT NULL AND rt.issued_count > rt.quantity_limit)) ORDER BY c.updated_at DESC,c.id DESC LIMIT 8") : [];
        $checks[] = mg_mf_diag_data_check('campaign_inventory_over_issued', 'Campaign or reward inventory over-issued', 'critical', true, $count, 'Issued counts should not exceed campaign or reward inventory limits.', $sample);
    } else {
        $checks[] = mg_mf_diag_data_check('active_campaign_inactive_reward', 'Active campaigns with inactive/missing reward', 'warning', false, 0, 'Campaign or reward tables are unavailable.');
    }

    if (mg_mf_diag_table_exists($pdo, 'campaign_contacts')) {
        $count = mg_mf_diag_count($pdo, "SELECT COUNT(*) FROM campaign_contacts WHERE deleted_at IS NULL AND (email IS NULL OR email = '' OR email NOT LIKE '%@%')");
        $sample = $count > 0 ? mg_mf_diag_sample($pdo, "SELECT id,public_id,campaign_id,email,name,updated_at FROM campaign_contacts WHERE deleted_at IS NULL AND (email IS NULL OR email = '' OR email NOT LIKE '%@%') ORDER BY updated_at DESC,id DESC LIMIT 8") : [];
        $checks[] = mg_mf_diag_data_check('campaign_contacts_invalid_email', 'Campaign contacts missing valid email', 'warning', true, $count, 'CRM send flows require a valid customer email before wallet placement.', $sample);
    } else {
        $checks[] = mg_mf_diag_data_check('campaign_contacts_invalid_email', 'Campaign contacts missing valid email', 'warning', false, 0, 'Campaign contacts table is unavailable.');
    }

    if (mg_mf_diag_table_exists($pdo, 'wallet_items') && mg_mf_diag_table_exists($pdo, 'campaigns')) {
        $count = mg_mf_diag_count($pdo, "SELECT COUNT(*) FROM wallet_items wi LEFT JOIN campaigns c ON c.id = wi.campaign_id WHERE wi.campaign_id IS NOT NULL AND c.id IS NULL");
        $sample = $count > 0 ? mg_mf_diag_sample($pdo, "SELECT wi.id,wi.public_id,wi.campaign_id,wi.status,wi.created_at FROM wallet_items wi LEFT JOIN campaigns c ON c.id = wi.campaign_id WHERE wi.campaign_id IS NOT NULL AND c.id IS NULL ORDER BY wi.id DESC LIMIT 8") : [];
        $checks[] = mg_mf_diag_data_check('wallet_items_missing_campaign', 'Wallet items pointing to missing campaign', 'critical', true, $count, 'Campaign-issued wallet items should keep their campaign reference intact.', $sample);
    }

    if (mg_mf_diag_table_exists($pdo, 'campaign_events') && mg_mf_diag_table_exists($pdo, 'wallet_items')) {
        $count = mg_mf_diag_count($pdo, "SELECT COUNT(*) FROM wallet_items wi WHERE wi.campaign_id IS NOT NULL AND wi.status IN ('issued','sent','claimed','redeemed') AND NOT EXISTS (SELECT 1 FROM campaign_events ce WHERE ce.wallet_item_id = wi.id)");
        $sample = $count > 0 ? mg_mf_diag_sample($pdo, "SELECT wi.id,wi.public_id,wi.campaign_id,wi.status,wi.created_at FROM wallet_items wi WHERE wi.campaign_id IS NOT NULL AND wi.status IN ('issued','sent','claimed','redeemed') AND NOT EXISTS (SELECT 1 FROM campaign_events ce WHERE ce.wallet_item_id = wi.id) ORDER BY wi.id DESC LIMIT 8") : [];
        $checks[] = mg_mf_diag_data_check('campaign_wallet_items_missing_events', 'Campaign wallet items missing events', 'warning', true, $count, 'Campaign-issued wallet items should have at least one campaign event for attribution and follow-up tracking.', $sample);
    }

    return $checks;
}

function mg_mf_diag_group(string $key, string $label, array $checks): array
{
    $critical = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'critical'));
    $warning = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'warning'));
    $notAvailable = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'not_available'));
    return [
        'key' => $key,
        'label' => $label,
        'status' => $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy'),
        'checks' => $checks,
        'counts' => [
            'checks' => count($checks),
            'critical' => $critical,
            'warning' => $warning,
            'not_available' => $notAvailable,
        ],
    ];
}

function mg_mf_diag_run(PDO $pdo): array
{
    $groups = [
        mg_mf_diag_group('endpoint_contracts', 'Endpoint contracts', mg_mf_diag_endpoint_contracts()),
        mg_mf_diag_group('schema_contracts', 'Schema contracts', mg_mf_diag_schema_contracts($pdo)),
        mg_mf_diag_group('runtime_data', 'Runtime data readiness', mg_mf_diag_runtime_data($pdo)),
    ];
    $checks = [];
    foreach ($groups as $group) {
        foreach ($group['checks'] as $check) {
            $checks[] = ['group' => $group['key']] + $check;
        }
    }
    $critical = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'critical'));
    $warning = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'warning'));
    $notAvailable = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'not_available'));
    $status = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');

    return [
        'status' => $status,
        'summary' => $status === 'healthy'
            ? 'Merchant flow contracts are ready based on the current non-mutating diagnostics.'
            : ($critical > 0 ? $critical . ' critical merchant flow contract issue(s) need review.' : $warning . ' merchant flow warning(s) need review.'),
        'generated_at' => gmdate('c'),
        'groups' => $groups,
        'checks' => $checks,
        'counts' => [
            'groups' => count($groups),
            'checks' => count($checks),
            'critical' => $critical,
            'warning' => $warning,
            'not_available' => $notAvailable,
        ],
        'read_only' => true,
        'catalog_version' => '2026-07-05.merchant-flow-contract-v1',
    ];
}

try {
    mg_rate_limit('admin.merchant_flow_contract_diagnostics.read', 'user:' . (int)$user['id'], 60, 60);
    $pdo = mg_db();
    $data = mg_mf_diag_run($pdo);
    mg_security_log('info', 'admin.merchant_flow_contract_diagnostics.viewed', 'Merchant flow contract diagnostics viewed.', [
        'status' => $data['status'],
        'critical' => $data['counts']['critical'] ?? 0,
        'warning' => $data['counts']['warning'] ?? 0,
        'catalog_version' => $data['catalog_version'] ?? null,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.merchant_flow_contract_diagnostics.failed', 'Merchant flow contract diagnostics request failed.', [
        'exception_class' => $error::class,
        'message' => mb_substr($error->getMessage(), 0, 240),
    ], (int)$user['id']);
    mg_fail('Unable to run merchant flow contract diagnostics.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
mg_ok($data, 'Merchant flow contract diagnostics loaded.');
