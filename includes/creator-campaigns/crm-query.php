<?php
declare(strict_types=1);

function mg_creator_campaign_crm_campaign_options(PDO $pdo, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        'SELECT public_id,title,status FROM creator_campaigns WHERE workspace_id=? ORDER BY updated_at DESC,id DESC LIMIT 250'
    );
    $stmt->execute([$workspaceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_crm_summary(PDO $pdo, int $merchantUserId, int $workspaceId): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) relationships,COUNT(DISTINCT r.crm_contact_id) contacts,
                SUM(r.relationship_type='creator_partner') creator_partners,
                SUM(r.relationship_type IN ('customer_lead','customer')) customer_contacts,
                SUM(r.relationship_type='claimant') claimants,
                SUM(r.relationship_type='redeemer') redeemers,
                SUM(r.relationship_status='active') active_relationships
         FROM merchant_crm_contact_creator_campaigns r
         INNER JOIN creator_campaigns cc ON cc.id=r.creator_campaign_id
         WHERE r.merchant_user_id=? AND cc.workspace_id=?"
    );
    $stmt->execute([$merchantUserId,$workspaceId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT SUM(projection_status='completed') completed,SUM(projection_status='skipped') skipped,
                SUM(projection_status='failed') failed,SUM(projection_status='pending') pending
         FROM merchant_crm_creator_campaign_events e
         INNER JOIN creator_campaigns cc ON cc.id=e.creator_campaign_id
         WHERE e.merchant_user_id=? AND cc.workspace_id=?"
    );
    $stmt->execute([$merchantUserId,$workspaceId]);
    $projection = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'relationships'=>(int)($summary['relationships']??0),
        'contacts'=>(int)($summary['contacts']??0),
        'creator_partners'=>(int)($summary['creator_partners']??0),
        'customer_contacts'=>(int)($summary['customer_contacts']??0),
        'claimants'=>(int)($summary['claimants']??0),
        'redeemers'=>(int)($summary['redeemers']??0),
        'active_relationships'=>(int)($summary['active_relationships']??0),
        'projections'=>[
            'completed'=>(int)($projection['completed']??0),
            'skipped'=>(int)($projection['skipped']??0),
            'failed'=>(int)($projection['failed']??0),
            'pending'=>(int)($projection['pending']??0),
        ],
    ];
}

