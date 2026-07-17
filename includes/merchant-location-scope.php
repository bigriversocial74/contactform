<?php
declare(strict_types=1);

/**
 * Canonical merchant-location ownership scope.
 *
 * A valid workspace relationship is authoritative. The direct merchant_user_id
 * column is used only for legacy/orphan rows whose workspace cannot be resolved.
 * This keeps older locations available without allowing a stale direct owner to
 * override a valid workspace owner.
 */

function mg_merchant_location_scope_alias(string $alias): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
        throw new InvalidArgumentException('Invalid merchant location SQL alias.');
    }
    return $alias;
}

function mg_merchant_location_scope_join(
    string $locationAlias = 'ml',
    string $workspaceAlias = 'location_scope_mw'
): string {
    $locationAlias = mg_merchant_location_scope_alias($locationAlias);
    $workspaceAlias = mg_merchant_location_scope_alias($workspaceAlias);
    return "LEFT JOIN merchant_workspaces {$workspaceAlias} ON {$workspaceAlias}.id={$locationAlias}.workspace_id";
}

function mg_merchant_location_scope_condition(
    string $locationAlias = 'ml',
    string $workspaceAlias = 'location_scope_mw'
): string {
    $locationAlias = mg_merchant_location_scope_alias($locationAlias);
    $workspaceAlias = mg_merchant_location_scope_alias($workspaceAlias);
    return "({$workspaceAlias}.id=? OR ({$workspaceAlias}.id IS NULL AND {$locationAlias}.merchant_user_id=?))";
}

function mg_merchant_location_scope_params(int $workspaceId, int $ownerMerchantId): array
{
    if ($workspaceId <= 0 || $ownerMerchantId <= 0) {
        throw new InvalidArgumentException('Merchant workspace and owner are required.');
    }
    return [$workspaceId, $ownerMerchantId];
}

function mg_merchant_location_scope_context(array $workspace): array
{
    $workspaceId = (int)($workspace['id'] ?? 0);
    $ownerMerchantId = (int)($workspace['merchant_user_id'] ?? 0);
    if ($workspaceId <= 0 || $ownerMerchantId <= 0) {
        throw new RuntimeException('Merchant workspace ownership is unavailable.');
    }
    return [
        'workspace_id' => $workspaceId,
        'owner_merchant_id' => $ownerMerchantId,
    ];
}

function mg_merchant_location_normalize_scope(
    PDO $pdo,
    int $workspaceId,
    int $ownerMerchantId
): int {
    if ($workspaceId <= 0 || $ownerMerchantId <= 0) return 0;
    try {
        $stmt = $pdo->prepare(
            'UPDATE merchant_locations ml
             LEFT JOIN merchant_workspaces location_scope_mw ON location_scope_mw.id=ml.workspace_id
             SET ml.workspace_id=?,ml.merchant_user_id=?,ml.updated_at=NOW()
             WHERE (location_scope_mw.id=? OR (location_scope_mw.id IS NULL AND ml.merchant_user_id=?))
               AND (ml.workspace_id IS NULL OR ml.workspace_id<>? OR ml.merchant_user_id IS NULL OR ml.merchant_user_id<>?)'
        );
        $stmt->execute([
            $workspaceId,$ownerMerchantId,
            $workspaceId,$ownerMerchantId,
            $workspaceId,$ownerMerchantId,
        ]);
        return max(0,$stmt->rowCount());
    } catch (Throwable) {
        return 0;
    }
}

function mg_merchant_location_count(
    PDO $pdo,
    int $workspaceId,
    int $ownerMerchantId,
    bool $includeArchived = false
): int {
    $sql = 'SELECT COUNT(*) FROM merchant_locations ml '
        . mg_merchant_location_scope_join('ml', 'location_scope_mw')
        . ' WHERE ' . mg_merchant_location_scope_condition('ml', 'location_scope_mw');
    if (!$includeArchived) $sql .= " AND ml.status<>'archived'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(mg_merchant_location_scope_params($workspaceId, $ownerMerchantId));
    return max(0, (int)$stmt->fetchColumn());
}

function mg_merchant_location_find_by_public_id(
    PDO $pdo,
    int $workspaceId,
    int $ownerMerchantId,
    string $publicId,
    bool $forUpdate = false
): ?array {
    $sql = 'SELECT ml.* FROM merchant_locations ml '
        . mg_merchant_location_scope_join('ml', 'location_scope_mw')
        . ' WHERE ml.public_id=? AND '
        . mg_merchant_location_scope_condition('ml', 'location_scope_mw')
        . ' LIMIT 1'
        . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$publicId], mg_merchant_location_scope_params($workspaceId, $ownerMerchantId)));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_merchant_location_find_by_id(
    PDO $pdo,
    int $workspaceId,
    int $ownerMerchantId,
    int $locationId,
    bool $forUpdate = false
): ?array {
    if ($locationId <= 0) return null;
    $sql = 'SELECT ml.* FROM merchant_locations ml '
        . mg_merchant_location_scope_join('ml', 'location_scope_mw')
        . ' WHERE ml.id=? AND '
        . mg_merchant_location_scope_condition('ml', 'location_scope_mw')
        . ' LIMIT 1'
        . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$locationId], mg_merchant_location_scope_params($workspaceId, $ownerMerchantId)));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_merchant_location_normalize_ownership(
    PDO $pdo,
    int $locationId,
    int $workspaceId,
    int $ownerMerchantId
): void {
    if ($locationId <= 0) throw new InvalidArgumentException('Merchant location is required.');
    $pdo->prepare(
        'UPDATE merchant_locations SET workspace_id=?,merchant_user_id=?,updated_at=NOW() WHERE id=?'
    )->execute([$workspaceId, $ownerMerchantId, $locationId]);
}
