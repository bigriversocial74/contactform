<?php
declare(strict_types=1);

function mg_investment_visibility_rank_audited(string $visibility): int
{
    return match ($visibility) {
        'approved_investors', 'public_summary' => 1,
        'selected_investors' => 2,
        'funded_investors' => 3,
        default => 99,
    };
}

function mg_investment_dataroom_save_document_audited(PDO $pdo, array $actor, array $input): array
{
    $round = mg_investment_diligence_round($pdo, mg_investment_text($input['round_id'] ?? '', 36, 36, 'Round identifier'));
    $status = (string)($input['status'] ?? 'draft');
    $classification = (string)($input['classification'] ?? 'standard');
    $requiresLegal = mg_investment_bool($input['requires_legal_review'] ?? false) || $classification === 'highly_sensitive';
    $publicId = trim((string)($input['document_id'] ?? ''));

    $existing = null;
    if ($publicId !== '') {
        $q = $pdo->prepare('SELECT * FROM investment_dataroom_documents WHERE public_id=? AND round_id=? LIMIT 1');
        $q->execute([mg_investment_text($publicId, 36, 36, 'Document identifier'), (int)$round['id']]);
        $existing = $q->fetch(PDO::FETCH_ASSOC);
        if (!$existing) throw new MgInvestmentException('Data-room document not found.', 404);
    }

    if ($status === 'published') {
        mg_investment_require_permission($actor, 'admin.investment.diligence.publish');
        mg_investment_url($input['external_url'] ?? '', true);
        if ($requiresLegal && (!$existing || (string)$existing['status'] !== 'approved')) {
            throw new MgInvestmentException('Legal-review data-room documents must be approved in a separate step before publication.', 409);
        }
    }

    if (!empty($input['folder_id'])) {
        $folderQ = $pdo->prepare('SELECT visibility FROM investment_dataroom_folders WHERE public_id=? AND round_id=? LIMIT 1');
        $folderQ->execute([mg_investment_text($input['folder_id'], 36, 36, 'Folder identifier'), (int)$round['id']]);
        $folderVisibility = (string)$folderQ->fetchColumn();
        if ($folderVisibility === '') throw new MgInvestmentException('Data-room folder not found.', 404);
        if (mg_investment_visibility_rank_audited((string)($input['visibility'] ?? 'approved_investors')) < mg_investment_visibility_rank_audited($folderVisibility)) {
            throw new MgInvestmentException('A document cannot be less restricted than its data-room folder.', 409);
        }
    }

    $result = mg_investment_dataroom_save_document($pdo, $actor, $input);
    if ($status === 'published') {
        $q = $pdo->prepare('SELECT id,current_version_number FROM investment_dataroom_documents WHERE public_id=? AND round_id=? LIMIT 1');
        $q->execute([$publicId !== '' ? $publicId : (string)($result['documents'][0]['public_id'] ?? ''), (int)$round['id']]);
        $document = $q->fetch(PDO::FETCH_ASSOC);
        if (!$document) {
            $q = $pdo->prepare('SELECT id,current_version_number FROM investment_dataroom_documents WHERE round_id=? AND title=? ORDER BY id DESC LIMIT 1');
            $q->execute([(int)$round['id'], mg_investment_text($input['title'] ?? '', 220, 2, 'Document title')]);
            $document = $q->fetch(PDO::FETCH_ASSOC);
        }
        if ($document) {
            $pdo->prepare('UPDATE investment_dataroom_document_versions SET status="superseded" WHERE document_id=? AND version_number<? AND status="published"')->execute([(int)$document['id'], (int)$document['current_version_number']]);
        }
    }
    return $result;
}

