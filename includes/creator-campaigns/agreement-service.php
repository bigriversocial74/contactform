<?php
declare(strict_types=1);

function mg_creator_campaign_agreement_request_context(): array
{
    return [
        'ip_hash' => isset($_SERVER['REMOTE_ADDR']) ? hash('sha256', (string) $_SERVER['REMOTE_ADDR']) : null,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
    ];
}

function mg_creator_campaign_agreement_snapshot(PDO $pdo, array $campaign, array $participant, array $terms): array
{
    $products = mg_creator_campaign_participation_products($pdo, (int) $campaign['id']);
    $questions = mg_creator_campaign_participation_questions($pdo, (int) $campaign['id']);
    $rulesStmt = $pdo->prepare('SELECT public_id,rule_type,operator_key,value_json,is_required,sort_order FROM creator_campaign_eligibility_rules WHERE campaign_id=? ORDER BY sort_order,id');
    $rulesStmt->execute([(int) $campaign['id']]);
    $rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rules as &$rule) {
        $rule['value'] = mg_creator_campaign_participation_decode_json($rule['value_json'] ?? null);
        unset($rule['value_json']);
    }
    unset($rule);
    return [
        'schema_version' => 1,
        'captured_at' => gmdate('c'),
        'campaign' => [
            'public_id' => $campaign['public_id'] ?? null,
            'title' => $campaign['title'] ?? null,
            'description' => $campaign['description'] ?? null,
            'objective' => $campaign['objective'] ?? null,
            'category' => $campaign['category'] ?? null,
            'focus' => $campaign['campaign_focus'] ?? null,
            'timezone' => $campaign['timezone'] ?? 'UTC',
            'starts_at' => $campaign['starts_at'] ?? null,
            'ends_at' => $campaign['ends_at'] ?? null,
            'creator_product_access' => $campaign['creator_product_access'] ?? 'none',
            'creator_landing_url' => $campaign['creator_landing_url'] ?? null,
            'geographic_scope' => mg_creator_campaign_participation_decode_json($campaign['geographic_scope_json'] ?? null),
        ],
        'participant' => [
            'public_id' => $participant['public_id'] ?? null,
            'creator_user_id' => (int) ($participant['creator_user_id'] ?? 0),
            'creator_profile_id' => (int) ($participant['creator_profile_id'] ?? 0),
            'source_type' => $participant['source_type'] ?? null,
        ],
        'products' => $products,
        'eligibility_rules' => $rules,
        'application_questions' => $questions,
        'terms' => [
            'summary' => $terms['summary'] ?? null,
            'deliverables' => $terms['deliverables'] ?? null,
            'compensation' => $terms['compensation'] ?? null,
            'content_rights' => $terms['content_rights'] ?? null,
            'disclosures' => $terms['disclosures'] ?? null,
            'cancellation' => $terms['cancellation'] ?? null,
            'reversal' => $terms['reversal'] ?? null,
            'creator_specific' => $terms['creator_specific'] ?? null,
        ],
    ];
}

function mg_creator_campaign_agreement_default_terms(array $campaign): array
{
    $title = trim((string) ($campaign['title'] ?? 'Creator Campaign'));
    $description = trim((string) ($campaign['description'] ?? ''));
    return [
        'summary' => $title . ' creator participation agreement.',
        'terms_text' => $description !== '' ? $description : 'The creator agrees to participate according to the immutable campaign terms captured in this version.',
        'deliverables' => 'Deliverable assignments and content review are managed in Phase 4 and attach to a new version when material terms change.',
        'compensation' => 'Compensation is limited to the campaign configuration captured in this version.',
        'content_rights' => 'Content rights are limited to the terms stated in this version and any later accepted immutable version.',
        'disclosures' => 'The creator must follow applicable sponsorship and advertising disclosure requirements.',
        'cancellation' => 'Either party may cancel according to the campaign cancellation terms captured in this version.',
        'reversal' => 'Any reversal must be documented and linked to the accepted agreement version.',
        'creator_specific' => null,
    ];
}

function mg_creator_campaign_agreement_normalize_terms(array $input, array $campaign): array
{
    $defaults = mg_creator_campaign_agreement_default_terms($campaign);
    $fields = ['summary'=>1000,'terms_text'=>32000,'deliverables'=>16000,'compensation'=>16000,'content_rights'=>16000,'disclosures'=>16000,'cancellation'=>16000,'reversal'=>16000,'creator_specific'=>16000];
    $terms = [];
    foreach ($fields as $field => $limit) {
        $value = array_key_exists($field, $input) ? $input[$field] : ($defaults[$field] ?? null);
        $terms[$field] = mg_creator_campaign_string($value, $field, $limit, $field === 'terms_text');
    }
    return $terms;
}

