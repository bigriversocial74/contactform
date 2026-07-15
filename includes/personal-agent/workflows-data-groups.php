<?php
declare(strict_types=1);

function mg_personal_workflows_group_gifts(PDO $pdo, int $userId): array
{
    mg_personal_workflows_require_schema($pdo);
    $stmt = $pdo->prepare("SELECT g.*,p.public_id plan_public_id,p.title plan_title,l.public_id list_public_id,l.name list_name,
        c.public_id contact_public_id,c.display_name contact_name,u.public_id recipient_public_id,
        COALESCE(pp.display_name,u.display_name,u.full_name) recipient_name,
        (SELECT COUNT(*) FROM user_group_gift_participants gp WHERE gp.group_gift_id=g.id AND gp.status IN ('invited','joined')) participant_count,
        (SELECT COUNT(*) FROM user_group_gift_participants gp WHERE gp.group_gift_id=g.id AND gp.status='joined') joined_count
        FROM user_group_gifts g
        LEFT JOIN user_gifting_plans p ON p.id=g.plan_id AND p.owner_user_id=g.organizer_user_id
        LEFT JOIN user_contact_lists l ON l.id=g.list_id AND l.owner_user_id=g.organizer_user_id
        LEFT JOIN user_contacts c ON c.id=g.recipient_user_contact_id AND c.owner_user_id=g.organizer_user_id
        LEFT JOIN users u ON u.id=g.recipient_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=g.recipient_user_id
        WHERE g.organizer_user_id=? ORDER BY FIELD(g.status,'open','draft','locked','fulfilled','closed','cancelled'),g.deadline_at,g.id");
    $stmt->execute([$userId]);
    $owned = array_map(static function(array $row): array {
        $recipient=['type'=>'none','id'=>'','name'=>''];
        if (!empty($row['contact_public_id'])) $recipient=['type'=>'contact','id'=>(string)$row['contact_public_id'],'name'=>(string)$row['contact_name']];
        elseif (!empty($row['recipient_public_id'])) $recipient=['type'=>'linked_user','id'=>(string)$row['recipient_public_id'],'name'=>(string)$row['recipient_name']];
        return [
            'id'=>(string)$row['public_id'],
            'title'=>(string)$row['title'],
            'description'=>(string)($row['description'] ?? ''),
            'goal'=>mg_personal_workflows_money((int)$row['goal_cents']),
            'pledged'=>mg_personal_workflows_money((int)$row['pledged_cents']),
            'min_contribution'=>mg_personal_workflows_money($row['min_contribution_cents'] !== null ? (int)$row['min_contribution_cents'] : null),
            'max_contribution'=>mg_personal_workflows_money($row['max_contribution_cents'] !== null ? (int)$row['max_contribution_cents'] : null),
            'currency'=>(string)$row['currency'],
            'deadline_at'=>(string)$row['deadline_at'],
            'status'=>(string)$row['status'],
            'contribution_mode'=>(string)$row['contribution_mode'],
            'participant_count'=>(int)$row['participant_count'],
            'joined_count'=>(int)$row['joined_count'],
            'recipient'=>$recipient,
            'list'=>!empty($row['list_public_id']) ? ['id'=>(string)$row['list_public_id'],'name'=>(string)$row['list_name']] : null,
            'plan'=>!empty($row['plan_public_id']) ? ['id'=>(string)$row['plan_public_id'],'title'=>(string)$row['plan_title']] : null,
            'created_at'=>$row['created_at'],
            'updated_at'=>$row['updated_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    foreach ($owned as &$ownedGroup) {
        $ownedGroup['participants']=mg_personal_workflows_group_participants($pdo,$userId,(string)$ownedGroup['id']);
    }
    unset($ownedGroup);

    $incomingStmt = $pdo->prepare("SELECT gp.public_id participant_id,g.public_id,g.title,g.description,g.goal_cents,g.pledged_cents,g.currency,g.deadline_at,g.status,
        gp.status participant_status,gp.pledge_cents,gp.is_anonymous,gp.invite_message,
        COALESCE(pp.display_name,u.display_name,u.full_name,'Organizer') organizer_name
        FROM user_group_gift_participants gp
        INNER JOIN user_group_gifts g ON g.id=gp.group_gift_id
        INNER JOIN users u ON u.id=g.organizer_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=u.id
        WHERE gp.invited_user_id=? AND gp.role_key='contributor' AND gp.status IN ('invited','joined','declined')
        ORDER BY FIELD(gp.status,'invited','joined','declined'),g.deadline_at");
    $incomingStmt->execute([$userId]);
    $incoming = array_map(static fn(array $row): array => [
        'participant_id'=>(string)$row['participant_id'],
        'id'=>(string)$row['public_id'],
        'title'=>(string)$row['title'],
        'description'=>(string)($row['description'] ?? ''),
        'goal'=>mg_personal_workflows_money((int)$row['goal_cents']),
        'pledged'=>mg_personal_workflows_money((int)$row['pledged_cents']),
        'currency'=>(string)$row['currency'],
        'deadline_at'=>(string)$row['deadline_at'],
        'status'=>(string)$row['status'],
        'participant_status'=>(string)$row['participant_status'],
        'my_pledge'=>mg_personal_workflows_money($row['pledge_cents'] !== null ? (int)$row['pledge_cents'] : null),
        'is_anonymous'=>(bool)$row['is_anonymous'],
        'invite_message'=>(string)($row['invite_message'] ?? ''),
        'organizer_name'=>(string)$row['organizer_name'],
    ], $incomingStmt->fetchAll(PDO::FETCH_ASSOC));

    return ['owned'=>$owned,'incoming'=>$incoming];
}

function mg_personal_workflows_requests(PDO $pdo, int $userId): array
{
    mg_personal_workflows_require_schema($pdo);
    $stmt = $pdo->prepare("SELECT r.*,COALESCE(pp.display_name,u.display_name,u.full_name,'Microgifter user') subject_name,u.public_id subject_public_id
        FROM user_recipient_data_requests r INNER JOIN users u ON u.id=r.subject_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=u.id WHERE r.requester_user_id=? ORDER BY r.created_at DESC");
    $stmt->execute([$userId]);
    $outgoing = array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],
        'direction'=>'outgoing',
        'person'=>['id'=>(string)$row['subject_public_id'],'name'=>(string)$row['subject_name']],
        'request_type'=>(string)$row['request_type'],
        'requested_scopes'=>mg_personal_agent_json($row['requested_scopes_json']),
        'approved_scopes'=>mg_personal_agent_json($row['approved_scopes_json'] ?? null),
        'message'=>(string)($row['message'] ?? ''),
        'response_note'=>(string)($row['response_note'] ?? ''),
        'status'=>(string)$row['status'],
        'expires_at'=>$row['expires_at'] ?: null,
        'responded_at'=>$row['responded_at'] ?: null,
        'created_at'=>$row['created_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $incomingStmt = $pdo->prepare("SELECT r.*,COALESCE(pp.display_name,u.display_name,u.full_name,'Microgifter user') requester_name,u.public_id requester_public_id
        FROM user_recipient_data_requests r INNER JOIN users u ON u.id=r.requester_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=u.id WHERE r.subject_user_id=? ORDER BY FIELD(r.status,'pending','approved','partially_approved','declined','revoked','cancelled','expired'),r.created_at DESC");
    $incomingStmt->execute([$userId]);
    $incoming = array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],
        'direction'=>'incoming',
        'person'=>['id'=>(string)$row['requester_public_id'],'name'=>(string)$row['requester_name']],
        'request_type'=>(string)$row['request_type'],
        'requested_scopes'=>mg_personal_agent_json($row['requested_scopes_json']),
        'approved_scopes'=>mg_personal_agent_json($row['approved_scopes_json'] ?? null),
        'message'=>(string)($row['message'] ?? ''),
        'response_note'=>(string)($row['response_note'] ?? ''),
        'status'=>(string)$row['status'],
        'expires_at'=>$row['expires_at'] ?: null,
        'responded_at'=>$row['responded_at'] ?: null,
        'created_at'=>$row['created_at'],
    ], $incomingStmt->fetchAll(PDO::FETCH_ASSOC));
    return ['outgoing'=>$outgoing,'incoming'=>$incoming];
}

function mg_personal_workflows_catalog(PDO $pdo, int $limit = 40): array
{
    if (!mg_personal_agent_table_exists($pdo, 'catalog_products') || !mg_personal_agent_table_exists($pdo, 'catalog_product_versions')) return [];
    $stmt = $pdo->query("SELECT cp.public_id product_id,cp.product_type,cp.slug,cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency,
        COALESCE(pp.display_name,u.display_name,u.full_name,'Local merchant') merchant_name
        FROM catalog_products cp
        INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published'
        INNER JOIN users u ON u.id=cp.merchant_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=cp.merchant_user_id
        WHERE cp.status='published'
        ORDER BY cp.published_at DESC,cp.id DESC LIMIT " . max(1,min(100,$limit)));
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['product_id'],
        'type'=>(string)$row['product_type'],
        'slug'=>(string)$row['slug'],
        'title'=>(string)$row['title'],
        'description'=>(string)($row['description'] ?? ''),
        'value'=>mg_personal_workflows_money((int)$row['unit_value_cents']),
        'currency'=>(string)$row['currency'],
        'merchant_name'=>(string)$row['merchant_name'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}
