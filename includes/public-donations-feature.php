<?php
declare(strict_types=1);

const MG_PUBLIC_DONATIONS_FEATURE_STATES = ['disabled', 'admin_only', 'selected_merchants', 'enabled'];

/** @return list<int> */
function mg_public_donations_parse_merchant_ids(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $raw = trim((string)$value);
        if ($raw === '') return [];
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    }

    $ids = [];
    foreach ($parts as $part) {
        $id = filter_var($part, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) $ids[(int)$id] = true;
    }
    $result = array_keys($ids);
    sort($result, SORT_NUMERIC);
    return $result;
}

function mg_public_donations_environment_rollout(): array
{
    $state = strtolower(trim((string)(getenv('MG_PUBLIC_DONATIONS_FEATURE_STATE') ?: 'disabled')));
    if (!in_array($state, MG_PUBLIC_DONATIONS_FEATURE_STATES, true)) $state = 'disabled';
    return [
        'state' => $state,
        'selected_merchant_ids' => mg_public_donations_parse_merchant_ids(getenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS') ?: ''),
        'source' => 'environment',
        'override_active' => false,
        'configuration_version' => null,
        'updated_at' => null,
        'updated_by_user_id' => null,
        'change_reason' => null,
    ];
}

function mg_public_donations_rollout_config(bool $refresh = false): array
{
    static $cached = null;
    if (!$refresh && is_array($cached)) return $cached;

    $fallback = mg_public_donations_environment_rollout();
    if (!function_exists('mg_db')) return $cached = $fallback;

    try {
        $pdo = mg_db();
        $table = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $table->execute(['public_donations_operations_settings']);
        if (!$table->fetchColumn()) return $cached = $fallback;

        $stmt = $pdo->query(
            'SELECT override_active,feature_state,selected_merchant_ids_json,configuration_version,change_reason,updated_by_user_id,updated_at
             FROM public_donations_operations_settings WHERE id=1 LIMIT 1'
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row || empty($row['override_active'])) return $cached = $fallback;

        $state = strtolower(trim((string)$row['feature_state']));
        if (!in_array($state, MG_PUBLIC_DONATIONS_FEATURE_STATES, true)) $state = 'disabled';
        $decoded = json_decode((string)($row['selected_merchant_ids_json'] ?? '[]'), true);

        return $cached = [
            'state' => $state,
            'selected_merchant_ids' => mg_public_donations_parse_merchant_ids(is_array($decoded) ? $decoded : []),
            'source' => 'database_override',
            'override_active' => true,
            'configuration_version' => (int)$row['configuration_version'],
            'updated_at' => $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            'updated_by_user_id' => $row['updated_by_user_id'] !== null ? (int)$row['updated_by_user_id'] : null,
            'change_reason' => $row['change_reason'] !== null ? (string)$row['change_reason'] : null,
        ];
    } catch (Throwable) {
        return $cached = $fallback;
    }
}

function mg_public_donations_feature_state(): string
{
    return (string)mg_public_donations_rollout_config()['state'];
}

/** @return list<int> */
function mg_public_donations_selected_merchant_ids(): array
{
    return mg_public_donations_rollout_config()['selected_merchant_ids'];
}

function mg_public_donations_actor_is_admin(?array $actor): bool
{
    if (!$actor) return false;
    $roles = $actor['roles'] ?? [];
    if (is_string($roles)) $roles = preg_split('/[\s,;]+/', $roles) ?: [];
    if (!is_array($roles)) $roles = [];
    foreach ($roles as $role) {
        $slug = is_array($role) ? (string)($role['slug'] ?? '') : (string)$role;
        if (in_array(strtolower(trim($slug)), ['admin', 'super_admin'], true)) return true;
    }
    return !empty($actor['is_admin']) || !empty($actor['is_super_admin']);
}

function mg_public_donations_is_enabled_for(?int $merchantUserId, ?array $actor = null): bool
{
    return match (mg_public_donations_feature_state()) {
        'enabled' => true,
        'selected_merchants' => $merchantUserId !== null && in_array($merchantUserId, mg_public_donations_selected_merchant_ids(), true),
        'admin_only' => mg_public_donations_actor_is_admin($actor),
        default => false,
    };
}

function mg_public_donations_feature_context(?int $merchantUserId, ?array $actor = null): array
{
    $rollout = mg_public_donations_rollout_config();
    return [
        'state' => $rollout['state'],
        'enabled' => mg_public_donations_is_enabled_for($merchantUserId, $actor),
        'merchant_user_id' => $merchantUserId,
        'source' => $rollout['source'],
        'override_active' => $rollout['override_active'],
        'configuration_version' => $rollout['configuration_version'],
    ];
}

function mg_public_donations_campaign_type_options(?int $merchantUserId, ?array $actor = null, bool $includeInternal = false): array
{
    $items = mg_campaign_type_options($includeInternal);
    if (mg_public_donations_is_enabled_for($merchantUserId, $actor)) return $items;
    return array_values(array_filter($items, static fn(array $item): bool => (string)($item['key'] ?? '') !== 'public_donation'));
}

function mg_public_donations_client_registry(?int $merchantUserId, ?array $actor = null, bool $includeInternal = false): array
{
    $items = mg_campaign_type_client_registry($includeInternal);
    if (mg_public_donations_is_enabled_for($merchantUserId, $actor)) return $items;
    return array_values(array_filter($items, static fn(array $item): bool => (string)($item['key'] ?? '') !== 'public_donation'));
}