function mg_creator_campaign_crm_list(PDO $pdo, array $user, array $filters = []): array
{
    if (!mg_creator_campaign_crm_installed($pdo)) {
        return ['schema_ready'=>false,'contacts'=>[],'summary'=>[],'campaigns'=>[],'pagination'=>['page'=>1,'pages'=>1,'total'=>0]];
    }
    $context = mg_creator_campaign_actor_context($pdo,$user,'merchant.creator_crm.view');
    $merchantUserId = (int)$context['workspace_owner_user_id'];
    $workspaceId = (int)$context['workspace_id'];
    $page = max(1,(int)($filters['page']??1));
    $limit = max(1,min(100,(int)($filters['limit']??25)));
    $offset = ($page-1)*$limit;
    $query = mg_merchant_crm_search_query($filters['q']??$filters['search']??'');
    $campaignPublicId = trim((string)($filters['campaign_id']??''));
    $relationship = strtolower(trim((string)($filters['relationship_type']??'')));
    $stage = strtolower(trim((string)($filters['lifecycle_stage']??'')));
    $status = strtolower(trim((string)($filters['crm_status']??'')));

    $where = ['r.merchant_user_id=?','cc.workspace_id=?'];
    $params = [$merchantUserId,$workspaceId];
    if ($campaignPublicId !== '') { $where[]='cc.public_id=?'; $params[]=$campaignPublicId; }
    if ($relationship !== '' && in_array($relationship,mg_creator_campaign_crm_relationship_types(),true)) { $where[]='r.relationship_type=?'; $params[]=$relationship; }
    if ($stage !== '') { $where[]='mc.lifecycle_stage=?'; $params[]=$stage; }
    if ($status !== '') { $where[]='mc.crm_status=?'; $params[]=$status; }
    if (mg_merchant_crm_search_column_exists($pdo,'merchant_crm_contacts','merged_into_contact_id')) $where[]='mc.merged_into_contact_id IS NULL';
    if ($query !== '') {
        $like = mg_merchant_crm_search_like($query);
        $where[] = "(LOWER(COALESCE(mc.display_name,'')) LIKE ? ESCAPE '\\\\' OR LOWER(COALESCE(mc.primary_email,'')) LIKE ? ESCAPE '\\\\'
                    OR LOWER(COALESCE(mc.primary_phone,'')) LIKE ? ESCAPE '\\\\' OR LOWER(COALESCE(mc.lifecycle_stage,'')) LIKE ? ESCAPE '\\\\'
                    OR LOWER(COALESCE(r.relationship_type,'')) LIKE ? ESCAPE '\\\\' OR LOWER(COALESCE(cc.title,'')) LIKE ? ESCAPE '\\\\'
                    OR LOWER(COALESCE(cc.public_id,'')) LIKE ? ESCAPE '\\\\' OR LOWER(COALESCE(mc.public_id,'')) LIKE ? ESCAPE '\\\\')";
        array_push($params,$like,$like,$like,$like,$like,$like,$like,$like);
    }
    $whereSql = implode(' AND ',$where);
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM merchant_crm_contact_creator_campaigns r
         INNER JOIN merchant_crm_contacts mc ON mc.id=r.crm_contact_id
         INNER JOIN creator_campaigns cc ON cc.id=r.creator_campaign_id WHERE '.$whereSql
    );
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $parts = mg_merchant_crm_search_select_parts($pdo);
    $sql = 'SELECT '.$parts['select'].',r.public_id relationship_public_id,r.relationship_type,r.relationship_status,
                   r.first_event_at,r.last_event_at,r.event_count,r.last_event_type,r.metadata_json relationship_metadata_json,
                   cc.public_id creator_campaign_public_id,cc.title creator_campaign_title,cc.status creator_campaign_status
            FROM merchant_crm_contact_creator_campaigns r
            INNER JOIN merchant_crm_contacts mc ON mc.id=r.crm_contact_id
            INNER JOIN creator_campaigns cc ON cc.id=r.creator_campaign_id'.$parts['joins'].'
            WHERE '.$whereSql.' ORDER BY r.last_event_at DESC,r.id DESC LIMIT '.$limit.' OFFSET '.$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $contact = mg_merchant_crm_search_row($row);
        $contact['relationship_id'] = (string)$row['relationship_public_id'];
        $contact['relationship_type'] = (string)$row['relationship_type'];
        $contact['relationship_status'] = (string)$row['relationship_status'];
        $contact['relationship_first_at'] = $row['first_event_at'];
        $contact['relationship_last_at'] = $row['last_event_at'];
        $contact['relationship_event_count'] = (int)$row['event_count'];
        $contact['relationship_last_event'] = (string)$row['last_event_type'];
        $contact['creator_campaign_id'] = (string)$row['creator_campaign_public_id'];
        $contact['creator_campaign_title'] = (string)$row['creator_campaign_title'];
        $contact['creator_campaign_status'] = (string)$row['creator_campaign_status'];
        $contact['creator_campaign_url'] = '/merchant-creator-campaign-detail.php?campaign='.rawurlencode((string)$row['creator_campaign_public_id']);
        $contact['relationship_metadata'] = mg_creator_campaign_participation_decode_json($row['relationship_metadata_json']??null) ?: [];
        $rows[] = $contact;
    }
    $pages = max(1,(int)ceil($total/$limit));
    return [
        'schema_ready'=>true,
        'contacts'=>$rows,
        'summary'=>mg_creator_campaign_crm_summary($pdo,$merchantUserId,$workspaceId),
        'campaigns'=>mg_creator_campaign_crm_campaign_options($pdo,$workspaceId),
        'relationship_types'=>mg_creator_campaign_crm_relationship_types(),
        'pagination'=>['page'=>$page,'pages'=>$pages,'total'=>$total,'limit'=>$limit],
    ];
}

function mg_creator_campaign_crm_runs(PDO $pdo, array $user, int $limit = 20): array
{
    if (!mg_creator_campaign_crm_installed($pdo)) return ['schema_ready'=>false,'runs'=>[]];
    $context = mg_creator_campaign_actor_context($pdo,$user,'merchant.creator_crm.view');
    $limit = max(1,min(100,$limit));
    $stmt = $pdo->prepare(
        'SELECT r.public_id,r.run_mode,r.status,r.participation_scanned,r.tracking_scanned,r.projected_count,
                r.replay_count,r.skipped_count,r.failed_count,r.started_at,r.completed_at,
                cc.public_id campaign_public_id,cc.title campaign_title
         FROM merchant_crm_creator_campaign_projection_runs r
         LEFT JOIN creator_campaigns cc ON cc.id=r.creator_campaign_id
         WHERE r.merchant_user_id=? ORDER BY r.id DESC LIMIT '.$limit
    );
    $stmt->execute([(int)$context['workspace_owner_user_id']]);
    return ['schema_ready'=>true,'runs'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}