function mg_investment_qa_save_audited(PDO $pdo, array $actor, array $input): array
{
    $status = (string)($input['status'] ?? 'draft');
    $requiresLegal = mg_investment_bool($input['requires_legal_review'] ?? false);
    $publicId = trim((string)($input['qa_id'] ?? ''));

    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input, $status, $requiresLegal, $publicId): array {
        $existing = null;
        if ($publicId !== '') {
            $q = $pdo->prepare('SELECT * FROM investment_qa_entries WHERE public_id=? LIMIT 1 FOR UPDATE');
            $q->execute([mg_investment_text($publicId, 36, 36, 'Q&A identifier')]);
            $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new MgInvestmentException('Q&A entry not found.', 404);
            if ($existing['published_at'] !== null) {
                if ($status !== 'archived') throw new MgInvestmentException('Published Q&A is immutable. Archive it and create a corrected entry.', 409);
                $round = !empty($input['round_id']) ? mg_investment_diligence_round($pdo, mg_investment_text($input['round_id'],36,36,'Round identifier')) : null;
                $newPublic = [
                    'round_id'=>$round ? (int)$round['id'] : null,
                    'category'=>mg_investment_text($input['category'] ?? 'general',80,2,'Category'),
                    'question'=>mg_investment_text($input['question'] ?? '',500,4,'Question'),
                    'answer'=>mg_investment_long_text($input['answer'] ?? '',30000,2,'Answer'),
                    'requires_legal_review'=>$requiresLegal ? 1 : 0,
                ];
                foreach ($newPublic as $field=>$value) {
                    if ((string)($existing[$field] ?? '') !== (string)($value ?? '')) throw new MgInvestmentException('Published Q&A content is immutable.',409);
                }
            }
        }
        if ($status === 'published') {
            mg_investment_require_permission($actor,'admin.investment.diligence.publish');
            if ($requiresLegal && (!$existing || (string)$existing['status'] !== 'approved')) {
                throw new MgInvestmentException('Legal-review Q&A must be approved in a separate step before publication.',409);
            }
        }
        return mg_investment_qa_save($pdo,$actor,$input);
    });
}

function mg_investment_meeting_save_audited(PDO $pdo, array $actor, array $input): array
{
    $investorId=(int)($input['investor_user_id']??0);
    $q=$pdo->prepare('SELECT COUNT(*) FROM investor_profiles WHERE user_id=? AND status="active"');
    $q->execute([$investorId]);
    if((int)$q->fetchColumn()<1)throw new MgInvestmentException('Select an active Investor profile.',409);
    $starts=mg_investment_date($input['starts_at']??null);
    $ends=mg_investment_date($input['ends_at']??null);
    if($starts===null)throw new MgInvestmentException('Meeting start time is required.');
    if($ends!==null&&strtotime($ends)<strtotime($starts))throw new MgInvestmentException('Meeting end cannot be before its start.');
    return mg_investment_audit_transaction($pdo,static fn():array=>mg_investment_meeting_save($pdo,$actor,$input));
}

function mg_investment_communication_save_audited(PDO $pdo, array $actor, array $input): array
{
    $status=(string)($input['status']??'draft');
    $audience=(string)($input['audience_type']??'approved_investors');
    $requiresLegal=mg_investment_bool($input['requires_legal_review']??false);
    $publicId=trim((string)($input['communication_id']??''));
    $round=!empty($input['round_id'])?mg_investment_diligence_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier')):null;
    $existing=null;
    if($publicId!==''){
        $q=$pdo->prepare('SELECT * FROM investor_communications WHERE public_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Communication identifier')]);$existing=$q->fetch(PDO::FETCH_ASSOC);if(!$existing)throw new MgInvestmentException('Communication not found.',404);
        if($existing['published_at']!==null){
            if($status!=='archived')throw new MgInvestmentException('Published investor communications are immutable. Archive and create a correction.',409);
            $newPublic=['round_id'=>$round?(int)$round['id']:null,'communication_type'=>(string)($input['communication_type']??'round_update'),'audience_type'=>$audience,'subject'=>mg_investment_text($input['subject']??'',220,2,'Subject'),'body'=>mg_investment_long_text($input['body']??'',30000,2,'Communication body'),'requires_legal_review'=>$requiresLegal?1:0];
            foreach($newPublic as $field=>$value)if((string)($existing[$field]??'')!==(string)($value??''))throw new MgInvestmentException('Published communication content and audience are immutable.',409);
        }
    }
    if($status==='published'){
        mg_investment_require_permission($actor,'admin.investment.diligence.publish');
        if(in_array($audience,['selected_investors','funded_investors'],true)&&!$round)throw new MgInvestmentException('A round is required for the selected communication audience.',409);
        if($requiresLegal&&(!$existing||(string)$existing['status']!=='approved'))throw new MgInvestmentException('Legal-review communications must be approved in a separate step before publication.',409);
        if($audience==='funded_investors'){
            $q=$pdo->prepare('SELECT COUNT(DISTINCT investor_user_id) FROM investor_closing_records WHERE round_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined")');$q->execute([(int)$round['id']]);if((int)$q->fetchColumn()<1)throw new MgInvestmentException('No verified funded investors are available for this communication.',409);
        }
    }
    $result=mg_investment_communication_save($pdo,$actor,$input);
    if($status==='published'&&$audience==='funded_investors'&&$round){
        $cleanup=$pdo->prepare('UPDATE investor_communication_recipients cr INNER JOIN investor_communications c ON c.id=cr.communication_id LEFT JOIN investor_closing_records cl ON cl.round_id=c.round_id AND cl.investor_user_id=cr.investor_user_id AND cl.verified_funded_cents>0 AND cl.status NOT IN ("withdrawn","declined") SET cr.status="revoked" WHERE c.round_id=? AND c.audience_type="funded_investors" AND c.status="published" AND cl.id IS NULL');$cleanup->execute([(int)$round['id']]);
    }
    return $result;
}

