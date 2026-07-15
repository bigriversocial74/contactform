<?php
declare(strict_types=1);

/**
 * Resolve the single campaign and single active reward inventory item owned by a
 * Distribution Program. Hosted Games intentionally expose only the Program
 * selector; campaign and reward IDs are never trusted from the browser.
 */
function mg_hosted_game_program_campaign_ids(array $program): array
{
    $metadata = mg_hosted_game_json_decode($program['metadata_json'] ?? null);
    $values = $metadata['campaign_ids'] ?? null;
    if (!is_array($values) && isset($metadata['campaign_id'])) {
        $values = [$metadata['campaign_id']];
    }
    if (!is_array($values)) return [];

    $ids = [];
    foreach ($values as $value) {
        $id = strtolower(trim((string)$value));
        if ($id === '' || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) continue;
        $ids[$id] = true;
    }
    return array_keys($ids);
}

function mg_hosted_game_resolve_program_integration(
    PDO $pdo,
    int $merchantUserId,
    string $programPublicId,
    bool $forUpdate = true
): array {
    $programPublicId = strtolower(trim($programPublicId));
    if ($merchantUserId < 1 || preg_match('/^[a-f0-9-]{36}$/', $programPublicId) !== 1) {
        throw new MgHostedGameException('Select a valid Distribution Program.');
    }

    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $programStmt = $pdo->prepare(
        "SELECT * FROM distribution_programs
         WHERE public_id=? AND merchant_user_id=? AND status NOT IN ('cancelled','archived')
         LIMIT 1{$lock}"
    );
    $programStmt->execute([$programPublicId, $merchantUserId]);
    $program = $programStmt->fetch(PDO::FETCH_ASSOC);
    if (!$program) {
        throw new MgHostedGameException('Distribution Program not found or unavailable.');
    }

    $campaignIds = mg_hosted_game_program_campaign_ids($program);
    if (count($campaignIds) !== 1) {
        throw new MgHostedGameException('A hosted-game Distribution Program must contain exactly one connected campaign.');
    }

    $campaignStmt = $pdo->prepare(
        "SELECT * FROM campaigns
         WHERE public_id=? AND merchant_user_id=? AND status<>'archived'
         LIMIT 1{$lock}"
    );
    $campaignStmt->execute([$campaignIds[0], $merchantUserId]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        throw new MgHostedGameException('The Distribution Program campaign is unavailable.');
    }

    $rewardStmt = $pdo->prepare(
        "SELECT dpp.id AS program_product_id,dpp.quantity_limit,dpp.quantity_issued,dpp.weight,
                cpt.id,cpt.public_id,cpv.title,cpv.unit_value_cents,cpv.currency
         FROM distribution_program_products dpp
         INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id
         INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id
         INNER JOIN catalog_products cp ON cp.id=cpv.product_id
         WHERE dpp.program_id=?
           AND dpp.status='active'
           AND cpt.status='active'
           AND cp.merchant_user_id=?
           AND (dpp.quantity_limit IS NULL OR dpp.quantity_issued<dpp.quantity_limit)
         ORDER BY dpp.weight DESC,dpp.id ASC
         LIMIT 2{$lock}"
    );
    $rewardStmt->execute([(int)$program['id'], $merchantUserId]);
    $rewards = $rewardStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rewards === []) {
        throw new MgHostedGameException('The Distribution Program does not have active reward inventory available.');
    }
    if (count($rewards) !== 1) {
        throw new MgHostedGameException('A hosted-game Distribution Program must contain exactly one active reward inventory item.');
    }

    return [
        'program' => $program,
        'campaign' => $campaign,
        'reward' => $rewards[0],
    ];
}
