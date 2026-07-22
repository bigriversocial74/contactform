<?php
declare(strict_types=1);

function mg_creator_campaign_participation_dashboard_merchant(PDO $pdo, array $user): array
{
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_applications.view'
    );
    $workspaceId = (int) $context['workspace_id'];
    $metrics = [];
    $queries = [
        'campaigns' => 'SELECT COUNT(*) FROM creator_campaigns WHERE workspace_id=?',
        'pending_applications' => "SELECT COUNT(*) FROM creator_campaign_applications a INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE cc.workspace_id=? AND a.status IN ('submitted','under_review','information_requested')",
        'pending_invitations' => "SELECT COUNT(*) FROM creator_campaign_invitations i INNER JOIN creator_campaigns cc ON cc.id=i.campaign_id WHERE cc.workspace_id=? AND i.status='pending'",
        'participants' => "SELECT COUNT(*) FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE cc.workspace_id=? AND p.status IN ('approved','agreement_pending','active','completed','suspended')",
        'agreement_pending' => "SELECT COUNT(*) FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE cc.workspace_id=? AND p.status='agreement_pending'",
    ];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$workspaceId]);
        $metrics[$key] = (int) $stmt->fetchColumn();
    }

    $campaigns = $pdo->prepare(
        "SELECT cc.public_id,cc.title,cc.status,cc.access_mode,cc.application_deadline_at,
                cc.maximum_approved_creators,cc.maximum_applications,
                (SELECT COUNT(*) FROM creator_campaign_applications a
                 WHERE a.campaign_id=cc.id AND a.status IN ('submitted','under_review','information_requested')) pending_applications,
                (SELECT COUNT(*) FROM creator_campaign_invitations i WHERE i.campaign_id=cc.id) invitations,
                (SELECT COUNT(*) FROM creator_campaign_invitations i
                 WHERE i.campaign_id=cc.id AND i.status='pending') pending_invitations,
                (SELECT COUNT(*) FROM creator_campaign_participants p
                 WHERE p.campaign_id=cc.id AND p.status IN ('approved','agreement_pending','active','completed','suspended')) participants,
                (SELECT COUNT(*) FROM creator_campaign_participants p
                 WHERE p.campaign_id=cc.id AND p.status='agreement_pending') agreement_pending
         FROM creator_campaigns cc
         WHERE cc.workspace_id=?
         ORDER BY cc.updated_at DESC,cc.id DESC
         LIMIT 100"
    );
    $campaigns->execute([$workspaceId]);

    return [
        'metrics' => $metrics,
        'campaigns' => $campaigns->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'definitions' => [
            'application_statuses' => mg_creator_campaign_application_statuses(),
            'invitation_statuses' => mg_creator_campaign_invitation_statuses(),
            'participant_statuses' => mg_creator_campaign_participant_statuses(),
        ],
        'phase4_installed' => mg_creator_campaign_participation_phase4_installed($pdo),
    ];
}

