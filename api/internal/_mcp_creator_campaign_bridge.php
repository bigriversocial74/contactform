<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/creator-campaigns/bootstrap.php';

function mg_mcp_creator_campaign_page(array $arguments, int $maximum = 100): array
{
    $limit = max(1, min((int)($arguments['limit'] ?? 25), $maximum));
    $cursor = trim((string)($arguments['cursor'] ?? ''));
    if ($cursor !== '' && preg_match('/^[0-9]{1,8}$/', $cursor) !== 1) {
        throw new MgMcpBridgeException('Invalid cursor.', 422, 'MCP_CREATOR_CAMPAIGN_CURSOR_INVALID');
    }
    return [$limit, $cursor === '' ? 0 : (int)$cursor];
}

function mg_mcp_creator_campaign_page_result(array $items, int $limit, int $offset): array
{
    return [
        'items' => $items,
        'limit' => $limit,
        'next_cursor' => count($items) === $limit ? (string)($offset + $limit) : null,
    ];
}

function mg_mcp_creator_campaign_uuid(mixed $value, string $field = 'campaign_id', bool $required = true): string
{
    $publicId = mg_mcp_bridge_text($value, 40, $field, $required);
    if ($publicId !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,39}$/', $publicId) !== 1) {
        throw new MgMcpBridgeException('Invalid ' . $field . '.', 422, 'MCP_CREATOR_CAMPAIGN_VALIDATION_FAILED');
    }
    return $publicId;
}

function mg_mcp_creator_campaign_actor(PDO $pdo, array $context): array
{
    $workspaceType = strtolower(trim((string)($context['workspace_type'] ?? '')));
    $workspaceId = (int)($context['workspace_id'] ?? 0);
    if (in_array($workspaceType, ['merchant', 'merchant_workspace'], true) && $workspaceId > 0) {
        return ['mode' => 'merchant', 'workspace_id' => $workspaceId, 'creator_user_id' => null];
    }

    $userId = (int)($context['user_id'] ?? 0);
    $stmt = $pdo->prepare(
        "SELECT cp.id,cp.public_id
         FROM creator_profiles cp
         INNER JOIN users u ON u.id=cp.user_id AND u.status='active'
         INNER JOIN user_model_assignments uma ON uma.user_id=u.id AND uma.status='active'
         INNER JOIN user_models um ON um.id=uma.user_model_id AND um.code='creator'
         WHERE cp.user_id=? AND cp.status='active' LIMIT 1"
    );
    $stmt->execute([$userId]);
    $creator = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$creator) {
        throw new MgMcpBridgeException('An active approved Creator account or merchant workspace is required.', 403, 'MCP_CREATOR_CAMPAIGN_ACTOR_DENIED');
    }
    return [
        'mode' => 'creator',
        'workspace_id' => null,
        'creator_user_id' => $userId,
        'creator_profile_id' => (int)$creator['id'],
        'creator_profile_public_id' => (string)$creator['public_id'],
    ];
}

function mg_mcp_creator_campaign_resolve(PDO $pdo, array $actor, string $campaignPublicId): array
{
    if ($actor['mode'] === 'merchant') {
        $stmt = $pdo->prepare('SELECT cc.* FROM creator_campaigns cc WHERE cc.public_id=? AND cc.workspace_id=? LIMIT 1');
        $stmt->execute([$campaignPublicId, (int)$actor['workspace_id']]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT cc.*
             FROM creator_campaigns cc
             LEFT JOIN creator_campaign_applications a ON a.campaign_id=cc.id AND a.creator_user_id=?
             LEFT JOIN creator_campaign_invitations i ON i.campaign_id=cc.id AND i.creator_user_id=?
             LEFT JOIN creator_campaign_participants p ON p.campaign_id=cc.id AND p.creator_user_id=?
             WHERE cc.public_id=? AND (
                 (cc.status IN ('scheduled','active')
                  AND cc.access_mode IN ('open','approved_creators','hybrid')
                  AND (cc.application_deadline_at IS NULL OR cc.application_deadline_at>=NOW()))
                 OR a.id IS NOT NULL OR i.id IS NOT NULL OR p.id IS NOT NULL
             ) LIMIT 1"
        );
        $creatorUserId = (int)$actor['creator_user_id'];
        $stmt->execute([$creatorUserId, $creatorUserId, $creatorUserId, $campaignPublicId]);
    }
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        throw new MgMcpBridgeException('Creator Campaign was not found in this connection scope.', 404, 'MCP_CREATOR_CAMPAIGN_NOT_FOUND');
    }
    return $campaign;
}