function mg_creator_campaign_agreement_hydrate(PDO $pdo, int $agreementId): array
{
    $stmt = $pdo->prepare('SELECT a.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.objective,cc.category,p.public_id participant_public_id,p.status participant_status,p.source_type,cp.public_id creator_profile_public_id,COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name,v.public_id current_version_public_id,v.version_number,v.status version_status,v.summary,v.terms_text,v.snapshot_json,v.change_summary,v.content_hash,v.requires_reacceptance,v.offered_at version_offered_at,v.accepted_at version_accepted_at,v.declined_at version_declined_at FROM creator_campaign_agreements a INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id INNER JOIN creator_campaign_participants p ON p.id=a.participant_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id INNER JOIN users u ON u.id=p.creator_user_id LEFT JOIN creator_campaign_agreement_versions v ON v.id=a.current_version_id WHERE a.id=? LIMIT 1');
    $stmt->execute([$agreementId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign agreement not found.');
    $row['snapshot'] = mg_creator_campaign_participation_decode_json($row['snapshot_json'] ?? null);
    unset($row['snapshot_json']);
    $versions = $pdo->prepare('SELECT public_id,version_number,status,summary,change_summary,content_hash,requires_reacceptance,offered_at,accepted_at,declined_at,created_at FROM creator_campaign_agreement_versions WHERE agreement_id=? ORDER BY version_number DESC');
    $versions->execute([$agreementId]);
    $row['versions'] = $versions->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $row;
}

function mg_creator_campaign_agreement_find_for_participant(PDO $pdo, int $participantId, bool $forUpdate = false): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM creator_campaign_agreements WHERE participant_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$participantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_creator_campaign_agreement_offer_internal(PDO $pdo, array $campaign, array $participant, int $actorUserId, array $input = []): array
{
    $terms = mg_creator_campaign_agreement_normalize_terms($input, $campaign);
    $requiresReacceptance = !array_key_exists('requires_reacceptance', $input) || !empty($input['requires_reacceptance']);
    $changeSummary = mg_creator_campaign_string($input['change_summary'] ?? null, 'change_summary', 2000);
    $snapshot = mg_creator_campaign_agreement_snapshot($pdo, $campaign, $participant, $terms);
    $hashSnapshot = $snapshot;
    unset($hashSnapshot['captured_at']);
    $contentHash = hash('sha256', mg_creator_campaign_json_encode(['snapshot'=>$hashSnapshot,'terms_text'=>$terms['terms_text']]));
    $agreement = mg_creator_campaign_agreement_find_for_participant($pdo, (int) $participant['id'], true);
    if (!$agreement) {
        $insert = $pdo->prepare("INSERT INTO creator_campaign_agreements(public_id,campaign_id,participant_id,creator_user_id,status,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,'draft',1,?,?,NOW(),NOW())");
        $insert->execute([mg_creator_campaign_public_id('ccag'),(int)$campaign['id'],(int)$participant['id'],(int)$participant['creator_user_id'],$actorUserId,$actorUserId]);
        $agreementId = (int) $pdo->lastInsertId();
        $agreement = mg_creator_campaign_agreement_find_for_participant($pdo, (int) $participant['id'], true);
    } else $agreementId = (int) $agreement['id'];
    $same = $pdo->prepare("SELECT id FROM creator_campaign_agreement_versions WHERE agreement_id=? AND content_hash=? AND status IN ('offered','accepted') ORDER BY version_number DESC LIMIT 1");
    $same->execute([$agreementId,$contentHash]);
    if ((int)($same->fetchColumn() ?: 0) > 0) return mg_creator_campaign_agreement_hydrate($pdo,$agreementId);
    $next=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM creator_campaign_agreement_versions WHERE agreement_id=?');
    $next->execute([$agreementId]);
    $versionNumber=(int)$next->fetchColumn();
    $pdo->prepare("UPDATE creator_campaign_agreement_versions SET status='superseded' WHERE agreement_id=? AND status IN ('draft','offered')")->execute([$agreementId]);
    $versionInsert=$pdo->prepare("INSERT INTO creator_campaign_agreement_versions(public_id,agreement_id,campaign_id,participant_id,version_number,status,summary,terms_text,snapshot_json,change_summary,content_hash,requires_reacceptance,offered_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,'offered',?,?,?,?,?,?,NOW(),?,NOW())");
    $versionInsert->execute([mg_creator_campaign_public_id('ccav'),$agreementId,(int)$campaign['id'],(int)$participant['id'],$versionNumber,$terms['summary'],$terms['terms_text'],mg_creator_campaign_json_encode($snapshot),$changeSummary,$contentHash,$requiresReacceptance?1:0,$actorUserId]);
    $versionId=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE creator_campaign_agreements SET status='offered',current_version_id=?,offered_at=NOW(),declined_at=NULL,cancelled_at=NULL,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$versionId,$actorUserId,$agreementId]);
    if ($requiresReacceptance && (string)$participant['status']==='active') {
        $pdo->prepare("UPDATE creator_campaign_participants SET status='agreement_pending',agreement_pending_at=NOW(),status_reason='Updated agreement requires acceptance',updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$actorUserId,(int)$participant['id']]);
    }
    mg_creator_campaign_participation_event($pdo,['campaign_id'=>(int)$campaign['id'],'participant_id'=>(int)$participant['id'],'actor_user_id'=>$actorUserId,'event_type'=>'agreement.offered','from_status'=>$agreement['status']??null,'to_status'=>'offered','reason'=>$changeSummary,'context'=>['agreement_id'=>$agreement['public_id']??null,'version_number'=>$versionNumber,'content_hash'=>$contentHash,'requires_reacceptance'=>$requiresReacceptance]]);
    return mg_creator_campaign_agreement_hydrate($pdo,$agreementId);
}

function mg_creator_campaign_agreement_ensure_offered(PDO $pdo, array $campaign, array $participant, int $actorUserId): array
{
    return mg_creator_campaign_agreement_offer_internal($pdo,$campaign,$participant,$actorUserId,['change_summary'=>'Initial participant agreement version.','requires_reacceptance'=>true]);
}

function mg_creator_campaign_agreement_offer_merchant(PDO $pdo, array $user, string $participantPublicId, array $input): array
{
    $context=mg_creator_campaign_participation_merchant_context($pdo,$user,'merchant.creator_agreements.manage');
    $workspaceId=(int)$context['workspace_id'];$actorId=(int)$context['actor_user_id'];
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT p.*,cc.public_id campaign_public_id,cc.workspace_id FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id WHERE p.public_id=? AND cc.workspace_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$participantPublicId,$workspaceId]);$participant=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$participant) throw new RuntimeException('Creator campaign participant not found.');
        if(!in_array((string)$participant['status'],['agreement_pending','active','suspended'],true)) throw new DomainException('An agreement may only be offered to an approved participant.');
        $campaign=mg_creator_campaign_participation_campaign_by_public_id($pdo,(string)$participant['campaign_public_id'],$workspaceId,true);
        $agreement=mg_creator_campaign_agreement_offer_internal($pdo,$campaign,$participant,$actorId,$input);$pdo->commit();return $agreement;
    } catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_agreement_list_merchant(PDO $pdo,array $user,array $filters=[]):array
{
    $context=mg_creator_campaign_participation_merchant_context($pdo,$user,'merchant.creator_agreements.view');$workspaceId=(int)$context['workspace_id'];
    $status=strtolower(trim((string)($filters['status']??'')));$campaignPublicId=trim((string)($filters['campaign_id']??''));$where=['cc.workspace_id=?'];$params=[$workspaceId];
    if($status!==''){if(!in_array($status,mg_creator_campaign_agreement_statuses(),true))throw new InvalidArgumentException('Agreement status filter is invalid.');$where[]='a.status=?';$params[]=$status;}
    if($campaignPublicId!==''){$where[]='cc.public_id=?';$params[]=$campaignPublicId;}
    $stmt=$pdo->prepare('SELECT a.public_id,a.status,a.offered_at,a.accepted_at,a.declined_at,a.lock_version,a.updated_at,cc.public_id campaign_public_id,cc.title campaign_title,p.public_id participant_public_id,p.status participant_status,cp.public_id creator_profile_public_id,COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name,v.public_id version_public_id,v.version_number,v.status version_status,v.summary,v.change_summary,v.content_hash FROM creator_campaign_agreements a INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id INNER JOIN creator_campaign_participants p ON p.id=a.participant_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id INNER JOIN users u ON u.id=p.creator_user_id LEFT JOIN creator_campaign_agreement_versions v ON v.id=a.current_version_id WHERE '.implode(' AND ',$where).' ORDER BY a.updated_at DESC,a.id DESC LIMIT 250');
    $stmt->execute($params);return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}