function mg_investment_diligence_admin_save_request_audited(PDO $pdo,array $actor,array $input):array
{
    mg_investment_pipeline_admin_user_audited($pdo,$input['assigned_user_id']??0);
    $publicId=mg_investment_text($input['request_id']??'',36,36,'Request identifier');
    $q=$pdo->prepare('SELECT approved_response FROM investor_diligence_requests WHERE public_id=? LIMIT 1');$q->execute([$publicId]);$existing=$q->fetch(PDO::FETCH_ASSOC);if(!$existing)throw new MgInvestmentException('Diligence request not found.',404);
    $status=(string)($input['status']??'acknowledged');$responseStatus=(string)($input['response_status']??'draft');$response=mg_investment_long_text($input['response_text']??'',30000);
    if($responseStatus==='published')mg_investment_long_text($response,30000,2,'Published response');
    if(in_array($status,['answered','closed'],true)&&$responseStatus!=='published'&&empty($existing['approved_response']))throw new MgInvestmentException('Answered or closed diligence requests require a published response.',409);
    return mg_investment_diligence_admin_save_request($pdo,$actor,$input);
}

function mg_investment_portal_data_v5_final2(PDO $pdo,array $user):array
{
    $data=mg_investment_portal_data_v5_final($pdo,$user);$userId=(int)$user['id'];$safeRounds=[];
    foreach($data['rounds'] as $portalRound){
        $q=$pdo->prepare('SELECT id,workspace_id,visibility FROM investment_rounds WHERE public_id=? LIMIT 1');$q->execute([(string)$portalRound['id']]);$round=$q->fetch(PDO::FETCH_ASSOC);if(!$round)continue;$roundId=(int)$round['id'];
        $fundedQ=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined")');$fundedQ->execute([$roundId,$userId]);$verified=(int)$fundedQ->fetchColumn()>0;
        if((string)$round['visibility']==='funded_investors'&&!$verified)continue;
        $selectedQ=$pdo->prepare('SELECT COUNT(*) FROM investment_round_access WHERE round_id=? AND investor_user_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');$selectedQ->execute([$roundId,$userId]);$selected=(int)$selectedQ->fetchColumn()>0;
        $allowed=static fn(string $visibility):bool=>$visibility==='approved_investors'||$visibility==='public_summary'||($visibility==='selected_investors'&&$selected)||($visibility==='funded_investors'&&$verified);
        if(isset($portalRound['documents'])&&is_array($portalRound['documents']))$portalRound['documents']=array_values(array_filter($portalRound['documents'],static fn(array $doc):bool=>$allowed((string)($doc['visibility']??''))));
        if(isset($portalRound['diligence'])&&is_array($portalRound['diligence'])){
            $portalRound['diligence']['relationship']['funded']=$verified;
            $folders=[];foreach((array)($portalRound['diligence']['folders']??[]) as $folder)if($allowed((string)($folder['visibility']??'')))$folders[(string)$folder['public_id']]=$folder;
            $portalRound['diligence']['folders']=array_values($folders);
            $portalRound['diligence']['documents']=array_values(array_filter((array)($portalRound['diligence']['documents']??[]),static function(array $doc)use($allowed,$folders):bool{$folderId=(string)($doc['folder_public_id']??'');return $allowed((string)($doc['visibility']??''))&&($folderId===''||isset($folders[$folderId]));}));
            $commQ=$pdo->prepare('SELECT c.public_id,c.communication_type,c.subject,c.body,c.published_at,cr.first_viewed_at,cr.last_viewed_at,cr.view_count FROM investor_communications c INNER JOIN investor_communication_recipients cr ON cr.communication_id=c.id AND cr.investor_user_id=? WHERE c.round_id=? AND c.status="published" AND cr.status IN ("published","viewed") AND (c.audience_type<>"funded_investors" OR EXISTS(SELECT 1 FROM investor_closing_records cl WHERE cl.round_id=c.round_id AND cl.investor_user_id=? AND cl.verified_funded_cents>0 AND cl.status NOT IN ("withdrawn","declined"))) ORDER BY c.published_at DESC');$commQ->execute([$userId,$roundId,$userId]);$portalRound['diligence']['communications']=$commQ->fetchAll(PDO::FETCH_ASSOC);
        }
        $safeRounds[]=$portalRound;
    }
    $data['rounds']=$safeRounds;return $data;
}