function mg_mcp_creator_campaign_campaign_projection(array $row, string $mode): array
{
    $projection = [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title'],
        'description' => $row['description'] ?? null,
        'objective' => $row['objective'] ?? null,
        'category' => $row['category'] ?? null,
        'campaign_focus' => $row['campaign_focus'] ?? 'general_brand_campaign',
        'access_mode' => (string)$row['access_mode'],
        'status' => (string)$row['status'],
        'timezone' => (string)$row['timezone'],
        'starts_at' => $row['starts_at'] ?? null,
        'ends_at' => $row['ends_at'] ?? null,
        'application_deadline_at' => $row['application_deadline_at'] ?? null,
        'creator_product_access' => $row['creator_product_access'] ?? 'none',
        'creator_landing_url' => $row['creator_landing_url'] ?? null,
        'maximum_approved_creators' => isset($row['maximum_approved_creators']) ? (int)$row['maximum_approved_creators'] : null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
    if (isset($row['merchant_name'])) {
        $projection['merchant_name'] = $row['merchant_name'];
    }
    if ($mode === 'merchant') {
        $projection['internal_reference'] = $row['internal_reference'] ?? null;
        $projection['builder_step'] = isset($row['builder_step']) ? (int)$row['builder_step'] : null;
        $projection['lock_version'] = isset($row['lock_version']) ? (int)$row['lock_version'] : null;
    }
    foreach (['application_status', 'invitation_status', 'participant_status'] as $field) {
        if (array_key_exists($field, $row)) {
            $projection[$field] = $row[$field];
        }
    }
    foreach (['product_count', 'participant_count', 'pending_application_count', 'deliverable_count'] as $field) {
        if (array_key_exists($field, $row)) {
            $projection[$field] = (int)$row[$field];
        }
    }
    return $projection;
}

function mg_mcp_creator_campaign_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $search = mg_mcp_bridge_text($arguments['search'] ?? '', 100, 'search', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];

    if ($actor['mode'] === 'merchant') {
        $where[] = 'cc.workspace_id=?';
        $params[] = (int)$actor['workspace_id'];
        $joins = '';
        $relationshipColumns = 'NULL application_status,NULL invitation_status,NULL participant_status,';
    } else {
        $creatorUserId = (int)$actor['creator_user_id'];
        $joins = " LEFT JOIN creator_campaign_applications a ON a.campaign_id=cc.id AND a.creator_user_id=?
                   LEFT JOIN creator_campaign_invitations i ON i.campaign_id=cc.id AND i.creator_user_id=?
                   LEFT JOIN creator_campaign_participants p ON p.campaign_id=cc.id AND p.creator_user_id=?";
        array_push($params, $creatorUserId, $creatorUserId, $creatorUserId);
        $where[] = "((cc.status IN ('scheduled','active') AND cc.access_mode IN ('open','approved_creators','hybrid') AND (cc.application_deadline_at IS NULL OR cc.application_deadline_at>=NOW())) OR a.id IS NOT NULL OR i.id IS NOT NULL OR p.id IS NOT NULL)";
        $relationshipColumns = 'a.status application_status,i.status invitation_status,p.status participant_status,';
    }
    if ($search !== '') {
        $where[] = '(cc.title LIKE ? OR cc.description LIKE ? OR cc.objective LIKE ? OR mw.display_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($status !== '') {
        $where[] = 'cc.status=?';
        $params[] = $status;
    }

    $sql = "SELECT DISTINCT cc.*,mw.display_name merchant_name,{$relationshipColumns}
                   (SELECT COUNT(*) FROM creator_campaign_products cp WHERE cp.campaign_id=cc.id AND cp.relationship_type<>'excluded') product_count,
                   (SELECT COUNT(*) FROM creator_campaign_participants p2 WHERE p2.campaign_id=cc.id) participant_count,
                   (SELECT COUNT(*) FROM creator_campaign_applications a2 WHERE a2.campaign_id=cc.id AND a2.status IN ('submitted','under_review','information_requested')) pending_application_count,
                   (SELECT COUNT(*) FROM creator_campaign_deliverables d WHERE d.campaign_id=cc.id AND d.status<>'retired') deliverable_count
            FROM creator_campaigns cc
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            {$joins}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cc.updated_at DESC,cc.id DESC LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_map(
        static fn(array $row): array => mg_mcp_creator_campaign_campaign_projection($row, (string)$actor['mode']),
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    );
    return mg_mcp_creator_campaign_page_result($items, $limit, $offset);
}

