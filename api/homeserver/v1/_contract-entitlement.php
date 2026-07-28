<?php
declare(strict_types=1);

function mg_hs_v1_account_id(int $ownerUserId): string
{
    return 'microgifter-account:' . $ownerUserId;
}

function mg_hs_v1_subscription_state(array $entitlement): string
{
    $status = strtolower((string)($entitlement['subscription_status'] ?? 'unknown'));
    if (($entitlement['state'] ?? '') === 'grace' || in_array($status, ['past_due', 'cancel_pending'], true)) return 'grace';
    if (!empty($entitlement['included'])) return 'active';
    if (in_array($status, ['canceled', 'expired'], true)) return 'canceled';
    if (($entitlement['state'] ?? '') === 'suspended') return 'suspended';
    return 'unknown';
}

function mg_hs_v1_lifecycle_state(string $subscriptionState, bool $revoked = false): string
{
    if ($revoked) return 'revoked';
    return match ($subscriptionState) {
        'active' => 'active',
        'grace' => 'grace',
        'suspended', 'canceled' => 'suspended',
        default => 'error',
    };
}

function mg_hs_v1_capability_decisions(array $entitlement, array $requested): array
{
    $registry = mg_hs_v1_capabilities();
    $requested = array_values(array_unique(array_filter(array_map('strval', $requested), static fn(string $value): bool => in_array($value, $registry, true))));
    if ($requested === []) $requested = $registry;

    $always = [
        'device-heartbeat.v1', 'entitlement-lease.v1', 'credential-rotation.v1',
        'signed-updates.v1', 'update-receipts.v1', 'device-replacement.v1',
    ];
    $paid = [
        'pairing.v1', 'device-registration.v1', 'merchant-assignments.v1',
        'site-assignments.v1', 'dataset-grants.v1', 'sync.incremental.v1',
        'update-authorization.v1',
    ];
    if (!empty($entitlement['can_operational_data'])) $paid[] = 'operational-data.v1';
    if (!empty($entitlement['can_agent_actions'])) $paid[] = 'campaign-actions.v1';

    $allowed = $always;
    if (in_array(mg_hs_v1_subscription_state($entitlement), ['active', 'grace'], true)) {
        $allowed = array_merge($allowed, $paid);
    }
    $granted = [];
    $denied = [];
    foreach ($requested as $capability) {
        if (in_array($capability, $allowed, true)) $granted[] = $capability;
        else $denied[] = $capability;
    }
    sort($granted, SORT_STRING);
    sort($denied, SORT_STRING);
    return ['requested' => $requested, 'granted' => $granted, 'denied' => $denied];
}

function mg_hs_v1_assignment(string $id, ?string $displayName = null, ?string $parentId = null): array
{
    return ['id' => $id, 'display_name' => $displayName, 'parent_id' => $parentId];
}

