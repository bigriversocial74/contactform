<?php
declare(strict_types=1);

function mg_creator_campaign_pilot_workspace(PDO $pdo, int $userId, string $workspacePublicId = ''): array
{
    $sql = "SELECT * FROM merchant_workspaces WHERE merchant_user_id=?";
    $params = [$userId];
    if ($workspacePublicId !== '') {
        $sql .= ' AND public_id=?';
        $params[] = $workspacePublicId;
    }
    $sql .= " ORDER BY FIELD(status,'active','pending_review','draft','suspended','archived'),updated_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MgCreatorCampaignPilotException(
            'A merchant workspace is required to operate a Creator Campaign pilot.',
            403,
            'CREATOR_CAMPAIGN_PILOT_WORKSPACE_REQUIRED'
        );
    }
    return $row;
}

function mg_creator_campaign_pilot_row(PDO $pdo, int $userId, int $workspaceId, bool $lock = false): ?array
{
    if (!mg_creator_campaign_pilot_schema_ready($pdo)) return null;
    $sql = 'SELECT * FROM creator_campaign_operator_pilots WHERE owner_user_id=? AND workspace_id=? LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['checklist'] = mg_creator_campaign_pilot_json($row['checklist_json'] ?? null);
    $row['readiness_snapshot'] = mg_creator_campaign_pilot_json($row['readiness_snapshot_json'] ?? null);
    return $row;
}