function mg_mcp_creator_campaign_get(PDO $pdo, array $actor, array $arguments): array
{
    $campaignPublicId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '');
    $campaign = mg_mcp_creator_campaign_resolve($pdo, $actor, $campaignPublicId);
    $campaignId = (int)$campaign['id'];
    $merchant = $pdo->prepare('SELECT display_name FROM merchant_workspaces WHERE id=? LIMIT 1');
    $merchant->execute([(int)$campaign['workspace_id']]);
    $campaign['merchant_name'] = $merchant->fetchColumn() ?: null;

    $products = $pdo->prepare(
        "SELECT cp.public_id id,p.public_id product_id,cp.relationship_type,cp.sort_order,cp.value_snapshot_cents,cp.currency
         FROM creator_campaign_products cp INNER JOIN catalog_products p ON p.id=cp.product_id
         WHERE cp.campaign_id=? ORDER BY cp.sort_order,cp.id"
    );
    $products->execute([$campaignId]);
    $rules = $pdo->prepare('SELECT public_id id,rule_type,operator_key,value_json,is_required,sort_order FROM creator_campaign_eligibility_rules WHERE campaign_id=? ORDER BY sort_order,id');
    $rules->execute([$campaignId]);
    $eligibility = $rules->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($eligibility as &$rule) {
        $decoded = json_decode((string)($rule['value_json'] ?? ''), true);
        $rule['value'] = is_array($decoded) ? $decoded : null;
        $rule['is_required'] = !empty($rule['is_required']);
        $rule['sort_order'] = (int)$rule['sort_order'];
        unset($rule['value_json']);
    }
    unset($rule);

    $deliverables = $pdo->prepare(
        "SELECT public_id id,title,description,deliverable_type,platform,content_format,quantity,status,due_offset_days,publication_required,proof_required,revision_limit,sort_order
         FROM creator_campaign_deliverables WHERE campaign_id=? AND status<>'retired' ORDER BY sort_order,id"
    );
    $deliverables->execute([$campaignId]);

    $result = [
        'campaign' => mg_mcp_creator_campaign_campaign_projection($campaign, (string)$actor['mode']),
        'products' => $products->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'eligibility_rules' => $eligibility,
        'deliverables' => $deliverables->fetchAll(PDO::FETCH_ASSOC) ?: [],
    ];
    if ($actor['mode'] === 'creator') {
        $creatorUserId = (int)$actor['creator_user_id'];
        $relationship = $pdo->prepare(
            "SELECT a.public_id application_id,a.status application_status,
                    i.public_id invitation_id,i.status invitation_status,
                    p.public_id participant_id,p.status participant_status
             FROM creator_campaigns cc
             LEFT JOIN creator_campaign_applications a ON a.campaign_id=cc.id AND a.creator_user_id=?
             LEFT JOIN creator_campaign_invitations i ON i.campaign_id=cc.id AND i.creator_user_id=?
             LEFT JOIN creator_campaign_participants p ON p.campaign_id=cc.id AND p.creator_user_id=?
             WHERE cc.id=? LIMIT 1"
        );
        $relationship->execute([$creatorUserId, $creatorUserId, $creatorUserId, $campaignId]);
        $result['relationship'] = $relationship->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return $result;
}

