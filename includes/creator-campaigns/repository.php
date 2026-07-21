<?php
declare(strict_types=1);

function mg_creator_campaign_repository_campaign(
    PDO $pdo,
    int $campaignId,
    ?int $workspaceId = null,
    bool $forUpdate = false
): array {
    $sql = 'SELECT cc.*,mw.merchant_user_id workspace_owner_user_id,mw.public_id workspace_public_id,mw.status workspace_status
            FROM creator_campaigns cc
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            WHERE cc.id=?';
    $params = [$campaignId];
    if ($workspaceId !== null) {
        $sql .= ' AND cc.workspace_id=?';
        $params[] = $workspaceId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        throw new RuntimeException('Creator campaign was not found.');
    }
    return $campaign;
}

function mg_creator_campaign_repository_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    bool $forUpdate = false
): array {
    $sql = 'SELECT cc.*,mw.merchant_user_id workspace_owner_user_id,mw.public_id workspace_public_id,mw.status workspace_status
            FROM creator_campaigns cc
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            WHERE cc.public_id=?';
    $params = [$publicId];
    if ($workspaceId !== null) {
        $sql .= ' AND cc.workspace_id=?';
        $params[] = $workspaceId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        throw new RuntimeException('Creator campaign was not found.');
    }
    return $campaign;
}

function mg_creator_campaign_repository_by_idempotency(
    PDO $pdo,
    int $workspaceId,
    string $idempotencyHash,
    bool $forUpdate = false
): ?array {
    $sql = 'SELECT * FROM creator_campaigns WHERE workspace_id=? AND creation_idempotency_hash=? LIMIT 1'
        . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$workspaceId, $idempotencyHash]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

function mg_creator_campaign_repository_status_event(
    PDO $pdo,
    int $campaignId,
    string $idempotencyHash
): ?array {
    $stmt = $pdo->prepare(
        'SELECT * FROM creator_campaign_status_events
         WHERE campaign_id=? AND idempotency_hash=? LIMIT 1'
    );
    $stmt->execute([$campaignId, $idempotencyHash]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    return $event ?: null;
}

function mg_creator_campaign_repository_products(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        'SELECT ccp.*,cp.public_id product_public_id,cp.status product_status,cp.product_type,
                cpv.public_id version_public_id,cpv.title version_title,cpv.version_status
         FROM creator_campaign_products ccp
         INNER JOIN catalog_products cp ON cp.id=ccp.product_id
         LEFT JOIN catalog_product_versions cpv ON cpv.id=ccp.selected_product_version_id
         WHERE ccp.campaign_id=?
         ORDER BY ccp.sort_order,ccp.id'
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_repository_eligibility_rules(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM creator_campaign_eligibility_rules
         WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_repository_status_events(PDO $pdo, int $campaignId, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $stmt = $pdo->prepare(
        'SELECT * FROM creator_campaign_status_events
         WHERE campaign_id=? ORDER BY created_at DESC,id DESC LIMIT ' . $limit
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_repository_hydrate(PDO $pdo, array $campaign): array
{
    $campaignId = (int) ($campaign['id'] ?? 0);
    if ($campaignId < 1) {
        throw new InvalidArgumentException('Campaign row is missing its identifier.');
    }
    $campaign['products'] = mg_creator_campaign_repository_products($pdo, $campaignId);
    $campaign['eligibility_rules'] = mg_creator_campaign_repository_eligibility_rules($pdo, $campaignId);
    return $campaign;
}

function mg_creator_campaign_repository_assert_product_owned(
    PDO $pdo,
    int $workspaceId,
    int $productId,
    ?int $selectedVersionId = null
): array {
    $stmt = $pdo->prepare(
        "SELECT cp.*,mw.merchant_user_id workspace_owner_user_id
         FROM catalog_products cp
         INNER JOIN merchant_workspaces mw ON mw.id=? AND mw.merchant_user_id=cp.merchant_user_id
         WHERE cp.id=? AND cp.status<>'archived' LIMIT 1"
    );
    $stmt->execute([$workspaceId, $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new DomainException('The selected product does not belong to this merchant workspace.');
    }

    if ($selectedVersionId !== null) {
        $version = $pdo->prepare(
            'SELECT id,product_id,version_status,unit_value_cents,currency
             FROM catalog_product_versions WHERE id=? AND product_id=? LIMIT 1'
        );
        $version->execute([$selectedVersionId, $productId]);
        $versionRow = $version->fetch(PDO::FETCH_ASSOC);
        if (!$versionRow) {
            throw new DomainException('The selected product version does not belong to the selected product.');
        }
        $product['selected_version'] = $versionRow;
    }

    return $product;
}

function mg_creator_campaign_repository_assert_asset_owned(
    PDO $pdo,
    int $workspaceId,
    int $assetId
): array {
    $stmt = $pdo->prepare(
        "SELECT ca.*
         FROM catalog_assets ca
         INNER JOIN merchant_workspaces mw ON mw.id=? AND mw.merchant_user_id=ca.owner_user_id
         WHERE ca.id=? AND ca.status IN ('pending','ready') LIMIT 1"
    );
    $stmt->execute([$workspaceId, $assetId]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) {
        throw new DomainException('The selected asset does not belong to this merchant workspace.');
    }
    return $asset;
}