function mg_hs_v1_scopes(PDO $pdo, array $connection, ?string $requestedMerchant = null, ?string $requestedSite = null): array
{
    $ownerUserId = (int)$connection['owner_user_id'];
    $deviceId = (int)$connection['device_id'];
    $canonicalMerchantId = 'merchant:' . $ownerUserId;
    $merchantName = null;
    $ownerPublicId = null;
    try {
        $owner = mg_hs_v1_user($pdo, $ownerUserId);
        $merchantName = trim((string)($owner['business_name'] ?? $owner['display_name'] ?? $owner['name'] ?? '')) ?: null;
        $ownerPublicId = trim((string)($owner['public_id'] ?? '')) ?: null;
    } catch (Throwable) {}

    if ($requestedMerchant !== null && !in_array($requestedMerchant, array_filter([
        $canonicalMerchantId,
        (string)$ownerUserId,
        $ownerPublicId,
    ]), true)) {
        mg_hs_v1_fail('microgifter_entitlement_connection_mismatch', 'The requested merchant scope does not belong to the owning account.', 403);
    }

    $sites = [];
    if (mg_hs_v1_table_exists($pdo, 'homeserver_dataset_grants')) {
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT site_id FROM homeserver_dataset_grants WHERE device_id=? AND merchant_user_id=? AND grant_state='enabled' AND site_id IS NOT NULL AND site_id<>'' ORDER BY site_id LIMIT 100");
            $stmt->execute([$deviceId, $ownerUserId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $siteId) {
                $siteId = mb_substr(trim((string)$siteId), 0, 120);
                if ($siteId !== '') $sites[$siteId] = mg_hs_v1_assignment($siteId, null, $canonicalMerchantId);
            }
        } catch (Throwable) {}
    }
    if (mg_hs_v1_table_exists($pdo, 'merchant_locations')) {
        try {
            $stmt = $pdo->prepare("SELECT id,public_id,name FROM merchant_locations WHERE merchant_user_id=? AND status<>'deleted' ORDER BY id LIMIT 100");
            $stmt->execute([$ownerUserId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $location) {
                $publicId = mb_substr(trim((string)($location['public_id'] ?? '')), 0, 120);
                $numericId = (string)((int)($location['id'] ?? 0));
                $name = mb_substr(trim((string)($location['name'] ?? '')), 0, 120) ?: null;
                if ($publicId !== '') $sites[$publicId] = mg_hs_v1_assignment($publicId, $name, $canonicalMerchantId);
                if ($numericId !== '0') $sites[$numericId] = mg_hs_v1_assignment($numericId, $name, $canonicalMerchantId);
            }
        } catch (Throwable) {}
    }
    if ($requestedSite !== null && !isset($sites[$requestedSite])) {
        mg_hs_v1_fail('microgifter_entitlement_connection_mismatch', 'The requested site scope does not belong to the owning account.', 403);
    }
    if ($requestedSite !== null) $sites = [$requestedSite => $sites[$requestedSite]];

    return [
        'merchant_scope' => [mg_hs_v1_assignment($canonicalMerchantId, $merchantName, null)],
        'site_scope' => array_values($sites),
    ];
}

function mg_hs_v1_physical_device_count(PDO $pdo, int $ownerUserId): int
{
    return mg_homeserver_active_device_count($pdo, $ownerUserId);
}

function mg_hs_v1_device_allowance(PDO $pdo, array $entitlement, int $ownerUserId): array
{
    $limit = $entitlement['device_limit'] ?? 0;
    $active = mg_hs_v1_physical_device_count($pdo, $ownerUserId);
    return [
        'active_count' => $active,
        'limit' => $limit,
        'remaining' => $limit === null ? null : max(0, (int)$limit - $active),
    ];
}

function mg_hs_v1_lease_seconds(): int
{
    $configured = (int)(getenv('MG_HOMESERVER_ENTITLEMENT_LEASE_SECONDS') ?: 86400);
    return max(3600, min(604800, $configured));
}

function mg_hs_v1_issue_lease(PDO $pdo, array $connection, array $entitlement, ?string $requestedMerchant = null, ?string $requestedSite = null): array
{
    $material = mg_hs_v1_signing_material();
    $subscriptionState = mg_hs_v1_subscription_state($entitlement);
    $requested = json_decode((string)($connection['requested_capabilities_json'] ?? '[]'), true);
    $decisions = mg_hs_v1_capability_decisions($entitlement, is_array($requested) ? $requested : []);
    $scopes = mg_hs_v1_scopes($pdo, $connection, $requestedMerchant, $requestedSite);
    $channels = ['stable'];
    if (!empty($entitlement['can_beta_updates'])) $channels[] = 'beta';
    $now = time();
    $leaseId = mg_homeserver_public_uuid();
    $payload = [
        'schema_version' => MG_HOMESERVER_ENTITLEMENT_SCHEMA_VERSION,
        'lease_id' => $leaseId,
        'provider_id' => 'microgifter',
        'account_id' => mg_hs_v1_account_id((int)$connection['owner_user_id']),
        'connection_id' => (string)$connection['public_id'],
        'device_id' => (string)$connection['device_public_id'],
        'issued_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $now),
        'not_before_utc' => gmdate('Y-m-d\TH:i:s\Z', $now - 30),
        'expires_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $now + mg_hs_v1_lease_seconds()),
        'subscription_state' => $subscriptionState,
        'granted_capabilities' => $decisions['granted'],
        'denied_capabilities' => $decisions['denied'],
        'merchant_scope' => $scopes['merchant_scope'],
        'site_scope' => $scopes['site_scope'],
        'device_allowance' => mg_hs_v1_device_allowance($pdo, $entitlement, (int)$connection['owner_user_id']),
        'update_eligibility' => in_array($subscriptionState, ['active', 'grace'], true) && !empty($entitlement['can_feature_updates']),
        'allowed_update_channels' => $channels,
        'minimum_homeserver_version' => null,
        'signing_key_id' => $material['key_id'],
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $signature = sodium_crypto_sign_detached($payloadJson, $material['secret_key']);
    $signatureEncoded = mg_homeserver_base64url_encode($signature);

    $pdo->prepare("UPDATE homeserver_entitlement_leases_v1 SET state='superseded' WHERE provider_connection_id=? AND state='active'")
        ->execute([(int)$connection['id']]);
    $stmt = $pdo->prepare('INSERT INTO homeserver_entitlement_leases_v1
        (public_id,provider_connection_id,device_id,owner_user_id,account_id,schema_version,subscription_state,
         granted_capabilities_json,denied_capabilities_json,merchant_scope_json,site_scope_json,device_allowance_json,
         update_eligibility,allowed_update_channels_json,minimum_homeserver_version,signing_key_id,payload_json,
         signature_base64,issued_at,not_before_at,expires_at,state,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),FROM_UNIXTIME(?),\'active\',UTC_TIMESTAMP())');
    $stmt->execute([
        $leaseId, (int)$connection['id'], (int)$connection['device_id'], (int)$connection['owner_user_id'],
        $payload['account_id'], 1, $subscriptionState,
        mg_homeserver_json($payload['granted_capabilities']), mg_homeserver_json($payload['denied_capabilities']),
        mg_homeserver_json($payload['merchant_scope']), mg_homeserver_json($payload['site_scope']),
        mg_homeserver_json($payload['device_allowance']), $payload['update_eligibility'] ? 1 : 0,
        mg_homeserver_json($channels), null, $material['key_id'], $payloadJson, $signatureEncoded,
        $now, $now - 30, $now + mg_hs_v1_lease_seconds(),
    ]);
    $pdo->prepare('UPDATE homeserver_provider_connections SET lifecycle_state=?,subscription_state=?,granted_capabilities_json=?,denied_capabilities_json=?,merchant_scope_json=?,site_scope_json=?,current_lease_id=?,entitlement_expires_at=FROM_UNIXTIME(?),update_eligible=?,update_channels_json=?,last_entitlement_refresh_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')
        ->execute([
            mg_hs_v1_lifecycle_state($subscriptionState), $subscriptionState,
            mg_homeserver_json($payload['granted_capabilities']), mg_homeserver_json($payload['denied_capabilities']),
            mg_homeserver_json($payload['merchant_scope']), mg_homeserver_json($payload['site_scope']),
            $leaseId, $now + mg_hs_v1_lease_seconds(), $payload['update_eligibility'] ? 1 : 0,
            mg_homeserver_json($channels), (int)$connection['id'],
        ]);
    return ['payload' => $payload, 'signature' => $signatureEncoded];
}

function mg_hs_v1_signing_key_payload(): array
{
    $material = mg_hs_v1_signing_material();
    return [
        'key_id' => $material['key_id'],
        'public_key_base64' => mg_homeserver_base64url_encode($material['public_key']),
        'not_before_utc' => null,
        'not_after_utc' => null,
    ];
}