function mg_mcp_creator_campaign_validate(PDO $pdo, array $actor, array $arguments): array
{
    if ($actor['mode'] !== 'merchant') {
        throw new MgMcpBridgeException('Campaign validation is available only to the authorized merchant workspace.', 403, 'MCP_CREATOR_CAMPAIGN_MERCHANT_REQUIRED');
    }
    $campaign = mg_mcp_creator_campaign_resolve($pdo, $actor, mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? ''));
    $hydrated = mg_creator_campaign_repository_hydrate($pdo, $campaign);
    return mg_creator_campaign_builder_validation($pdo, $hydrated);
}

function mg_mcp_creator_campaign_application_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') {
        $where[] = 'cc.workspace_id=?';
        $params[] = (int)$actor['workspace_id'];
    } else {
        $where[] = 'a.creator_user_id=?';
        $params[] = (int)$actor['creator_user_id'];
    }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = 'a.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare(
        "SELECT a.public_id id,a.status,a.cover_note,a.portfolio_url,a.decision_note,a.submitted_at,a.decided_at,a.updated_at,
                cc.public_id campaign_id,cc.title campaign_title,cp.public_id creator_profile_id,
                COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name
         FROM creator_campaign_applications a
         INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
         INNER JOIN creator_profiles cp ON cp.id=a.creator_profile_id
         INNER JOIN users u ON u.id=a.creator_user_id
         WHERE " . implode(' AND ', $where) . " ORDER BY a.updated_at DESC,a.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_participant_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 'p.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = 'p.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare(
        "SELECT p.public_id id,p.status,p.source_type,p.approved_at,p.agreement_pending_at,p.activated_at,p.completed_at,p.suspended_at,p.updated_at,
                cc.public_id campaign_id,cc.title campaign_title,cp.public_id creator_profile_id,
                COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name
         FROM creator_campaign_participants p
         INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
         INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN users u ON u.id=p.creator_user_id
         WHERE " . implode(' AND ', $where) . " ORDER BY p.updated_at DESC,p.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_deliverable_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 'pd.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = ($actor['mode'] === 'merchant' ? 'd.status=?' : 'pd.status=?'); $params[] = $status; }

    if ($actor['mode'] === 'merchant') {
        $sql = "SELECT d.public_id id,d.title,d.description,d.deliverable_type,d.platform,d.content_format,d.quantity,d.status,d.due_offset_days,d.publication_required,d.proof_required,d.revision_limit,d.sort_order,
                       cc.public_id campaign_id,cc.title campaign_title,
                       (SELECT COUNT(*) FROM creator_campaign_participant_deliverables pd WHERE pd.campaign_deliverable_id=d.id) assignment_count
                FROM creator_campaign_deliverables d INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id
                WHERE " . implode(' AND ', $where) . " ORDER BY cc.updated_at DESC,d.sort_order,d.id LIMIT {$limit} OFFSET {$offset}";
    } else {
        $sql = "SELECT pd.public_id id,pd.status,pd.due_at,pd.assigned_at,pd.approved_at,pd.published_at,pd.verified_at,pd.updated_at,
                       d.public_id deliverable_id,d.title,d.description,d.deliverable_type,d.platform,d.content_format,d.instructions,d.publication_required,d.proof_required,d.revision_limit,
                       cc.public_id campaign_id,cc.title campaign_title,mw.display_name merchant_name,
                       s.public_id submission_id,s.status submission_status,s.current_revision_number
                FROM creator_campaign_participant_deliverables pd
                INNER JOIN creator_campaign_deliverables d ON d.id=pd.campaign_deliverable_id
                INNER JOIN creator_campaigns cc ON cc.id=pd.campaign_id
                INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
                LEFT JOIN creator_campaign_submissions s ON s.participant_deliverable_id=pd.id
                WHERE " . implode(' AND ', $where) . " ORDER BY COALESCE(pd.due_at,'9999-12-31'),pd.id LIMIT {$limit} OFFSET {$offset}";
    }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_submission_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 's.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = 's.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare(
        "SELECT s.public_id id,s.status,s.caption_text,s.content_url,s.platform,s.disclosure_text,s.creator_note,s.merchant_feedback,s.publication_url,s.publication_platform,s.current_revision_number,s.submitted_at,s.reviewed_at,s.updated_at,
                d.public_id deliverable_id,d.title deliverable_title,d.deliverable_type,
                cc.public_id campaign_id,cc.title campaign_title,p.public_id participant_id,
                cp.public_id creator_profile_id,COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name
         FROM creator_campaign_submissions s
         INNER JOIN creator_campaign_participant_deliverables pd ON pd.id=s.participant_deliverable_id
         INNER JOIN creator_campaign_deliverables d ON d.id=pd.campaign_deliverable_id
         INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
         INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
         INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN users u ON u.id=s.creator_user_id
         WHERE " . implode(' AND ', $where) . " ORDER BY s.updated_at DESC,s.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_tracking(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $where = ["s.status<>'retired'"];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 's.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    $stmt = $pdo->prepare(
        "SELECT s.public_id id,s.label,s.channel,s.platform,s.destination_path,s.tracking_code,s.attribution_model,s.click_window_days,s.conversion_window_days,s.status,s.updated_at,
                cc.public_id campaign_id,cc.title campaign_title,p.public_id participant_id,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.source_id=s.id AND e.status='accepted') accepted_events,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.source_id=s.id AND e.event_type='click' AND e.is_unique=1 AND e.status='accepted') unique_clicks,
                (SELECT COUNT(*) FROM creator_campaign_attributions a INNER JOIN creator_campaign_tracking_events ce ON ce.id=a.conversion_event_id WHERE a.source_id=s.id AND a.status IN ('attributed','overridden') AND ce.status='accepted') conversions
         FROM creator_campaign_tracking_sources s
         INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
         INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
         WHERE " . implode(' AND ', $where) . " ORDER BY s.updated_at DESC,s.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $result = mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);

    $eventWhere = ["e.status='accepted'"];
    $eventParams = [];
    if ($actor['mode'] === 'merchant') { $eventWhere[] = 'cc.workspace_id=?'; $eventParams[] = (int)$actor['workspace_id']; }
    else { $eventWhere[] = 'e.creator_user_id=?'; $eventParams[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $eventWhere[] = 'cc.public_id=?'; $eventParams[] = $campaignId; }
    $event = $pdo->prepare("SELECT e.event_type,COUNT(*) total FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE " . implode(' AND ', $eventWhere) . ' GROUP BY e.event_type ORDER BY e.event_type');
    $event->execute($eventParams);
    $result['accepted_event_summary'] = $event->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $result;
}