function mg_creator_campaign_discover_creator(PDO $pdo, array $user, array $filters = []): array
{
    $context = mg_creator_campaign_creator_context($pdo, $user, 'creator.campaigns.discover');
    $creatorUserId = (int) $context['creator_user_id'];
    $search = trim((string) ($filters['search'] ?? ''));
    $category = trim((string) ($filters['category'] ?? ''));
    $objective = trim((string) ($filters['objective'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(50, max(8, (int) ($filters['per_page'] ?? 12)));

    $where = [
        "cc.status IN ('scheduled','active')",
        "cc.access_mode IN ('open','approved_creators','hybrid')",
        '(cc.application_deadline_at IS NULL OR cc.application_deadline_at>=NOW())',
    ];
    $filterParams = [];
    if ($search !== '') {
        $where[] = '(cc.title LIKE ? OR cc.description LIKE ? OR cc.objective LIKE ? OR mw.display_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($filterParams, $like, $like, $like, $like);
    }
    if ($category !== '') {
        $where[] = 'cc.category=?';
        $filterParams[] = $category;
    }
    if ($objective !== '') {
        $where[] = 'cc.objective=?';
        $filterParams[] = $objective;
    }

    $from = ' FROM creator_campaigns cc
              INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
              LEFT JOIN creator_campaign_applications a ON a.campaign_id=cc.id AND a.creator_user_id=?
              LEFT JOIN creator_campaign_participants p ON p.campaign_id=cc.id AND p.creator_user_id=?
              WHERE ' . implode(' AND ', $where);
    $params = array_merge([$creatorUserId, $creatorUserId], $filterParams);

    $count = $pdo->prepare('SELECT COUNT(DISTINCT cc.id)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT cc.public_id,cc.title,cc.description,cc.objective,cc.category,cc.campaign_focus,
                cc.access_mode,cc.timezone,cc.starts_at,cc.ends_at,cc.application_deadline_at,
                cc.geographic_scope_json,cc.creator_product_access,cc.maximum_approved_creators,
                mw.display_name merchant_name,
                a.public_id application_public_id,a.status application_status,a.lock_version application_lock_version,
                p.public_id participant_public_id,p.status participant_status,
                (SELECT COUNT(*) FROM creator_campaign_products cp WHERE cp.campaign_id=cc.id AND cp.relationship_type<>'excluded') product_count,
                (SELECT COUNT(*) FROM creator_campaign_eligibility_rules er WHERE er.campaign_id=cc.id) eligibility_rule_count,
                (SELECT COUNT(*) FROM creator_campaign_application_questions aq WHERE aq.campaign_id=cc.id) application_question_count"
        . $from .
        ' ORDER BY COALESCE(cc.starts_at,cc.created_at),cc.id DESC
          LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as &$item) {
        $item['geographic_scope'] = mg_creator_campaign_participation_decode_json($item['geographic_scope_json'] ?? null);
        unset($item['geographic_scope_json']);
    }
    unset($item);

    $categories = $pdo->query(
        "SELECT DISTINCT category FROM creator_campaigns
         WHERE status IN ('scheduled','active') AND category IS NOT NULL AND category<>'' ORDER BY category"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $objectives = $pdo->query(
        "SELECT DISTINCT objective FROM creator_campaigns
         WHERE status IN ('scheduled','active') AND objective IS NOT NULL AND objective<>'' ORDER BY objective"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    return [
        'items' => $items,
        'filters' => ['categories' => $categories, 'objectives' => $objectives],
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
    ];
}

function mg_creator_campaign_detail_creator(
    PDO $pdo,
    array $user,
    string $campaignPublicId
): array {
    $context = mg_creator_campaign_creator_context($pdo, $user, 'creator.campaigns.discover');
    $creatorUserId = (int) $context['creator_user_id'];
    $campaign = mg_creator_campaign_participation_campaign_by_public_id($pdo, $campaignPublicId);
    if (!mg_creator_campaign_participation_public_campaign($campaign)) {
        $invited = $pdo->prepare(
            "SELECT 1 FROM creator_campaign_invitations
             WHERE campaign_id=? AND creator_user_id=? AND status IN ('pending','accepted') LIMIT 1"
        );
        $invited->execute([(int) $campaign['id'], $creatorUserId]);
        if (!$invited->fetchColumn()) throw new RuntimeException('Creator campaign not found.');
    }

    $campaign['products'] = mg_creator_campaign_participation_products($pdo, (int) $campaign['id']);
    $campaign['questions'] = mg_creator_campaign_participation_questions($pdo, (int) $campaign['id']);
    $rules = $pdo->prepare(
        'SELECT public_id,rule_type,operator_key,value_json,is_required,sort_order
         FROM creator_campaign_eligibility_rules WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $rules->execute([(int) $campaign['id']]);
    $campaign['eligibility_rules'] = $rules->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($campaign['eligibility_rules'] as &$rule) {
        $rule['value'] = mg_creator_campaign_participation_decode_json($rule['value_json'] ?? null);
        unset($rule['value_json']);
    }
    unset($rule);

    $application = $pdo->prepare(
        'SELECT id,public_id,status,cover_note,portfolio_url,decision_note,lock_version,updated_at
         FROM creator_campaign_applications WHERE campaign_id=? AND creator_user_id=? LIMIT 1'
    );
    $application->execute([(int) $campaign['id'], $creatorUserId]);
    $campaign['application'] = $application->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($campaign['application']) {
        $campaign['application']['answers'] = mg_creator_campaign_participation_answer_rows(
            $pdo, (int) $campaign['application']['id']
        );
        unset($campaign['application']['id']);
    }

    $invitation = $pdo->prepare(
        'SELECT public_id,status,invitation_message,response_deadline_at,lock_version,updated_at
         FROM creator_campaign_invitations WHERE campaign_id=? AND creator_user_id=? LIMIT 1'
    );
    $invitation->execute([(int) $campaign['id'], $creatorUserId]);
    $campaign['invitation'] = $invitation->fetch(PDO::FETCH_ASSOC) ?: null;

    $participant = $pdo->prepare(
        'SELECT public_id,status,source_type,approved_at,agreement_pending_at,lock_version,updated_at
         FROM creator_campaign_participants WHERE campaign_id=? AND creator_user_id=? LIMIT 1'
    );
    $participant->execute([(int) $campaign['id'], $creatorUserId]);
    $campaign['participant'] = $participant->fetch(PDO::FETCH_ASSOC) ?: null;
    $campaign['application_capacity'] = mg_creator_campaign_application_count_capacity($pdo, $campaign);
    $campaign['participant_capacity'] = mg_creator_campaign_participant_capacity($pdo, $campaign);
    $campaign['phase4_installed'] = mg_creator_campaign_participation_phase4_installed($pdo);

    return ['campaign' => $campaign];
}

function mg_creator_campaign_participation_timeline_merchant(
    PDO $pdo,
    array $user,
    string $campaignPublicId
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_participants.view'
    );
    $campaign = mg_creator_campaign_participation_campaign_by_public_id(
        $pdo, $campaignPublicId, (int) $context['workspace_id']
    );
    $stmt = $pdo->prepare(
        'SELECT e.public_id,e.event_type,e.from_status,e.to_status,e.reason,e.context_json,e.created_at,
                u.display_name actor_display_name,u.full_name actor_full_name,
                a.public_id application_public_id,i.public_id invitation_public_id,p.public_id participant_public_id
         FROM creator_campaign_participation_events e
         INNER JOIN users u ON u.id=e.actor_user_id
         LEFT JOIN creator_campaign_applications a ON a.id=e.application_id
         LEFT JOIN creator_campaign_invitations i ON i.id=e.invitation_id
         LEFT JOIN creator_campaign_participants p ON p.id=e.participant_id
         WHERE e.campaign_id=?
         ORDER BY e.created_at DESC,e.id DESC LIMIT 250'
    );
    $stmt->execute([(int) $campaign['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as &$item) {
        $item['context'] = mg_creator_campaign_participation_decode_json($item['context_json'] ?? null);
        unset($item['context_json']);
    }
    unset($item);
    return ['items' => $items];
}