function mg_creator_campaign_pilot_connections(PDO $pdo, int $userId, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        "SELECT c.id,c.public_id,c.display_name,c.status,c.maximum_operation_class,c.expires_at,c.last_activity_at,
                cl.public_id client_public_id,cl.display_name client_name,cl.status client_status,
                cl.maximum_operation_class client_maximum_operation_class,
                GROUP_CONCAT(CASE WHEN cs.revoked_at IS NULL THEN cs.scope_key END ORDER BY cs.scope_key SEPARATOR ',') scope_csv
         FROM mcp_connections c
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN mcp_connection_scopes cs ON cs.connection_id=c.id
         WHERE c.user_id=? AND c.workspace_id=? AND c.workspace_type IN ('merchant','merchant_workspace')
         GROUP BY c.id,cl.id
         ORDER BY FIELD(c.status,'active','pending','paused','expired','disabled','revoked'),c.updated_at DESC"
    );
    $stmt->execute([$userId, $workspaceId]);
    return array_map(static function(array $row): array {
        $row['scopes'] = array_values(array_filter(explode(',', (string)($row['scope_csv'] ?? ''))));
        unset($row['scope_csv']);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_grants(PDO $pdo, int $userId, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        "SELECT g.*,c.public_id connection_public_id,c.display_name connection_name,c.status connection_status,
                c.maximum_operation_class connection_maximum_operation_class,
                cl.public_id client_public_id,cl.display_name client_name,cl.status client_status,
                cl.maximum_operation_class client_maximum_operation_class
         FROM mcp_automation_grants g
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         WHERE g.authorizing_user_id=? AND g.workspace_id=? AND g.workspace_type IN ('merchant','merchant_workspace')
         ORDER BY FIELD(g.status,'active','draft','paused','expired','revoked'),g.updated_at DESC,g.id DESC"
    );
    $stmt->execute([$userId, $workspaceId]);
    return array_map(static function(array $row): array {
        $row['allowed_tools'] = mg_mcp_automation_json_list($row['allowed_tools_json'] ?? []);
        $row['allowed_playbooks'] = mg_mcp_automation_json_list($row['allowed_playbooks_json'] ?? []);
        $row['allowed_triggers'] = mg_mcp_automation_json_list($row['allowed_trigger_types_json'] ?? []);
        $row['target_policy'] = mg_mcp_automation_json_object($row['target_policy_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_definitions(PDO $pdo, int $userId, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        "SELECT a.*,g.public_id grant_public_id,g.status grant_status,g.expires_at grant_expires_at,
                c.public_id connection_public_id,c.display_name connection_name,c.status connection_status,
                cl.display_name client_name,cl.status client_status,
                t.public_id trigger_public_id,t.status trigger_status,t.fire_count,
                COALESCE(r.run_count,0) run_count,
                COALESCE(r.failed_count,0) failed_count,
                r.last_run_status,r.last_run_at AS latest_run_at,r.last_run_public_id
         FROM mcp_automations a
         INNER JOIN mcp_automation_grants g ON g.id=a.grant_id
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN mcp_automation_triggers t ON t.automation_id=a.id AND t.trigger_type='manual'
         LEFT JOIN (
             SELECT rr.automation_id,
                    COUNT(*) run_count,
                    SUM(rr.status IN ('failed','dead_lettered')) failed_count,
                    SUBSTRING_INDEX(GROUP_CONCAT(rr.status ORDER BY rr.id DESC),',',1) last_run_status,
                    MAX(rr.created_at) last_run_at,
                    SUBSTRING_INDEX(GROUP_CONCAT(rr.public_id ORDER BY rr.id DESC),',',1) last_run_public_id
             FROM mcp_automation_runs rr
             GROUP BY rr.automation_id
         ) r ON r.automation_id=a.id
         WHERE a.owner_user_id=? AND a.workspace_id=?
           AND JSON_UNQUOTE(JSON_EXTRACT(a.configuration_json,'$.mode'))='manual_bounded_playbook'
         ORDER BY FIELD(a.status,'active','draft','paused','failed','expired','revoked'),a.updated_at DESC"
    );
    $stmt->execute([$userId, $workspaceId]);
    return array_map(static function(array $row): array {
        $row['configuration'] = mg_mcp_automation_json_object($row['configuration_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_runs(PDO $pdo, int $userId, int $workspaceId, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT r.*,a.public_id automation_public_id,a.name automation_name,a.playbook_key,a.status automation_status,
                g.public_id grant_public_id,g.status grant_status,
                d.public_id artifact_public_id,d.status artifact_status,d.title artifact_title,
                ar.public_id receipt_public_id,ar.status receipt_status,ar.created_at receipt_created_at
         FROM mcp_automation_runs r
         INNER JOIN mcp_automations a ON a.id=r.automation_id
         INNER JOIN mcp_automation_grants g ON g.id=r.grant_id
         LEFT JOIN mcp_agent_drafts d
           ON d.public_id=JSON_UNQUOTE(JSON_EXTRACT(r.output_summary_json,'$.artifact_id'))
         LEFT JOIN mcp_action_receipts ar ON ar.run_id=r.id
         WHERE a.owner_user_id=? AND a.workspace_id=?
           AND JSON_UNQUOTE(JSON_EXTRACT(r.output_summary_json,'$.mode'))='manual_bounded_playbook'
         ORDER BY r.created_at DESC,r.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([$userId, $workspaceId]);
    return array_map(static function(array $row): array {
        $row['summary'] = mg_mcp_automation_json_object($row['output_summary_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_artifacts(PDO $pdo, int $userId, int $workspaceId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        mg_mcp_draft_select_sql(
            "WHERE d.owner_user_id=? AND d.workspace_id=?
               AND JSON_EXTRACT(d.payload_json,'$.creator_campaign_playbook_output')=true
             ORDER BY d.created_at DESC,d.id DESC LIMIT " . $limit
        )
    );
    $stmt->execute([$userId, $workspaceId]);
    return array_map(static function(array $row): array {
        $projection = mg_mcp_draft_projection($row);
        $projection['database_id'] = (int)$row['id'];
        return $projection;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_action_grants(PDO $pdo, int $userId, int $workspaceId): array
{
    $grants = mg_creator_campaign_pilot_grants($pdo, $userId, $workspaceId);
    return array_values(array_filter($grants, static function(array $grant): bool {
        return (string)$grant['status'] === 'active'
            && (string)$grant['maximum_operation_class'] === 'approval_gated'
            && (string)$grant['connection_status'] === 'active'
            && (string)$grant['client_status'] === 'active';
    }));
}

function mg_creator_campaign_pilot_security_events(PDO $pdo, int $userId, int $workspaceId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT public_id,severity,event_type,message,evidence_json,created_at
         FROM mcp_security_events
         WHERE user_id=? AND workspace_id=?
           AND (event_type LIKE 'mcp.creator_campaign.%' OR event_type LIKE 'creator_campaign.pilot.%')
         ORDER BY created_at DESC,id DESC
         LIMIT " . $limit
    );
    $stmt->execute([$userId, $workspaceId]);
    return array_map(static function(array $row): array {
        $row['evidence'] = mg_creator_campaign_pilot_json($row['evidence_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_events(PDO $pdo, int $pilotId, int $limit = 30): array
{
    if (!mg_creator_campaign_pilot_schema_ready($pdo) || $pilotId < 1) return [];
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT * FROM creator_campaign_operator_events WHERE pilot_id=?
         ORDER BY created_at DESC,id DESC LIMIT " . $limit
    );
    $stmt->execute([$pilotId]);
    return array_map(static function(array $row): array {
        $row['metadata'] = mg_creator_campaign_pilot_json($row['metadata_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_handoffs(PDO $pdo, int $pilotId, int $limit = 30): array
{
    if (!mg_creator_campaign_pilot_schema_ready($pdo) || $pilotId < 1) return [];
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT h.*,d.public_id draft_public_id,d.title draft_title
         FROM creator_campaign_operator_handoffs h
         INNER JOIN mcp_agent_drafts d ON d.id=h.source_draft_id
         WHERE h.pilot_id=?
         ORDER BY h.created_at DESC,h.id DESC LIMIT " . $limit
    );
    $stmt->execute([$pilotId]);
    return array_map(static function(array $row): array {
        $row['input'] = mg_creator_campaign_pilot_json($row['input_json'] ?? null);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_creator_campaign_pilot_draft_row(PDO $pdo, int $userId, int $workspaceId, string $draftPublicId): array
{
    $stmt = $pdo->prepare(
        mg_mcp_draft_select_sql(
            "WHERE d.public_id=? AND d.owner_user_id=? AND d.workspace_id=?
               AND JSON_EXTRACT(d.payload_json,'$.creator_campaign_playbook_output')=true
             LIMIT 1"
        )
    );
    $stmt->execute([$draftPublicId, $userId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MgCreatorCampaignPilotException(
            'The selected playbook review artifact was not found.',
            404,
            'CREATOR_CAMPAIGN_PILOT_ARTIFACT_NOT_FOUND'
        );
    }
    return $row;
}

function mg_creator_campaign_pilot_action_grant_row(
    PDO $pdo,
    int $userId,
    int $workspaceId,
    string $grantPublicId
): array {
    $stmt = $pdo->prepare(
        "SELECT g.*,c.public_id connection_public_id,c.status connection_status,
                c.maximum_operation_class connection_maximum_operation_class,
                cl.id client_id,cl.public_id client_public_id,cl.status client_status,
                cl.maximum_operation_class client_maximum_operation_class,
                GROUP_CONCAT(CASE WHEN cs.revoked_at IS NULL THEN cs.scope_key END ORDER BY cs.scope_key SEPARATOR ',') scope_csv
         FROM mcp_automation_grants g
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN mcp_connection_scopes cs ON cs.connection_id=c.id
         WHERE g.public_id=? AND g.authorizing_user_id=? AND g.workspace_id=?
         GROUP BY g.id,c.id,cl.id LIMIT 1"
    );
    $stmt->execute([$grantPublicId, $userId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MgCreatorCampaignPilotException('The approval-gated action grant was not found.', 404, 'CREATOR_CAMPAIGN_PILOT_ACTION_GRANT_NOT_FOUND');
    }
    $row['scopes'] = array_values(array_filter(explode(',', (string)($row['scope_csv'] ?? ''))));
    $row['allowed_tools'] = mg_mcp_automation_json_list($row['allowed_tools_json'] ?? []);
    return $row;
}

function mg_creator_campaign_pilot_run_row(PDO $pdo, int $userId, int $workspaceId, string $runPublicId): array
{
    $stmt = $pdo->prepare(
        "SELECT r.*,a.public_id automation_public_id,a.name automation_name,a.playbook_key
         FROM mcp_automation_runs r
         INNER JOIN mcp_automations a ON a.id=r.automation_id
         WHERE r.public_id=? AND a.owner_user_id=? AND a.workspace_id=? LIMIT 1"
    );
    $stmt->execute([$runPublicId, $userId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MgCreatorCampaignPilotException('The selected playbook run was not found.', 404, 'CREATOR_CAMPAIGN_PILOT_RUN_NOT_FOUND');
    }
    $row['summary'] = mg_mcp_automation_json_object($row['output_summary_json'] ?? null);
    return $row;
}