function mg_creator_campaign_agreement_detail_merchant(PDO $pdo,array $user,string $agreementPublicId):array
{
    $context=mg_creator_campaign_participation_merchant_context($pdo,$user,'merchant.creator_agreements.view');$stmt=$pdo->prepare('SELECT a.id FROM creator_campaign_agreements a INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE a.public_id=? AND cc.workspace_id=? LIMIT 1');$stmt->execute([$agreementPublicId,(int)$context['workspace_id']]);$id=(int)($stmt->fetchColumn()?:0);if($id<1)throw new RuntimeException('Creator campaign agreement not found.');return mg_creator_campaign_agreement_hydrate($pdo,$id);
}

function mg_creator_campaign_agreement_list_creator(PDO $pdo,array $user):array
{
    $context=mg_creator_campaign_creator_context($pdo,$user,'creator.campaign_agreements.view_own');
    $stmt=$pdo->prepare('SELECT a.public_id,a.status,a.offered_at,a.accepted_at,a.declined_at,a.lock_version,a.updated_at,cc.public_id campaign_public_id,cc.title campaign_title,cc.objective,cc.category,mw.display_name merchant_name,p.public_id participant_public_id,p.status participant_status,v.public_id version_public_id,v.version_number,v.status version_status,v.summary,v.terms_text,v.snapshot_json,v.change_summary,v.content_hash,v.requires_reacceptance,v.offered_at version_offered_at,v.accepted_at version_accepted_at,v.declined_at version_declined_at FROM creator_campaign_agreements a INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id INNER JOIN creator_campaign_participants p ON p.id=a.participant_id LEFT JOIN creator_campaign_agreement_versions v ON v.id=a.current_version_id WHERE a.creator_user_id=? ORDER BY a.updated_at DESC,a.id DESC');
    $stmt->execute([(int)$context['creator_user_id']]);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];foreach($items as &$item){$item['snapshot']=mg_creator_campaign_participation_decode_json($item['snapshot_json']??null);unset($item['snapshot_json']);}unset($item);return ['items'=>$items];
}

