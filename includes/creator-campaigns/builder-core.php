<?php
declare(strict_types=1);

function mg_creator_campaign_builder_decode_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_creator_campaign_builder_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'
    );
    $stmt->execute([$table]);
    return $cache[$key] = ((int) $stmt->fetchColumn() === 1);
}

function mg_creator_campaign_builder_assert_schema(PDO $pdo): void
{
    $required = ['creator_campaigns', 'creator_campaign_application_questions'];
    foreach ($required as $table) {
        if (!mg_creator_campaign_builder_table_exists($pdo, $table)) {
            throw new RuntimeException(
                'Creator Campaign Builder schema is incomplete. Import database/20260721_creator_campaign_merchant_builder_v2.sql.'
            );
        }
    }
}

function mg_creator_campaign_builder_reference_context(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        "SELECT ca.public_id cover_asset_public_id,
                rt.public_id featured_reward_public_id,
                CASE
                  WHEN cc.campaign_manager_user_id IS NULL OR cc.campaign_manager_user_id=mw.merchant_user_id THEN 'owner'
                  ELSE (
                    SELECT mtm.public_id
                    FROM merchant_team_members mtm
                    WHERE mtm.workspace_id=cc.workspace_id
                      AND mtm.user_id=cc.campaign_manager_user_id
                      AND mtm.status='active'
                    ORDER BY mtm.id DESC
                    LIMIT 1
                  )
                END campaign_manager_key
         FROM creator_campaigns cc
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         LEFT JOIN catalog_assets ca ON ca.id=cc.cover_asset_id
         LEFT JOIN reward_templates rt ON rt.id=cc.featured_reward_template_id
         WHERE cc.id=? LIMIT 1"
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'cover_asset_public_id' => null,
        'featured_reward_public_id' => null,
        'campaign_manager_key' => 'owner',
    ];
}

