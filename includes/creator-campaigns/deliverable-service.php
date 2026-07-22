<?php
declare(strict_types=1);

function mg_creator_campaign_deliverable_datetime(mixed $value, string $field): ?string
{
    $text = trim((string) $value);
    if ($text === '') return null;
    try { return (new DateTimeImmutable($text))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    catch (Throwable) { throw new InvalidArgumentException("{$field} must be a valid date and time."); }
}

function mg_creator_campaign_deliverable_normalize(array $input): array
{
    $type = strtolower(trim((string) ($input['deliverable_type'] ?? '')));
    if (!in_array($type, mg_creator_campaign_deliverable_types(), true)) throw new InvalidArgumentException('deliverable_type is invalid.');
    $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
    if (!in_array($status, mg_creator_campaign_deliverable_statuses(), true)) throw new InvalidArgumentException('deliverable status is invalid.');
    $title = mg_creator_campaign_string($input['title'] ?? null, 'title', 180, true);
    $quantity = max(1, min(100, (int) ($input['quantity'] ?? 1)));
    $revisionLimit = max(0, min(25, (int) ($input['revision_limit'] ?? 2)));
    $offset = ($input['due_offset_days'] ?? '') === '' ? null : max(0, min(3650, (int) $input['due_offset_days']));
    return [
        'title'=>$title,
        'description'=>mg_creator_campaign_string($input['description'] ?? null, 'description', 16000),
        'deliverable_type'=>$type,
        'platform'=>mg_creator_campaign_string($input['platform'] ?? null, 'platform', 80),
        'content_format'=>mg_creator_campaign_string($input['content_format'] ?? null, 'content_format', 120),
        'quantity'=>$quantity,
        'instructions'=>mg_creator_campaign_string($input['instructions'] ?? null, 'instructions', 32000),
        'required_talking_points'=>mg_creator_campaign_deliverable_string_list($input['required_talking_points'] ?? [], 'required_talking_points'),
        'required_disclosures'=>mg_creator_campaign_deliverable_string_list($input['required_disclosures'] ?? [], 'required_disclosures'),
        'publication_required'=>mg_creator_campaign_deliverable_bool($input['publication_required'] ?? false),
        'proof_required'=>mg_creator_campaign_deliverable_bool($input['proof_required'] ?? false),
        'merchant_review_required'=>array_key_exists('merchant_review_required',$input) ? mg_creator_campaign_deliverable_bool($input['merchant_review_required']) : 1,
        'revision_limit'=>$revisionLimit,
        'due_offset_days'=>$offset,
        'due_at'=>mg_creator_campaign_deliverable_datetime($input['due_at'] ?? null, 'due_at'),
        'status'=>$status,
        'sort_order'=>max(0, min(100000, (int) ($input['sort_order'] ?? 0))),
    ];
}

function mg_creator_campaign_deliverable_save_merchant(PDO $pdo, array $user, string $campaignPublicId, array $input): array
{
    $context = mg_creator_campaign_deliverable_merchant_context($pdo,$user,'merchant.creator_deliverables.manage');
    $workspaceId=(int)$context['workspace_id']; $actorId=(int)$context['actor_user_id'];
    $data=mg_creator_campaign_deliverable_normalize($input); $publicId=trim((string)($input['deliverable_id']??''));
    mg_creator_campaign_assert_transaction_boundary($pdo); $pdo->beginTransaction();
    try {
        $campaign=mg_creator_campaign_participation_campaign_by_public_id($pdo,$campaignPublicId,$workspaceId,true);
        mg_creator_campaign_participation_require_campaign_open($campaign,'change deliverables');
        if($publicId===''){
            $publicId=mg_creator_campaign_public_id('ccdl');
            $stmt=$pdo->prepare('INSERT INTO creator_campaign_deliverables(public_id,campaign_id,title,description,deliverable_type,platform,content_format,quantity,instructions,required_talking_points_json,required_disclosures_json,publication_required,proof_required,merchant_review_required,revision_limit,due_offset_days,due_at,status,sort_order,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,NOW(),NOW())');
            $stmt->execute([$publicId,(int)$campaign['id'],$data['title'],$data['description'],$data['deliverable_type'],$data['platform'],$data['content_format'],$data['quantity'],$data['instructions'],mg_creator_campaign_json_encode($data['required_talking_points']),mg_creator_campaign_json_encode($data['required_disclosures']),$data['publication_required'],$data['proof_required'],$data['merchant_review_required'],$data['revision_limit'],$data['due_offset_days'],$data['due_at'],$data['status'],$data['sort_order'],$actorId,$actorId]);
        } else {
            $row=mg_creator_campaign_deliverable_by_public_id($pdo,$publicId,$workspaceId,true);
            if((int)$row['campaign_id']!==(int)$campaign['id']) throw new DomainException('The deliverable belongs to another campaign.');
            mg_creator_campaign_participation_require_expected_lock($row,(int)($input['expected_lock_version']??0));
            $stmt=$pdo->prepare('UPDATE creator_campaign_deliverables SET title=?,description=?,deliverable_type=?,platform=?,content_format=?,quantity=?,instructions=?,required_talking_points_json=?,required_disclosures_json=?,publication_required=?,proof_required=?,merchant_review_required=?,revision_limit=?,due_offset_days=?,due_at=?,status=?,sort_order=?,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?');
            $stmt->execute([$data['title'],$data['description'],$data['deliverable_type'],$data['platform'],$data['content_format'],$data['quantity'],$data['instructions'],mg_creator_campaign_json_encode($data['required_talking_points']),mg_creator_campaign_json_encode($data['required_disclosures']),$data['publication_required'],$data['proof_required'],$data['merchant_review_required'],$data['revision_limit'],$data['due_offset_days'],$data['due_at'],$data['status'],$data['sort_order'],$actorId,(int)$row['id']]);
        }
        $pdo->commit(); return mg_creator_campaign_deliverable_by_public_id($pdo,$publicId,$workspaceId);
    } catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_deliverable_retire_merchant(PDO $pdo,array $user,string $deliverablePublicId,array $input):array
{
    $context=mg_creator_campaign_deliverable_merchant_context($pdo,$user,'merchant.creator_deliverables.manage');$workspaceId=(int)$context['workspace_id'];$actorId=(int)$context['actor_user_id'];
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{$row=mg_creator_campaign_deliverable_by_public_id($pdo,$deliverablePublicId,$workspaceId,true);mg_creator_campaign_participation_require_expected_lock($row,(int)($input['expected_lock_version']??0));$pdo->prepare("UPDATE creator_campaign_deliverables SET status='retired',updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$actorId,(int)$row['id']]);$pdo->commit();return mg_creator_campaign_deliverable_by_public_id($pdo,$deliverablePublicId,$workspaceId);}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_assign_deliverables_internal(PDO $pdo,array $participant,int $actorUserId,?int $onlyDeliverableId=null):array
{
    mg_creator_campaign_deliverable_require_active_participant($participant);
    $campaignStmt=$pdo->prepare('SELECT * FROM creator_campaigns WHERE id=? LIMIT 1');$campaignStmt->execute([(int)$participant['campaign_id']]);$campaign=$campaignStmt->fetch(PDO::FETCH_ASSOC);if(!$campaign)throw new RuntimeException('Creator campaign not found.');
    $sql="SELECT * FROM creator_campaign_deliverables WHERE campaign_id=? AND status='active'";$params=[(int)$participant['campaign_id']];if($onlyDeliverableId!==null){$sql.=' AND id=?';$params[]=$onlyDeliverableId;}$sql.=' ORDER BY sort_order,id';$stmt=$pdo->prepare($sql);$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];$created=[];
    foreach($items as $deliverable){$publicId=mg_creator_campaign_public_id('ccpd');$dueAt=mg_creator_campaign_deliverable_due_at($deliverable,$campaign);$insert=$pdo->prepare("INSERT IGNORE INTO creator_campaign_participant_deliverables(public_id,campaign_id,participant_id,campaign_deliverable_id,agreement_version_id,creator_user_id,status,assigned_at,due_at,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'assigned',NOW(),?,1,?,?,NOW(),NOW())");$insert->execute([$publicId,(int)$participant['campaign_id'],(int)$participant['id'],(int)$deliverable['id'],(int)$participant['latest_accepted_version_id'],(int)$participant['creator_user_id'],$dueAt,$actorUserId,$actorUserId]);if($insert->rowCount()===1){$assignment=mg_creator_campaign_participant_deliverable_by_public_id($pdo,$publicId);mg_creator_campaign_deliverable_event($pdo,$assignment,$actorUserId,'deliverable.assigned',null,'assigned',null,['agreement_version_id'=>(int)$participant['latest_accepted_version_id']]);mg_creator_campaign_notify_creator_assignment($pdo,$assignment,$actorUserId);$created[]=$assignment;}}
    return $created;
}

function mg_creator_campaign_deliverable_sync_merchant(PDO $pdo,array $user,string $campaignPublicId):array
{
    $context=mg_creator_campaign_deliverable_merchant_context($pdo,$user,'merchant.creator_deliverables.manage');$workspaceId=(int)$context['workspace_id'];$actorId=(int)$context['actor_user_id'];
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{$campaign=mg_creator_campaign_participation_campaign_by_public_id($pdo,$campaignPublicId,$workspaceId,true);$stmt=$pdo->prepare("SELECT p.*,a.latest_accepted_version_id,p.status participant_status FROM creator_campaign_participants p INNER JOIN creator_campaign_agreements a ON a.participant_id=p.id WHERE p.campaign_id=? AND p.status='active' AND a.status='accepted' AND a.latest_accepted_version_id IS NOT NULL FOR UPDATE");$stmt->execute([(int)$campaign['id']]);$created=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $participant){$created=array_merge($created,mg_creator_campaign_assign_deliverables_internal($pdo,$participant,$actorId));}$pdo->commit();return ['created_count'=>count($created),'items'=>$created];}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_deliverable_sync_creator(PDO $pdo,array $user):array
{
    $context=mg_creator_campaign_deliverable_creator_context($pdo,$user,'creator.campaign_submissions.manage_own');$creatorUserId=(int)$context['creator_user_id'];$actorId=(int)$context['actor_user_id'];
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{$stmt=$pdo->prepare("SELECT p.*,a.latest_accepted_version_id,p.status participant_status FROM creator_campaign_participants p INNER JOIN creator_campaign_agreements a ON a.participant_id=p.id WHERE p.creator_user_id=? AND p.status='active' AND a.status='accepted' AND a.latest_accepted_version_id IS NOT NULL FOR UPDATE");$stmt->execute([$creatorUserId]);$created=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $participant){$created=array_merge($created,mg_creator_campaign_assign_deliverables_internal($pdo,$participant,$actorId));}$pdo->commit();return ['created_count'=>count($created)];}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_deliverable_assign_merchant(PDO $pdo,array $user,string $participantPublicId,string $deliverablePublicId):array
{
    $context=mg_creator_campaign_deliverable_merchant_context($pdo,$user,'merchant.creator_deliverables.manage');$workspaceId=(int)$context['workspace_id'];$actorId=(int)$context['actor_user_id'];
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{$stmt=$pdo->prepare('SELECT p.*,a.latest_accepted_version_id,p.status participant_status,cc.workspace_id FROM creator_campaign_participants p INNER JOIN creator_campaign_agreements a ON a.participant_id=p.id INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE p.public_id=? AND cc.workspace_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$participantPublicId,$workspaceId]);$participant=$stmt->fetch(PDO::FETCH_ASSOC);if(!$participant)throw new RuntimeException('Creator campaign participant not found.');$deliverable=mg_creator_campaign_deliverable_by_public_id($pdo,$deliverablePublicId,$workspaceId,true);if((int)$deliverable['campaign_id']!==(int)$participant['campaign_id'])throw new DomainException('The deliverable and participant belong to different campaigns.');$created=mg_creator_campaign_assign_deliverables_internal($pdo,$participant,$actorId,(int)$deliverable['id']);$pdo->commit();return ['created'=>!empty($created),'item'=>$created[0]??null];}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_assignment_transition_merchant(PDO $pdo,array $user,string $assignmentPublicId,string $toStatus,array $input):array
{
    $context=mg_creator_campaign_deliverable_merchant_context($pdo,$user,'merchant.creator_deliverables.manage');$workspaceId=(int)$context['workspace_id'];$actorId=(int)$context['actor_user_id'];$toStatus=strtolower(trim($toStatus));if(!in_array($toStatus,['waived','cancelled'],true))throw new InvalidArgumentException('Assignment transition is invalid.');$reason=mg_creator_campaign_string($input['reason']??null,'reason',2000,true);
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{$row=mg_creator_campaign_participant_deliverable_by_public_id($pdo,$assignmentPublicId,$workspaceId,null,true);mg_creator_campaign_participation_require_expected_lock($row,(int)($input['expected_lock_version']??0));$from=(string)$row['status'];if(in_array($from,['verified','waived','cancelled'],true))throw new DomainException('This assignment is already final.');$field=$toStatus==='waived'?'waived_at':'cancelled_at';$pdo->prepare("UPDATE creator_campaign_participant_deliverables SET status=?,{$field}=NOW(),status_reason=?,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$toStatus,$reason,$actorId,(int)$row['id']]);mg_creator_campaign_deliverable_event($pdo,$row,$actorId,'deliverable.'.$toStatus,$from,$toStatus,$reason);$pdo->commit();return mg_creator_campaign_participant_deliverable_by_public_id($pdo,$assignmentPublicId,$workspaceId);}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
