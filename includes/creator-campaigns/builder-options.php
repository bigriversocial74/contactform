<?php
declare(strict_types=1);

function mg_creator_campaign_builder_options(PDO $pdo, array $user): array
{
    mg_creator_campaign_builder_assert_schema($pdo);
    $context = mg_creator_campaign_actor_context($pdo, $user, 'merchant.creator_campaigns.manage', null);
    $workspaceId = (int) $context['workspace_id'];
    $ownerId = (int) $context['workspace_owner_user_id'];

    $products = $pdo->prepare(
        "SELECT p.public_id,p.product_type,p.status,p.slug,
                v.public_id version_public_id,v.title,v.unit_value_cents,v.currency,v.version_status
         FROM catalog_products p
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         WHERE p.merchant_user_id=? AND p.status<>'archived'
         ORDER BY COALESCE(v.title,p.slug),p.id"
    );
    $products->execute([$ownerId]);

    $rewards = $pdo->prepare(
        "SELECT public_id,title,reward_type,value_type,value_amount_cents,value_percent,currency,status
         FROM reward_templates WHERE merchant_user_id=? AND status<>'archived' ORDER BY title,id"
    );
    $rewards->execute([$ownerId]);

    $assets = $pdo->prepare(
        "SELECT public_id,asset_type,original_filename,mime_type,width_px,height_px,status,created_at
         FROM catalog_assets WHERE owner_user_id=? AND asset_type='image' AND status IN ('pending','ready')
         ORDER BY created_at DESC,id DESC LIMIT 100"
    );
    $assets->execute([$ownerId]);

    $workspace = $pdo->prepare('SELECT display_name,timezone,default_currency FROM merchant_workspaces WHERE id=? LIMIT 1');
    $workspace->execute([$workspaceId]);
    $workspaceRow = $workspace->fetch(PDO::FETCH_ASSOC) ?: [];

    $managers = [[
        'key' => 'owner',
        'label' => (string) ($workspaceRow['display_name'] ?? 'Workspace owner'),
        'role' => 'owner',
    ]];
    $team = $pdo->prepare(
        "SELECT mtm.public_id,mtm.display_name,mtm.role_key,u.display_name user_display_name,u.full_name
         FROM merchant_team_members mtm
         INNER JOIN users u ON u.id=mtm.user_id AND u.status='active'
         WHERE mtm.workspace_id=? AND mtm.status='active' AND mtm.user_id IS NOT NULL
         ORDER BY FIELD(mtm.role_key,'owner','admin','manager','viewer'),COALESCE(mtm.display_name,u.display_name,u.full_name),mtm.id"
    );
    $team->execute([$workspaceId]);
    foreach ($team->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $managers[] = [
            'key' => (string) $row['public_id'],
            'label' => (string) ($row['display_name'] ?: $row['user_display_name'] ?: $row['full_name'] ?: 'Team member'),
            'role' => (string) $row['role_key'],
        ];
    }

    return [
        'products' => $products->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'reward_templates' => $rewards->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'assets' => $assets->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'managers' => $managers,
        'workspace' => $workspaceRow,
        'definitions' => [
            'statuses' => mg_creator_campaign_statuses(),
            'access_modes' => mg_creator_campaign_access_modes(),
            'focuses' => mg_creator_campaign_focuses(),
            'product_access_modes' => mg_creator_campaign_product_access_modes(),
            'existing_creator_preferences' => mg_creator_campaign_existing_creator_preferences(),
            'product_relationships' => mg_creator_campaign_product_relationship_types(),
            'eligibility_rule_types' => mg_creator_campaign_eligibility_rule_types(),
            'eligibility_operators' => mg_creator_campaign_eligibility_operators(),
            'question_types' => mg_creator_campaign_application_question_types(),
            'builder_steps' => mg_creator_campaign_builder_steps(),
        ],
    ];
}

function mg_creator_campaign_builder_resolve_manager(PDO $pdo, array $context, mixed $key): ?int
{
    $key = trim((string) $key);
    if ($key === '' || $key === 'owner') return (int) $context['workspace_owner_user_id'];
    $stmt = $pdo->prepare(
        "SELECT user_id FROM merchant_team_members
         WHERE workspace_id=? AND public_id=? AND status='active' AND user_id IS NOT NULL LIMIT 1"
    );
    $stmt->execute([(int) $context['workspace_id'], $key]);
    $userId = (int) $stmt->fetchColumn();
    if ($userId < 1) throw new DomainException('The selected campaign manager is not an active workspace member.');
    return $userId;
}

function mg_creator_campaign_builder_resolve_asset(PDO $pdo, array $context, mixed $publicId): ?int
{
    $publicId = trim((string) $publicId);
    if ($publicId === '') return null;
    $stmt = $pdo->prepare(
        "SELECT ca.id FROM catalog_assets ca
         INNER JOIN merchant_workspaces mw ON mw.id=? AND mw.merchant_user_id=ca.owner_user_id
         WHERE ca.public_id=? AND ca.asset_type='image' AND ca.status IN ('pending','ready') LIMIT 1"
    );
    $stmt->execute([(int) $context['workspace_id'], $publicId]);
    $id = (int) $stmt->fetchColumn();
    if ($id < 1) throw new DomainException('The selected campaign image does not belong to this workspace.');
    return $id;
}