function mg_creator_campaign_builder_questions(PDO $pdo, int $campaignId): array
{
    mg_creator_campaign_builder_assert_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT public_id,prompt,helper_text,question_type,options_json,is_required,sort_order,created_at,updated_at
         FROM creator_campaign_application_questions
         WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $stmt->execute([$campaignId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['options'] = mg_creator_campaign_builder_decode_json($row['options_json'] ?? null);
        $row['is_required'] = (bool) ($row['is_required'] ?? false);
        unset($row['options_json']);
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_builder_present(PDO $pdo, array $campaign, bool $withEvents = false): array
{
    $campaignId = (int) ($campaign['id'] ?? 0);
    if ($campaignId < 1) throw new InvalidArgumentException('Campaign row is missing its identifier.');
    mg_creator_campaign_builder_assert_schema($pdo);
    if (!array_key_exists('products', $campaign)) {
        $campaign = mg_creator_campaign_repository_hydrate($pdo, $campaign);
    }
    $campaign = array_replace($campaign, mg_creator_campaign_builder_reference_context($pdo, $campaignId));
    $campaign['geographic_scope'] = mg_creator_campaign_builder_decode_json($campaign['geographic_scope_json'] ?? null);
    $campaign['metadata'] = mg_creator_campaign_builder_decode_json($campaign['metadata_json'] ?? null);
    $campaign['builder_completed_steps'] = array_values(array_map('intval', mg_creator_campaign_builder_decode_json($campaign['builder_completed_steps_json'] ?? null)));
    $campaign['builder_validation'] = mg_creator_campaign_builder_decode_json($campaign['builder_validation_json'] ?? null);
    $campaign['application_questions'] = mg_creator_campaign_builder_questions($pdo, $campaignId);
    foreach ($campaign['eligibility_rules'] as &$rule) {
        $rule['value'] = json_decode((string) ($rule['value_json'] ?? 'null'), true);
        $rule['is_required'] = (bool) ($rule['is_required'] ?? false);
        unset($rule['value_json']);
    }
    unset($rule);
    foreach ($campaign['products'] as &$product) {
        $product['value_snapshot_cents'] = $product['value_snapshot_cents'] === null ? null : (int) $product['value_snapshot_cents'];
        unset($product['created_by_user_id'], $product['updated_by_user_id']);
    }
    unset($product);
    if ($withEvents) {
        $campaign['status_events'] = mg_creator_campaign_repository_status_events($pdo, $campaignId, 100);
    }
    unset(
        $campaign['id'], $campaign['workspace_id'], $campaign['workspace_owner_user_id'],
        $campaign['creation_idempotency_hash'], $campaign['geographic_scope_json'],
        $campaign['metadata_json'], $campaign['builder_completed_steps_json'],
        $campaign['builder_validation_json']
    );
    return $campaign;
}

function mg_creator_campaign_builder_resolve_campaign(
    PDO $pdo,
    array $user,
    string $campaignPublicId,
    string $permission = 'merchant.creator_campaigns.view',
    bool $forUpdate = false
): array {
    $campaignPublicId = trim($campaignPublicId);
    if ($campaignPublicId === '') throw new InvalidArgumentException('campaign_id is required.');
    $context = mg_creator_campaign_actor_context($pdo, $user, $permission, null);
    $campaign = mg_creator_campaign_repository_by_public_id(
        $pdo,
        $campaignPublicId,
        (int) $context['workspace_id'],
        $forUpdate
    );
    return ['context' => $context, 'campaign' => $campaign];
}

function mg_creator_campaign_builder_list(PDO $pdo, array $user, array $filters = []): array
{
    mg_creator_campaign_builder_assert_schema($pdo);
    $context = mg_creator_campaign_actor_context($pdo, $user, 'merchant.creator_campaigns.view', null);
    $workspaceId = (int) $context['workspace_id'];
    $status = strtolower(trim((string) ($filters['status'] ?? '')));
    $q = trim((string) ($filters['q'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $limit = max(1, min(50, (int) ($filters['limit'] ?? 24)));
    $offset = ($page - 1) * $limit;
    if ($q !== '' && (function_exists('mb_strlen') ? mb_strlen($q) : strlen($q)) > 120) {
        throw new InvalidArgumentException('Search query is too long.');
    }
    if ($status !== '' && !in_array($status, mg_creator_campaign_statuses(), true)) {
        throw new InvalidArgumentException('Campaign status filter is invalid.');
    }
    $where = ['cc.workspace_id=?'];
    $params = [$workspaceId];
    if ($status !== '') {
        $where[] = 'cc.status=?';
        $params[] = $status;
    }
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $where[] = "(cc.title LIKE ? ESCAPE '\\\\' OR cc.internal_reference LIKE ? ESCAPE '\\\\' OR COALESCE(cc.objective,'') LIKE ? ESCAPE '\\\\')";
        array_push($params, $like, $like, $like);
    }
    $sql = "SELECT cc.public_id,cc.internal_reference,cc.title,cc.description,cc.objective,cc.category,
                   cc.campaign_focus,cc.access_mode,cc.status,cc.starts_at,cc.ends_at,cc.application_deadline_at,
                   cc.maximum_approved_creators,cc.builder_step,cc.builder_completed_steps_json,cc.builder_validation_json,
                   cc.lock_version,cc.updated_at,cc.created_at,
                   (SELECT COUNT(*) FROM creator_campaign_products p WHERE p.campaign_id=cc.id AND p.relationship_type<>'excluded') product_count,
                   (SELECT COUNT(*) FROM creator_campaign_eligibility_rules r WHERE r.campaign_id=cc.id) eligibility_rule_count,
                   (SELECT COUNT(*) FROM creator_campaign_application_questions q WHERE q.campaign_id=cc.id) application_question_count
            FROM creator_campaigns cc
            WHERE " . implode(' AND ', $where) . '
            ORDER BY FIELD(cc.status,\'active\',\'scheduled\',\'draft\',\'paused\',\'completed\',\'cancelled\',\'archived\'),cc.updated_at DESC,cc.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['product_count'] = (int) $row['product_count'];
        $row['eligibility_rule_count'] = (int) $row['eligibility_rule_count'];
        $row['application_question_count'] = (int) $row['application_question_count'];
        $row['builder_completed_steps'] = array_values(array_map('intval', mg_creator_campaign_builder_decode_json($row['builder_completed_steps_json'] ?? null)));
        $row['builder_validation'] = mg_creator_campaign_builder_decode_json($row['builder_validation_json'] ?? null);
        unset($row['builder_completed_steps_json'], $row['builder_validation_json']);
    }
    unset($row);

    $summaryStmt = $pdo->prepare(
        "SELECT COUNT(*) total,
                SUM(status='draft') drafts,
                SUM(status='scheduled') scheduled,
                SUM(status='active') active,
                SUM(status='paused') paused,
                SUM(status='completed') completed,
                SUM(status='archived') archived,
                SUM(status='cancelled') cancelled
         FROM creator_campaigns WHERE workspace_id=?"
    );
    $summaryStmt->execute([$workspaceId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($summary as $key => $value) $summary[$key] = (int) $value;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM creator_campaigns cc WHERE ' . implode(' AND ', $where));
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    return [
        'campaigns' => $rows,
        'summary' => $summary,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
        ],
    ];
}