function mg_mcp_creator_campaign_attribution_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = ["ce.status='accepted'"];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 'a.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = 'a.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare(
        "SELECT a.public_id id,a.attribution_model,a.status,a.confidence_score,a.window_started_at,a.window_ended_at,a.attributed_at,a.updated_at,
                ce.public_id conversion_event_id,ce.event_type conversion_type,ce.occurred_at,
                s.public_id source_id,s.label source_label,p.public_id participant_id,
                cc.public_id campaign_id,cc.title campaign_title,
                cp.public_id creator_profile_id,COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name
         FROM creator_campaign_attributions a
         INNER JOIN creator_campaign_tracking_events ce ON ce.id=a.conversion_event_id
         INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
         LEFT JOIN creator_campaign_tracking_sources s ON s.id=a.source_id
         LEFT JOIN creator_campaign_participants p ON p.id=a.participant_id
         LEFT JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         LEFT JOIN users u ON u.id=p.creator_user_id
         WHERE " . implode(' AND ', $where) . " ORDER BY a.attributed_at DESC,a.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_earning_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 'e.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    $stmt = $pdo->prepare(
        "SELECT e.public_id id,e.event_type,e.source_type,e.source_public_id,e.amount_minor,e.currency,e.reason,e.created_at,
                cc.public_id campaign_id,cc.title campaign_title,p.public_id participant_id,
                cp.public_id creator_profile_id,COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name,r.title rule_title
         FROM creator_campaign_earning_events e
         INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         INNER JOIN creator_campaign_participants p ON p.id=e.participant_id
         INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN users u ON u.id=e.creator_user_id
         LEFT JOIN creator_campaign_compensation_rules r ON r.id=e.rule_id
         WHERE " . implode(' AND ', $where) . " ORDER BY e.created_at DESC,e.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $totals = [];
    foreach ($items as $item) {
        $currency = strtoupper((string)$item['currency']);
        $totals[$currency] = ($totals[$currency] ?? 0) + (int)$item['amount_minor'];
    }
    return mg_mcp_creator_campaign_page_result($items, $limit, $offset) + ['page_totals_minor' => $totals];
}