function mg_creator_campaign_agreement_detail_creator(PDO $pdo,array $user,string $agreementPublicId):array
{
    $context=mg_creator_campaign_creator_context($pdo,$user,'creator.campaign_agreements.view_own');$stmt=$pdo->prepare('SELECT id FROM creator_campaign_agreements WHERE public_id=? AND creator_user_id=? LIMIT 1');$stmt->execute([$agreementPublicId,(int)$context['creator_user_id']]);$id=(int)($stmt->fetchColumn()?:0);if($id<1)throw new RuntimeException('Creator campaign agreement not found.');return mg_creator_campaign_agreement_hydrate($pdo,$id);
}

function mg_creator_campaign_agreement_respond_creator(PDO $pdo,array $user,string $agreementPublicId,string $decision,array $input):array
{
    $context=mg_creator_campaign_creator_context($pdo,$user,'creator.campaign_agreements.respond_own');$creatorUserId=(int)$context['creator_user_id'];$actorId=(int)$context['actor_user_id'];$decision=strtolower(trim($decision));if(!in_array($decision,['accepted','declined'],true))throw new InvalidArgumentException('Agreement response is invalid.');$expectedLock=(int)($input['expected_lock_version']??0);$note=mg_creator_campaign_string($input['response_note']??null,'response_note',2000);
    mg_creator_campaign_assert_transaction_boundary($pdo);$pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT a.*,p.status participant_status,p.lock_version participant_lock_version,cc.public_id campaign_public_id FROM creator_campaign_agreements a INNER JOIN creator_campaign_participants p ON p.id=a.participant_id INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE a.public_id=? AND a.creator_user_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$agreementPublicId,$creatorUserId]);$agreement=$stmt->fetch(PDO::FETCH_ASSOC);if(!$agreement)throw new RuntimeException('Creator campaign agreement not found.');mg_creator_campaign_participation_require_expected_lock($agreement,$expectedLock);if((string)$agreement['status']!=='offered')throw new DomainException('This agreement is not awaiting a response.');
        $versionStmt=$pdo->prepare('SELECT * FROM creator_campaign_agreement_versions WHERE id=? LIMIT 1 FOR UPDATE');$versionStmt->execute([(int)$agreement['current_version_id']]);$version=$versionStmt->fetch(PDO::FETCH_ASSOC);if(!$version||(string)$version['status']!=='offered')throw new DomainException('The current agreement version is not awaiting a response.');
        $versionUpdate=$pdo->prepare("UPDATE creator_campaign_agreement_versions SET status=?,accepted_at=IF(?='accepted',NOW(),accepted_at),declined_at=IF(?='declined',NOW(),declined_at) WHERE id=? AND status='offered'");$versionUpdate->execute([$decision,$decision,$decision,(int)$version['id']]);if($versionUpdate->rowCount()!==1)throw new DomainException('Agreement response lost its version lock.');
        $pdo->prepare('INSERT INTO creator_campaign_agreement_acceptances(public_id,agreement_id,agreement_version_id,participant_id,creator_user_id,decision,content_hash,response_note,request_context_json,decided_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([mg_creator_campaign_public_id('ccaa'),(int)$agreement['id'],(int)$version['id'],(int)$agreement['participant_id'],$creatorUserId,$decision,(string)$version['content_hash'],$note,mg_creator_campaign_json_encode(mg_creator_campaign_agreement_request_context())]);
        if($decision==='accepted'){
            $pdo->prepare("UPDATE creator_campaign_agreements SET status='accepted',latest_accepted_version_id=?,accepted_at=NOW(),declined_at=NULL,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([(int)$version['id'],$actorId,(int)$agreement['id']]);$fromParticipant=(string)$agreement['participant_status'];if($fromParticipant!=='active'){mg_creator_campaign_assert_transition('participant',$fromParticipant,'active');$pdo->prepare("UPDATE creator_campaign_participants SET status='active',activated_at=NOW(),status_reason='Agreement accepted',updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$actorId,(int)$agreement['participant_id']]);}
        }else{
            $pdo->prepare("UPDATE creator_campaign_agreements SET status='declined',declined_at=NOW(),updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$actorId,(int)$agreement['id']]);$fromParticipant=(string)$agreement['participant_status'];if($fromParticipant==='agreement_pending'){$pdo->prepare("UPDATE creator_campaign_participants SET status='declined',status_reason=?,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW() WHERE id=?")->execute([$note,$actorId,(int)$agreement['participant_id']]);}
        }
        mg_creator_campaign_participation_event($pdo,['campaign_id'=>(int)$agreement['campaign_id'],'participant_id'=>(int)$agreement['participant_id'],'actor_user_id'=>$actorId,'event_type'=>'agreement.'.$decision,'from_status'=>'offered','to_status'=>$decision,'reason'=>$note,'idempotency_key'=>$input['idempotency_key']??null,'context'=>['agreement_id'=>$agreementPublicId,'version_number'=>(int)$version['version_number'],'content_hash'=>(string)$version['content_hash']]]);$pdo->commit();return mg_creator_campaign_agreement_hydrate($pdo,(int)$agreement['id']);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_creator_campaign_active_workspace_creator(PDO $pdo,array $user):array
{
    $context=mg_creator_campaign_creator_context($pdo,$user,'creator.campaign_participants.view_own');$stmt=$pdo->prepare("SELECT p.public_id participant_public_id,p.status participant_status,p.activated_at,p.completed_at,cc.public_id campaign_public_id,cc.title campaign_title,cc.description,cc.objective,cc.category,cc.starts_at,cc.ends_at,cc.timezone,cc.creator_landing_url,mw.display_name merchant_name,a.public_id agreement_public_id,a.status agreement_status,v.public_id agreement_version_public_id,v.version_number,v.summary,v.content_hash FROM creator_campaign_participants p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id LEFT JOIN creator_campaign_agreements a ON a.participant_id=p.id LEFT JOIN creator_campaign_agreement_versions v ON v.id=a.current_version_id WHERE p.creator_user_id=? AND p.status IN ('active','completed','suspended','agreement_pending') ORDER BY FIELD(p.status,'active','agreement_pending','suspended','completed'),COALESCE(cc.starts_at,cc.created_at),cc.id");$stmt->execute([(int)$context['creator_user_id']]);return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}
