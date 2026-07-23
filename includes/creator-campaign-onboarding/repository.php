<?php
declare(strict_types=1);

function mg_creator_campaign_onboarding_schema_ready(PDO $pdo): bool
{
    foreach ([
        'creator_campaign_merchant_onboarding',
        'creator_campaign_onboarding_events',
        'creator_campaign_onboarding_receipts',
    ] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() !== 1) return false;
    }
    return true;
}

function mg_creator_campaign_onboarding_hydrate(array $row): array
{
    foreach ([
        'business_defaults_json'=>'business_defaults',
        'product_selection_json'=>'product_selection',
        'compensation_defaults_json'=>'compensation_defaults',
        'creator_preferences_json'=>'creator_preferences',
        'operator_roles_json'=>'operator_roles',
        'readiness_snapshot_json'=>'readiness_snapshot',
    ] as $source => $target) {
        $row[$target] = mg_creator_campaign_onboarding_json($row[$source] ?? null);
    }
    $row['current_step'] = max(1, min(9, (int)($row['current_step'] ?? 1)));
    return $row;
}

function mg_creator_campaign_onboarding_row(PDO $pdo, int $ownerUserId, int $workspaceId, bool $lock = false): ?array
{
    if (!mg_creator_campaign_onboarding_schema_ready($pdo)) return null;
    $sql = 'SELECT * FROM creator_campaign_merchant_onboarding WHERE owner_user_id=? AND workspace_id=? LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ownerUserId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? mg_creator_campaign_onboarding_hydrate($row) : null;
}

function mg_creator_campaign_onboarding_ensure(PDO $pdo, array $user, array $workspace, array $pilot): array
{
    if (!mg_creator_campaign_onboarding_schema_ready($pdo)) {
        throw new MgCreatorCampaignOnboardingException(
            'Import the Phase 15 SQL before using merchant onboarding.',
            503,
            'CREATOR_CAMPAIGN_ONBOARDING_SCHEMA_MISSING'
        );
    }
    $ownerId = (int)($user['id'] ?? 0);
    $workspaceId = (int)($workspace['id'] ?? 0);
    $pilotId = (int)($pilot['id'] ?? 0);
    if ($ownerId < 1 || $workspaceId < 1 || $pilotId < 1 || (int)($workspace['merchant_user_id'] ?? 0) !== $ownerId) {
        throw new MgCreatorCampaignOnboardingException('Merchant owner authority is required.', 403, 'CREATOR_CAMPAIGN_ONBOARDING_OWNER_REQUIRED');
    }
    $existing = mg_creator_campaign_onboarding_row($pdo, $ownerId, $workspaceId);
    if ($existing) return $existing;

    $pdo->prepare(
        "INSERT INTO creator_campaign_merchant_onboarding
         (public_id,pilot_id,workspace_id,owner_user_id,status,current_step,created_at,updated_at)
         VALUES (?,?,?,?,'invited',1,NOW(),NOW())"
    )->execute([mg_public_uuid(), $pilotId, $workspaceId, $ownerId]);

    $row = mg_creator_campaign_onboarding_row($pdo, $ownerId, $workspaceId);
    if (!$row) throw new MgCreatorCampaignOnboardingException('Onboarding record could not be created.', 500);
    mg_creator_campaign_onboarding_event($pdo, $row, $ownerId, 'creator_campaign.onboarding.created', 'enrollment', 'info', 'Merchant onboarding workspace created.');
    return $row;
}