function mg_mcp_creator_campaign_payout_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 'payout.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = 'payout.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare(
        "SELECT payout.public_id id,payout.status,payout.amount_minor,payout.currency,payout.approved_at,payout.processing_at,payout.paid_at,payout.failed_at,payout.reversed_at,payout.created_at,payout.updated_at,
                cc.public_id campaign_id,cc.title campaign_title,participant.public_id participant_id,
                cp.public_id creator_profile_id,COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name,
                COUNT(item.id) item_count
         FROM creator_campaign_payouts payout
         INNER JOIN creator_campaigns cc ON cc.id=payout.campaign_id
         INNER JOIN creator_campaign_participants participant ON participant.id=payout.participant_id
         INNER JOIN creator_profiles cp ON cp.id=participant.creator_profile_id
         INNER JOIN users u ON u.id=payout.creator_user_id
         LEFT JOIN creator_campaign_payout_items item ON item.payout_id=payout.id
         WHERE " . implode(' AND ', $where) . " GROUP BY payout.id ORDER BY payout.updated_at DESC,payout.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_dispute_list(PDO $pdo, array $actor, array $arguments): array
{
    [$limit, $offset] = mg_mcp_creator_campaign_page($arguments);
    $campaignId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $status = mg_mcp_bridge_text($arguments['status'] ?? '', 40, 'status', false);
    $where = [];
    $params = [];
    if ($actor['mode'] === 'merchant') { $where[] = 'cc.workspace_id=?'; $params[] = (int)$actor['workspace_id']; }
    else { $where[] = 'd.creator_user_id=?'; $params[] = (int)$actor['creator_user_id']; }
    if ($campaignId !== '') { $where[] = 'cc.public_id=?'; $params[] = $campaignId; }
    if ($status !== '') { $where[] = 'd.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare(
        "SELECT d.public_id id,d.source_type,d.source_public_id,d.status,d.reason,d.resolution_note,d.opened_at,d.resolved_at,d.updated_at,
                cc.public_id campaign_id,cc.title campaign_title,p.public_id participant_id,
                cp.public_id creator_profile_id,COALESCE(cp.display_name,u.display_name,u.full_name,'Creator') creator_name
         FROM creator_campaign_disputes d
         INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id
         INNER JOIN creator_campaign_participants p ON p.id=d.participant_id
         INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN users u ON u.id=d.creator_user_id
         WHERE " . implode(' AND ', $where) . " ORDER BY d.updated_at DESC,d.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return mg_mcp_creator_campaign_page_result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $limit, $offset);
}

function mg_mcp_creator_campaign_analytics(PDO $pdo, array $actor, array $arguments): array
{
    $days = max(1, min((int)($arguments['days'] ?? 30), 366));
    $campaignPublicId = mg_mcp_creator_campaign_uuid($arguments['campaign_id'] ?? '', 'campaign_id', false);
    $campaignId = null;
    if ($campaignPublicId !== '') {
        $campaignId = (int)mg_mcp_creator_campaign_resolve($pdo, $actor, $campaignPublicId)['id'];
    }
    $campaignClause = $campaignId !== null ? ' AND cc.id=?' : '';
    $scopeColumn = $actor['mode'] === 'merchant' ? 'cc.workspace_id' : 'e.creator_user_id';
    $scopeValue = $actor['mode'] === 'merchant' ? (int)$actor['workspace_id'] : (int)$actor['creator_user_id'];

    $tracking = $pdo->prepare(
        "SELECT SUM(e.event_type='landing_view') views,SUM(e.event_type='click' AND e.is_unique=1) unique_clicks,SUM(e.event_type='engagement') engagements,COUNT(*) accepted_events
         FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         WHERE e.status='accepted' AND {$scopeColumn}=? AND e.occurred_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY){$campaignClause}"
    );
    $tracking->execute($campaignId !== null ? [$scopeValue, $campaignId] : [$scopeValue]);
    $summary = $tracking->fetch(PDO::FETCH_ASSOC) ?: [];

    $attributionScope = $actor['mode'] === 'merchant' ? 'cc.workspace_id' : 'a.creator_user_id';
    $attribution = $pdo->prepare(
        "SELECT COUNT(*) conversions,SUM(ce.event_type='lead') leads,SUM(ce.event_type='checkout') checkouts,SUM(ce.event_type='purchase') purchases,SUM(ce.event_type='claim') claims,SUM(ce.event_type='redemption') redemptions
         FROM creator_campaign_attributions a
         INNER JOIN creator_campaign_tracking_events ce ON ce.id=a.conversion_event_id AND ce.status='accepted'
         INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
         WHERE a.status IN ('attributed','overridden') AND {$attributionScope}=? AND a.attributed_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY){$campaignClause}"
    );
    $attribution->execute($campaignId !== null ? [$scopeValue, $campaignId] : [$scopeValue]);
    $summary = array_merge($summary, $attribution->fetch(PDO::FETCH_ASSOC) ?: []);
    foreach ($summary as $key => $value) { $summary[$key] = (int)($value ?? 0); }
    $summary['conversion_rate_bps'] = $summary['unique_clicks'] > 0 ? (int)round(10000 * $summary['conversions'] / $summary['unique_clicks']) : 0;

    $financialScope = $actor['mode'] === 'merchant' ? 'cc.workspace_id' : 'e.creator_user_id';
    $earnings = $pdo->prepare(
        "SELECT e.currency,SUM(e.amount_minor) amount_minor FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         WHERE {$financialScope}=? AND e.created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY){$campaignClause} GROUP BY e.currency ORDER BY e.currency"
    );
    $earnings->execute($campaignId !== null ? [$scopeValue, $campaignId] : [$scopeValue]);

    $payoutScope = $actor['mode'] === 'merchant' ? 'cc.workspace_id' : 'p.creator_user_id';
    $payouts = $pdo->prepare(
        "SELECT p.currency,SUM(CASE WHEN p.status='paid' THEN p.amount_minor ELSE 0 END) paid_minor,SUM(CASE WHEN p.status IN ('draft','approved','processing') THEN p.amount_minor ELSE 0 END) scheduled_minor
         FROM creator_campaign_payouts p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
         WHERE {$payoutScope}=? AND p.updated_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY){$campaignClause} GROUP BY p.currency ORDER BY p.currency"
    );
    $payouts->execute($campaignId !== null ? [$scopeValue, $campaignId] : [$scopeValue]);

    return [
        'range' => ['days' => $days, 'generated_at' => gmdate('c')],
        'summary' => $summary,
        'earnings' => $earnings->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'payouts' => $payouts->fetchAll(PDO::FETCH_ASSOC) ?: [],
    ];
}