function mg_creator_campaign_builder_resolve_reward(PDO $pdo, array $context, mixed $publicId): ?int
{
    $publicId = trim((string) $publicId);
    if ($publicId === '') return null;
    $stmt = $pdo->prepare(
        'SELECT id FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status<>\'archived\' LIMIT 1'
    );
    $stmt->execute([$publicId, (int) $context['workspace_owner_user_id']]);
    $id = (int) $stmt->fetchColumn();
    if ($id < 1) throw new DomainException('The selected reward does not belong to this workspace.');
    return $id;
}

function mg_creator_campaign_builder_normalize_question(array $question, int $sortOrder): array
{
    $prompt = mg_creator_campaign_string($question['prompt'] ?? null, 'application question prompt', 500, true);
    $helper = mg_creator_campaign_string($question['helper_text'] ?? null, 'application question helper text', 500);
    $type = strtolower(trim((string) ($question['question_type'] ?? 'short_text')));
    if (!in_array($type, mg_creator_campaign_application_question_types(), true)) {
        throw new InvalidArgumentException('Application question type is invalid.');
    }
    $options = $question['options'] ?? [];
    if (!is_array($options)) throw new InvalidArgumentException('Application question options must be an array.');
    $options = array_values(array_filter(array_map(static function (mixed $value): string {
        return trim((string) $value);
    }, $options), static fn(string $value): bool => $value !== ''));
    if (count($options) > 30) throw new InvalidArgumentException('An application question may not have more than 30 options.');
    if (in_array($type, ['single_choice', 'multiple_choice'], true) && count($options) < 2) {
        throw new InvalidArgumentException('Choice questions require at least two options.');
    }
    if (!in_array($type, ['single_choice', 'multiple_choice'], true)) $options = [];
    return [
        'prompt' => $prompt,
        'helper_text' => $helper,
        'question_type' => $type,
        'options_json' => $options === [] ? null : mg_creator_campaign_json_encode($options),
        'is_required' => !empty($question['is_required']) ? 1 : 0,
        'sort_order' => max(0, (int) ($question['sort_order'] ?? $sortOrder)),
    ];
}

function mg_creator_campaign_builder_product_rows(PDO $pdo, array $context, array $products): array
{
    if (count($products) > 100) throw new InvalidArgumentException('A campaign may not contain more than 100 product relationships.');
    $rows = [];
    $primaryCount = 0;
    $seen = [];
    foreach ($products as $index => $input) {
        if (!is_array($input)) throw new InvalidArgumentException('Product relationships must be objects.');
        $productPublicId = trim((string) ($input['product_public_id'] ?? ''));
        if ($productPublicId === '') throw new InvalidArgumentException('product_public_id is required.');
        $productStmt = $pdo->prepare(
            "SELECT p.id,p.current_version_id FROM catalog_products p
             WHERE p.public_id=? AND p.merchant_user_id=? AND p.status<>'archived' LIMIT 1"
        );
        $productStmt->execute([$productPublicId, (int) $context['workspace_owner_user_id']]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) throw new DomainException('A selected product does not belong to this workspace.');
        $versionId = null;
        $versionPublicId = trim((string) ($input['version_public_id'] ?? ''));
        if ($versionPublicId !== '') {
            $versionStmt = $pdo->prepare(
                'SELECT id,unit_value_cents,currency FROM catalog_product_versions WHERE public_id=? AND product_id=? LIMIT 1'
            );
            $versionStmt->execute([$versionPublicId, (int) $product['id']]);
        } else {
            $versionStmt = $pdo->prepare(
                'SELECT id,unit_value_cents,currency FROM catalog_product_versions WHERE id=? AND product_id=? LIMIT 1'
            );
            $versionStmt->execute([(int) ($product['current_version_id'] ?? 0), (int) $product['id']]);
        }
        $version = $versionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($versionPublicId !== '' && !$version) throw new DomainException('A selected product version is invalid.');
        if ($version) $versionId = (int) $version['id'];
        $relationship = strtolower(trim((string) ($input['relationship_type'] ?? 'featured')));
        if (!in_array($relationship, mg_creator_campaign_product_relationship_types(), true)) {
            throw new InvalidArgumentException('Product relationship type is invalid.');
        }
        if ($relationship === 'primary') $primaryCount++;
        $uniqueKey = (int) $product['id'] . ':' . $relationship;
        if (isset($seen[$uniqueKey])) throw new InvalidArgumentException('Duplicate product relationship.');
        $seen[$uniqueKey] = true;
        $rows[] = [
            'product_id' => (int) $product['id'],
            'version_id' => $versionId,
            'relationship_type' => $relationship,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? $index)),
            'value_snapshot_cents' => $version ? (int) $version['unit_value_cents'] : null,
            'currency' => $version ? strtoupper((string) $version['currency']) : null,
        ];
    }
    if ($primaryCount > 1) throw new InvalidArgumentException('A campaign may have only one primary product.');
    return $rows;
}

function mg_creator_campaign_builder_mark_step(array $campaign, int $step): array
{
    $completed = array_values(array_unique(array_map('intval', mg_creator_campaign_builder_decode_json($campaign['builder_completed_steps_json'] ?? null))));
    if (!in_array($step, $completed, true)) $completed[] = $step;
    sort($completed);
    return $completed;
}