function mg_investment_portal_accessible_round_final(PDO $pdo,array $user,string $roundPublicId):array
{
    if(!in_array('investor',is_array($user['roles']??null)?$user['roles']:[],true))throw new MgInvestmentException('Investor access is not active.',403);$userId=(int)$user['id'];
    $profileQ=$pdo->prepare('SELECT COUNT(*) FROM investor_profiles WHERE user_id=? AND status="active"');$profileQ->execute([$userId]);if((int)$profileQ->fetchColumn()<1)throw new MgInvestmentException('Investor profile is not active.',403);
    $q=$pdo->prepare('SELECT r.* FROM investment_rounds r INNER JOIN investment_round_publication p ON p.round_id=r.id WHERE r.public_id=? AND p.publication_status IN ("private_preview","published") AND r.status IN ("private_preview","open","minimum_reached","closing","closed") AND (r.visibility="approved_investors" OR (r.visibility="selected_investors" AND EXISTS(SELECT 1 FROM investment_round_access a WHERE a.round_id=r.id AND a.investor_user_id=? AND a.status="granted" AND (a.expires_at IS NULL OR a.expires_at>NOW()))) OR (r.visibility="funded_investors" AND EXISTS(SELECT 1 FROM investor_closing_records c WHERE c.round_id=r.id AND c.investor_user_id=? AND c.verified_funded_cents>0 AND c.status NOT IN ("withdrawn","declined")))) LIMIT 1');$q->execute([$roundPublicId,$userId,$userId]);$round=$q->fetch(PDO::FETCH_ASSOC);if(!$round)throw new MgInvestmentException('Investment round is not available.',404);return $round;
}