function mg_creator_campaign_onboarding_products(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.id,p.public_id,p.product_type,p.slug,p.status,p.current_version_id,
                v.public_id version_public_id,v.title,v.description,v.unit_value_cents,v.currency,v.version_status,
                COUNT(DISTINCT CASE WHEN a.asset_type='image' AND a.status='ready' THEN a.id END) ready_image_count,
                COUNT(DISTINCT CASE WHEN ppt.id IS NOT NULL AND ppt.status='active' THEN ppt.id END) active_pppm_count
         FROM catalog_products p
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=v.id
         LEFT JOIN catalog_assets a ON a.id=pva.asset_id
         LEFT JOIN catalog_pppm_templates ppt ON ppt.product_version_id=v.id
         WHERE p.merchant_user_id=? AND p.status<>'archived'
         GROUP BY p.id,v.id
         ORDER BY FIELD(p.status,'published','review','draft'),COALESCE(v.title,p.slug),p.id"
    );
    $stmt->execute([$merchantUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['unit_value_cents'] = (int)($row['unit_value_cents'] ?? 0);
        $row['ready_image_count'] = (int)($row['ready_image_count'] ?? 0);
        $row['active_pppm_count'] = (int)($row['active_pppm_count'] ?? 0);
        $row['checks'] = [
            'published'=>(string)$row['status'] === 'published' && (string)$row['version_status'] === 'published',
            'price'=>(int)$row['unit_value_cents'] > 0,
            'image'=>(int)$row['ready_image_count'] > 0,
            'claim_rules'=>(int)$row['active_pppm_count'] > 0,
        ];
        $row['ready'] = !in_array(false, $row['checks'], true);
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_onboarding_campaigns(PDO $pdo, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id,cc.public_id,cc.title,cc.status,cc.lock_version,cc.starts_at,cc.ends_at,cc.updated_at,
                COUNT(DISTINCT CASE WHEN ccp.relationship_type<>'excluded' THEN ccp.id END) product_count,
                COUNT(DISTINCT ccr.id) eligibility_count,
                COUNT(DISTINCT ccd.id) deliverable_count,
                COUNT(DISTINCT CASE WHEN comp.status='active' THEN comp.id END) active_compensation_count,
                COUNT(DISTINCT CASE WHEN b.status IN ('active','draft') THEN b.id END) budget_count,
                COUNT(DISTINCT CASE WHEN av.status IN ('offered','accepted') THEN av.id END) agreement_count,
                COUNT(DISTINCT CASE WHEN ts.status='active' THEN ts.id END) tracking_count
         FROM creator_campaigns cc
         LEFT JOIN creator_campaign_products ccp ON ccp.campaign_id=cc.id
         LEFT JOIN creator_campaign_eligibility_rules ccr ON ccr.campaign_id=cc.id
         LEFT JOIN creator_campaign_deliverables ccd ON ccd.campaign_id=cc.id
         LEFT JOIN creator_campaign_compensation_rules comp ON comp.campaign_id=cc.id
         LEFT JOIN creator_campaign_budgets b ON b.campaign_id=cc.id
         LEFT JOIN creator_campaign_agreement_versions av ON av.campaign_id=cc.id
         LEFT JOIN creator_campaign_tracking_sources ts ON ts.campaign_id=cc.id
         WHERE cc.workspace_id=? AND cc.status<>'archived'
         GROUP BY cc.id
         ORDER BY FIELD(cc.status,'draft','scheduled','active','paused','completed','cancelled'),cc.updated_at DESC,cc.id DESC"
    );
    $stmt->execute([$workspaceId]);
    return array_map(static function(array $row): array {
        foreach (['product_count','eligibility_count','deliverable_count','active_compensation_count','budget_count','agreement_count','tracking_count','lock_version'] as $key) {
            $row[$key] = (int)($row[$key] ?? 0);
        }
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_creator_campaign_onboarding_team(PDO $pdo, int $workspaceId, int $ownerUserId): array
{
    $rows = [[
        'public_id'=>'owner',
        'user_id'=>$ownerUserId,
        'display_name'=>'Workspace owner',
        'role_key'=>'owner',
    ]];
    $stmt = $pdo->prepare(
        "SELECT mtm.public_id,mtm.user_id,COALESCE(NULLIF(mtm.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),'Team member') display_name,mtm.role_key
         FROM merchant_team_members mtm
         INNER JOIN users u ON u.id=mtm.user_id AND u.status='active'
         WHERE mtm.workspace_id=? AND mtm.status='active' AND mtm.user_id IS NOT NULL
         ORDER BY FIELD(mtm.role_key,'owner','admin','manager','viewer'),display_name,mtm.id"
    );
    $stmt->execute([$workspaceId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if ((int)$row['user_id'] === $ownerUserId) continue;
        $rows[] = $row;
    }
    return $rows;
}

function mg_creator_campaign_onboarding_resolve_team_user(PDO $pdo, int $workspaceId, int $ownerUserId, string $publicId): int
{
    if ($publicId === '' || $publicId === 'owner') return $ownerUserId;
    $stmt = $pdo->prepare(
        "SELECT mtm.user_id FROM merchant_team_members mtm
         INNER JOIN users u ON u.id=mtm.user_id AND u.status='active'
         WHERE mtm.workspace_id=? AND mtm.public_id=? AND mtm.status='active' AND mtm.user_id IS NOT NULL LIMIT 1"
    );
    $stmt->execute([$workspaceId, $publicId]);
    $userId = (int)$stmt->fetchColumn();
    if ($userId < 1) throw new MgCreatorCampaignOnboardingException('The selected operator is not an active workspace member.');
    return $userId;
}

function mg_creator_campaign_onboarding_events(PDO $pdo, int $onboardingId, int $limit = 30): array
{
    if (!mg_creator_campaign_onboarding_schema_ready($pdo) || $onboardingId < 1) return [];
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare("SELECT * FROM creator_campaign_onboarding_events WHERE onboarding_id=? ORDER BY created_at DESC,id DESC LIMIT {$limit}");
    $stmt->execute([$onboardingId]);
    return array_map(static function(array $row): array {
        $row['metadata'] = mg_creator_campaign_onboarding_json($row['metadata_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_creator_campaign_onboarding_receipts(PDO $pdo, int $onboardingId, int $limit = 20): array
{
    if (!mg_creator_campaign_onboarding_schema_ready($pdo) || $onboardingId < 1) return [];
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT r.*,cc.public_id campaign_public_id,cc.title campaign_title
         FROM creator_campaign_onboarding_receipts r
         LEFT JOIN creator_campaigns cc ON cc.id=r.campaign_id
         WHERE r.onboarding_id=? ORDER BY r.created_at DESC,r.id DESC LIMIT {$limit}"
    );
    $stmt->execute([$onboardingId]);
    return array_map(static function(array $row): array {
        $row['checks'] = mg_creator_campaign_onboarding_json($row['checks_json'] ?? null);
        $row['score'] = (int)$row['score'];
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_creator_campaign_onboarding_campaign_row(PDO $pdo, int $workspaceId, string $campaignPublicId, bool $lock = false): array
{
    $sql = 'SELECT * FROM creator_campaigns WHERE workspace_id=? AND public_id=? LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$workspaceId, $campaignPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgCreatorCampaignOnboardingException('The selected Creator Campaign was not found.', 404, 'CREATOR_CAMPAIGN_ONBOARDING_CAMPAIGN_NOT_FOUND');
    return $row;
}

function mg_creator_campaign_onboarding_summary(PDO $pdo, int $ownerUserId, int $workspaceId): ?array
{
    $row = mg_creator_campaign_onboarding_row($pdo, $ownerUserId, $workspaceId);
    if (!$row) return null;
    $latest = mg_creator_campaign_onboarding_receipts($pdo, (int)$row['id'], 1)[0] ?? null;
    return [
        'status'=>(string)$row['status'],
        'status_label'=>mg_creator_campaign_onboarding_status_label((string)$row['status']),
        'current_step'=>(int)$row['current_step'],
        'readiness'=>$row['readiness_snapshot'],
        'latest_receipt'=>$latest,
        'href'=>'/account-creator-campaign-onboarding.php',
    ];
}
