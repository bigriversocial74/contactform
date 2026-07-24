<?php
declare(strict_types=1);

const MG_PUBLIC_DONATIONS_FEATURE_STATES = ['disabled', 'admin_only', 'selected_merchants', 'enabled'];

function mg_public_donations_feature_state(): string
{
    $state = strtolower(trim((string)(getenv('MG_PUBLIC_DONATIONS_FEATURE_STATE') ?: 'disabled')));
    return in_array($state, MG_PUBLIC_DONATIONS_FEATURE_STATES, true) ? $state : 'disabled';
}

/** @return list<int> */
function mg_public_donations_selected_merchant_ids(): array
{
    $raw = trim((string)(getenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS') ?: ''));
    if ($raw === '') return [];
    $ids = [];
    foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) $ids[(int)$id] = true;
    }
    return array_keys($ids);
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
    return [
        'state' => mg_public_donations_feature_state(),
        'enabled' => mg_public_donations_is_enabled_for($merchantUserId, $actor),
        'merchant_user_id' => $merchantUserId,
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