function mg_investment_portal_event_v2_final(PDO $pdo,array $user,array $input):array
{
    $event=(string)($input['event_type']??'');if(!in_array($event,['document_open','metric_view','round_view'],true))throw new MgInvestmentException('Invalid standard portal event.');$round=mg_investment_portal_accessible_round_final($pdo,$user,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$subjectId=mg_investment_text($input['subject_id']??'',36,1,'Subject identifier');$publication=mg_investment_publication_get($pdo,(int)$round['id']);$sections=(array)($publication['sections']??[]);$userId=(int)$user['id'];
    if($event==='round_view'){if(!hash_equals((string)$round['public_id'],$subjectId))throw new MgInvestmentException('Round-view subject does not match the accessible round.',409);}
    elseif($event==='metric_view'){if(empty($sections['evidence_metrics']))throw new MgInvestmentException('Evidence metrics are not published for this round.',404);$q=$pdo->prepare('SELECT COUNT(*) FROM investment_metrics WHERE public_id=? AND workspace_id=? AND investor_visible=1');$q->execute([$subjectId,(int)$round['workspace_id']]);if((int)$q->fetchColumn()<1)throw new MgInvestmentException('Evidence metric is not available.',404);}
    else{$allowed=['approved_investors','public_summary'];$s=$pdo->prepare('SELECT COUNT(*) FROM investment_round_access WHERE round_id=? AND investor_user_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');$s->execute([(int)$round['id'],$userId]);if((int)$s->fetchColumn()>0)$allowed[]='selected_investors';$f=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined")');$f->execute([(int)$round['id'],$userId]);if((int)$f->fetchColumn()>0)$allowed[]='funded_investors';$marks=implode(',',array_fill(0,count($allowed),'?'));$q=$pdo->prepare('SELECT COUNT(*) FROM investment_documents WHERE public_id=? AND workspace_id=? AND status="published" AND visibility IN ('.$marks.')');$q->execute([$subjectId,(int)$round['workspace_id'],...$allowed]);if((int)$q->fetchColumn()<1)throw new MgInvestmentException('Investment document is not available.',404);}
    mg_investment_portal_log($pdo,$userId,(int)$round['id'],$event,$subjectId,['title'=>mg_investment_text($input['title']??'',220)]);return ['recorded'=>true];
}

function mg_investment_portal_event_final2(PDO $pdo,array $user,array $input):array
{
    $event=(string)($input['event_type']??'');if(in_array($event,['document_open','metric_view','round_view'],true))return mg_investment_portal_event_v2_final($pdo,$user,$input);
    if(in_array($event,['communication_view','qa_view'],true)){
        $round=mg_investment_portal_accessible_round_final($pdo,$user,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$subjectId=mg_investment_text($input['subject_id']??'',36,36,'Subject identifier');$userId=(int)$user['id'];
        if($event==='qa_view'){$q=$pdo->prepare('SELECT COUNT(*) FROM investment_qa_entries WHERE public_id=? AND status="published" AND (round_id=? OR round_id IS NULL)');$q->execute([$subjectId,(int)$round['id']]);if((int)$q->fetchColumn()<1)throw new MgInvestmentException('Q&A entry is not available.',404);}
        else{$q=$pdo->prepare('SELECT c.id FROM investor_communications c INNER JOIN investor_communication_recipients cr ON cr.communication_id=c.id WHERE c.public_id=? AND c.round_id=? AND cr.investor_user_id=? AND c.status="published" AND cr.status IN ("published","viewed") AND (c.audience_type<>"funded_investors" OR EXISTS(SELECT 1 FROM investor_closing_records cl WHERE cl.round_id=c.round_id AND cl.investor_user_id=? AND cl.verified_funded_cents>0 AND cl.status NOT IN ("withdrawn","declined"))) LIMIT 1');$q->execute([$subjectId,(int)$round['id'],$userId,$userId]);$id=(int)$q->fetchColumn();if($id<1)throw new MgInvestmentException('Communication is not available.',404);$pdo->prepare('UPDATE investor_communication_recipients SET status="viewed",first_viewed_at=COALESCE(first_viewed_at,NOW()),last_viewed_at=NOW(),view_count=view_count+1 WHERE communication_id=? AND investor_user_id=?')->execute([$id,$userId]);}
        mg_investment_portal_log_v3($pdo,$userId,(int)$round['id'],$event,$subjectId,['title'=>mg_investment_text($input['title']??'',220)]);return ['recorded'=>true];
    }
    return mg_investment_portal_event_v5($pdo,$user,$input);
}

function mg_investment_portal_submit_diligence_v5_final2(PDO $pdo,array $user,array $input):array
{
    $roundId=mg_investment_text($input['round_id']??'',36,36,'Round identifier');mg_investment_portal_accessible_round_final($pdo,$user,$roundId);$q=$pdo->prepare('SELECT COUNT(*) FROM investor_diligence_requests WHERE investor_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');$q->execute([(int)$user['id']]);if((int)$q->fetchColumn()>=10)throw new MgInvestmentException('Diligence request limit reached. Try again later.',429);
    mg_investment_audit_transaction($pdo,static fn():array=>mg_investment_portal_submit_diligence($pdo,$user,$input));return mg_investment_portal_data_v5_final2($pdo,$user);
}

function mg_investment_portal_submit_interest_v5_final2(PDO $pdo,array $user,array $input):array
{
    $roundId=mg_investment_text($input['round_id']??'',36,36,'Round identifier');mg_investment_portal_accessible_round_final($pdo,$user,$roundId);$q=$pdo->prepare('SELECT COUNT(*) FROM investor_interest_submissions WHERE investor_user_id=? AND round_id=(SELECT id FROM investment_rounds WHERE public_id=? LIMIT 1) AND created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)');$q->execute([(int)$user['id'],$roundId]);if((int)$q->fetchColumn()>=3)throw new MgInvestmentException('Interest submission limit reached for this round. Update the discussion through the investor team.',429);
    mg_investment_audit_transaction($pdo,static fn():array=>mg_investment_portal_submit_interest($pdo,$user,$input));return mg_investment_portal_data_v5_final2($pdo,$user);
}

function mg_investment_portal_acknowledge_notice_v5_final2(PDO $pdo,array $user,array $input):array
{
    mg_investment_portal_acknowledge_notice_v5($pdo,$user,$input);return mg_investment_portal_data_v5_final2($pdo,$user);
}