function mg_mcp_creator_campaign_bridge_dispatch(PDO $pdo, array $context, string $operation, array $arguments): array
{
    $scopeByOperation = [
        'creator_campaigns.list' => 'creator_campaigns:read',
        'creator_campaigns.get' => 'creator_campaigns:read',
        'creator_campaigns.validate' => 'creator_campaigns:read',
        'creator_campaigns.analytics.get' => 'creator_campaigns_analytics:read',
        'creator_campaigns.applications.list' => 'creator_campaign_applications:read',
        'creator_campaigns.participants.list' => 'creator_campaign_participants:read',
        'creator_campaigns.deliverables.list' => 'creator_campaign_deliverables:read',
        'creator_campaigns.submissions.list' => 'creator_campaign_submissions:read',
        'creator_campaigns.tracking.get' => 'creator_campaign_tracking:read',
        'creator_campaigns.attributions.list' => 'creator_campaign_attributions:read',
        'creator_campaigns.earnings.list' => 'creator_campaign_earnings:read',
        'creator_campaigns.payouts.list' => 'creator_campaign_payouts:read',
        'creator_campaigns.disputes.list' => 'creator_campaign_disputes:read',
    ];
    $scope = $scopeByOperation[$operation] ?? null;
    if ($scope === null) {
        throw new MgMcpBridgeException('Creator Campaign bridge operation is not allowed.', 404, 'MCP_CREATOR_CAMPAIGN_OPERATION_UNKNOWN');
    }
    mg_mcp_bridge_require_scope($context, $scope);
    $actor = mg_mcp_creator_campaign_actor($pdo, $context);
    return match ($operation) {
        'creator_campaigns.list' => mg_mcp_creator_campaign_list($pdo, $actor, $arguments),
        'creator_campaigns.get' => mg_mcp_creator_campaign_get($pdo, $actor, $arguments),
        'creator_campaigns.validate' => mg_mcp_creator_campaign_validate($pdo, $actor, $arguments),
        'creator_campaigns.analytics.get' => mg_mcp_creator_campaign_analytics($pdo, $actor, $arguments),
        'creator_campaigns.applications.list' => mg_mcp_creator_campaign_application_list($pdo, $actor, $arguments),
        'creator_campaigns.participants.list' => mg_mcp_creator_campaign_participant_list($pdo, $actor, $arguments),
        'creator_campaigns.deliverables.list' => mg_mcp_creator_campaign_deliverable_list($pdo, $actor, $arguments),
        'creator_campaigns.submissions.list' => mg_mcp_creator_campaign_submission_list($pdo, $actor, $arguments),
        'creator_campaigns.tracking.get' => mg_mcp_creator_campaign_tracking($pdo, $actor, $arguments),
        'creator_campaigns.attributions.list' => mg_mcp_creator_campaign_attribution_list($pdo, $actor, $arguments),
        'creator_campaigns.earnings.list' => mg_mcp_creator_campaign_earning_list($pdo, $actor, $arguments),
        'creator_campaigns.payouts.list' => mg_mcp_creator_campaign_payout_list($pdo, $actor, $arguments),
        'creator_campaigns.disputes.list' => mg_mcp_creator_campaign_dispute_list($pdo, $actor, $arguments),
    };
}
