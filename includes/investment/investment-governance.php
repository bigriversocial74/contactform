<?php
declare(strict_types=1);

function mg_investment_governance_datetime(mixed $value,bool $required=false,string $label='Date and time'): ?string
{
    $text=trim((string)$value);
    if($text===''){if($required)throw new MgInvestmentException($label.' is required.');return null;}
    $time=strtotime($text);
    if($time===false)throw new MgInvestmentException($label.' is invalid.');
    return date('Y-m-d H:i:s',$time);
}

function mg_investment_governance_round(PDO $pdo,mixed $publicId,bool $required=true): ?array
{
    $id=trim((string)$publicId);
    if($id===''){if($required)throw new MgInvestmentException('Round identifier is required.');return null;}
    return mg_investment_closing_round($pdo,mg_investment_text($id,36,36,'Round identifier'));
}

function mg_investment_governance_participant(PDO $pdo,string $publicId,bool $forUpdate=false): array
{
    $sql='SELECT * FROM investment_governance_participants WHERE public_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $q=$pdo->prepare($sql);$q->execute([mg_investment_text($publicId,36,36,'Participant identifier')]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgInvestmentException('Governance participant not found.',404);
    return $row;
}

function mg_investment_governance_meeting(PDO $pdo,string $publicId,bool $forUpdate=false): array
{
    $sql='SELECT * FROM investment_board_meetings WHERE public_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $q=$pdo->prepare($sql);$q->execute([mg_investment_text($publicId,36,36,'Meeting identifier')]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgInvestmentException('Board meeting not found.',404);
    return $row;
}

function mg_investment_governance_consent(PDO $pdo,string $publicId,bool $forUpdate=false): array
{
    $sql='SELECT * FROM investment_written_consents WHERE public_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $q=$pdo->prepare($sql);$q->execute([mg_investment_text($publicId,36,36,'Consent identifier')]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgInvestmentException('Written consent not found.',404);
    return $row;
}

function mg_investment_governance_dashboard(PDO $pdo,array $filters=[]): array
{
    $roundPublicId=trim((string)($filters['round_id']??''));
    $round=null;$roundId=null;
    if($roundPublicId!==''){$round=mg_investment_governance_round($pdo,$roundPublicId);$roundId=(int)$round['id'];}
    $pdo->exec('UPDATE investment_reporting_obligations SET status="overdue",updated_at=NOW() WHERE due_at<NOW() AND status IN ("planned","in_progress","internal_review","counsel_review","ready")');

    $rounds=$pdo->query('SELECT public_id,public_name,status,instrument_type,target_raise_cents,signed_cents,funded_cents,counsel_status FROM investment_rounds ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $participants=$pdo->query('SELECT p.*,COUNT(a.id) AS appointment_count,SUM(a.status="active") AS active_appointment_count FROM investment_governance_participants p LEFT JOIN investment_governance_appointments a ON a.participant_id=p.id GROUP BY p.id ORDER BY p.status,p.display_name')->fetchAll(PDO::FETCH_ASSOC);

    $where=$roundId!==null?' WHERE (m.round_id=? OR m.round_id IS NULL) ':'';
    $meetingsQ=$pdo->prepare('SELECT m.*,r.public_id AS round_public_id,r.public_name,COUNT(DISTINCT a.id) AS attendee_count,COUNT(DISTINCT g.id) AS agenda_count,COUNT(DISTINCT d.id) AS packet_count,MAX(v.version_number) AS latest_minutes_version FROM investment_board_meetings m LEFT JOIN investment_rounds r ON r.id=m.round_id LEFT JOIN investment_board_meeting_attendees a ON a.meeting_id=m.id LEFT JOIN investment_board_agenda_items g ON g.meeting_id=m.id LEFT JOIN investment_board_packet_documents d ON d.meeting_id=m.id LEFT JOIN investment_board_minute_versions v ON v.meeting_id=m.id'.$where.' GROUP BY m.id ORDER BY m.starts_at DESC');
    $meetingsQ->execute($roundId!==null?[$roundId]:[]);$meetings=$meetingsQ->fetchAll(PDO::FETCH_ASSOC);

    $consentWhere=$roundId!==null?' WHERE (c.round_id=? OR c.round_id IS NULL) ':'';
    $consentsQ=$pdo->prepare('SELECT c.*,r.public_id AS round_public_id,r.public_name,b.public_id AS batch_public_id,b.batch_name,COUNT(cp.id) AS participant_count,SUM(cp.response="approved") AS approved_count,SUM(cp.response="declined") AS declined_count,SUM(cp.response="abstained") AS abstained_count,SUM(cp.response="pending") AS pending_count FROM investment_written_consents c LEFT JOIN investment_rounds r ON r.id=c.round_id LEFT JOIN investment_closing_batches b ON b.id=c.closing_batch_id LEFT JOIN investment_consent_participants cp ON cp.consent_id=c.id'.$consentWhere.' GROUP BY c.id ORDER BY c.created_at DESC');
    $consentsQ->execute($roundId!==null?[$roundId]:[]);$consents=$consentsQ->fetchAll(PDO::FETCH_ASSOC);

    $rightsWhere=$roundId!==null?' WHERE ir.round_id=? ':'';
    $rightsQ=$pdo->prepare('SELECT ir.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email FROM investment_investor_rights ir INNER JOIN investment_rounds r ON r.id=ir.round_id INNER JOIN users u ON u.id=ir.investor_user_id'.$rightsWhere.' ORDER BY ir.updated_at DESC');
    $rightsQ->execute($roundId!==null?[$roundId]:[]);$rights=$rightsQ->fetchAll(PDO::FETCH_ASSOC);

    $obligationWhere=$roundId!==null?' WHERE o.round_id=? ':'';
    $obligationsQ=$pdo->prepare('SELECT o.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,au.full_name AS assigned_name,au.display_name AS assigned_display_name FROM investment_reporting_obligations o INNER JOIN investment_rounds r ON r.id=o.round_id LEFT JOIN users u ON u.id=o.investor_user_id LEFT JOIN users au ON au.id=o.assigned_user_id'.$obligationWhere.' ORDER BY FIELD(o.status,"overdue","counsel_review","internal_review","in_progress","planned","ready","completed","waived","cancelled"),o.due_at');
    $obligationsQ->execute($roundId!==null?[$roundId]:[]);$obligations=$obligationsQ->fetchAll(PDO::FETCH_ASSOC);

    $holdingsWhere=$roundId!==null?' WHERE h.round_id=? ':'';
    $holdingsQ=$pdo->prepare('SELECT h.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email FROM investment_holdings_references h INNER JOIN investment_rounds r ON r.id=h.round_id INNER JOIN users u ON u.id=h.investor_user_id'.$holdingsWhere.' ORDER BY h.generated_at DESC');
    $holdingsQ->execute($roundId!==null?[$roundId]:[]);$holdings=$holdingsQ->fetchAll(PDO::FETCH_ASSOC);

    $taxWhere=$roundId!==null?' WHERE td.round_id=? ':'';
    $taxQ=$pdo->prepare('SELECT td.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,v.public_id AS version_public_id,v.external_url,v.external_reference,v.status AS version_status,v.published_at AS version_published_at FROM investment_tax_documents td INNER JOIN investment_rounds r ON r.id=td.round_id INNER JOIN users u ON u.id=td.investor_user_id LEFT JOIN investment_tax_document_versions v ON v.tax_document_id=td.id AND v.version_number=td.current_version_number'.$taxWhere.' ORDER BY td.reporting_year DESC,td.updated_at DESC');
    $taxQ->execute($roundId!==null?[$roundId]:[]);$tax=$taxQ->fetchAll(PDO::FETCH_ASSOC);

    $noticeWhere=$roundId!==null?' WHERE (n.round_id=? OR n.round_id IS NULL) ':'';
    $noticesQ=$pdo->prepare('SELECT n.*,r.public_id AS round_public_id,r.public_name,COUNT(nr.id) AS recipient_count,SUM(nr.status IN ("viewed","acknowledged")) AS viewed_count,SUM(nr.status="acknowledged") AS acknowledged_count FROM investment_material_notices n LEFT JOIN investment_rounds r ON r.id=n.round_id LEFT JOIN investment_material_notice_recipients nr ON nr.notice_id=n.id'.$noticeWhere.' GROUP BY n.id ORDER BY n.created_at DESC');
    $noticesQ->execute($roundId!==null?[$roundId]:[]);$notices=$noticesQ->fetchAll(PDO::FETCH_ASSOC);

    $appointments=$pdo->query('SELECT a.*,p.public_id AS participant_public_id,p.display_name,p.organization,r.public_id AS round_public_id,r.public_name FROM investment_governance_appointments a INNER JOIN investment_governance_participants p ON p.id=a.participant_id LEFT JOIN investment_rounds r ON r.id=a.round_id ORDER BY a.status,a.starts_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $summary=[
        'active_participants'=>count(array_filter($participants,static fn($p)=>(string)$p['status']==='active')),
        'upcoming_meetings'=>count(array_filter($meetings,static fn($m)=>strtotime((string)$m['starts_at'])>=time()&&!in_array($m['status'],['cancelled','archived'],true))),
        'open_consents'=>count(array_filter($consents,static fn($c)=>in_array($c['status'],['internal_review','counsel_review','approved_for_execution','collecting'],true))),
        'active_rights'=>count(array_filter($rights,static fn($r)=>(string)$r['status']==='active')),
        'overdue_obligations'=>count(array_filter($obligations,static fn($o)=>(string)$o['status']==='overdue')),
        'published_tax_documents'=>count(array_filter($tax,static fn($t)=>(string)$t['status']==='published')),
        'published_notices'=>count(array_filter($notices,static fn($n)=>(string)$n['status']==='published')),
        'holdings_references'=>count($holdings),
    ];
    return compact('summary','rounds','round','participants','appointments','meetings','consents','rights','obligations','holdings','tax','notices');
}

function mg_investment_governance_save_participant(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');
    $types=['director','board_observer','officer','committee_member','investor_representative','counsel','administrator','guest','other'];
    $statuses=['active','inactive','removed','archived'];$conf=['not_recorded','requested','confirmed','expired','not_applicable'];
    $type=(string)($input['participant_type']??'other');$status=(string)($input['status']??'active');$confStatus=(string)($input['confidentiality_status']??'not_recorded');
    if(!in_array($type,$types,true)||!in_array($status,$statuses,true)||!in_array($confStatus,$conf,true))throw new MgInvestmentException('Invalid participant type or status.');
    $publicId=trim((string)($input['participant_id']??''));$actorId=(int)$actor['id'];$userId=(int)($input['user_id']??0)?:null;
    $values=[$userId,$type,mg_investment_text($input['display_name']??'',180,2,'Display name'),mg_investment_text($input['organization']??'',180)?:null,mg_investment_text($input['email']??'',254)?:null,mg_investment_text($input['phone']??'',60)?:null,mg_investment_text($input['title']??'',180)?:null,mg_investment_long_text($input['biography']??'',8000)?:null,mg_investment_long_text($input['conflict_disclosure']??'',8000)?:null,$confStatus,$status];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_governance_participants (public_id,user_id,participant_type,display_name,organization,email,phone,title,biography,conflict_disclosure,confidentiality_status,status,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);}
    else{$row=mg_investment_governance_participant($pdo,$publicId);$pdo->prepare('UPDATE investment_governance_participants SET user_id=?,participant_type=?,display_name=?,organization=?,email=?,phone=?,title=?,biography=?,conflict_disclosure=?,confidentiality_status=?,status=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([...$values,$actorId,(int)$row['id']]);}
    mg_audit('investment_governance_participant_saved','investment_governance_participant',['participant_id'=>$publicId,'type'=>$type,'status'=>$status],$actorId);
    return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_appointment(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');
    $participant=mg_investment_governance_participant($pdo,mg_investment_text($input['participant_id']??'',36,36,'Participant identifier'));
    $round=mg_investment_governance_round($pdo,$input['round_id']??'',false);
    $types=['director','board_observer','officer','committee_member','investor_representative','counsel','administrator','other'];
    $sources=['board_resolution','stockholder_consent','financing_agreement','side_letter','employment','engagement_letter','other'];
    $votes=['voting','non_voting','advisory','not_applicable'];$statuses=['planned','active','expired','removed','cancelled','archived'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];
    $type=(string)($input['appointment_type']??'other');$source=(string)($input['appointment_source']??'other');$vote=(string)($input['voting_status']??'not_applicable');$status=(string)($input['status']??'planned');$counselStatus=(string)($input['counsel_status']??'not_started');
    if(!in_array($type,$types,true)||!in_array($source,$sources,true)||!in_array($vote,$votes,true)||!in_array($status,$statuses,true)||!in_array($counselStatus,$counsel,true))throw new MgInvestmentException('Invalid appointment configuration.');
    $url=mg_investment_url($input['appointment_document_url']??'');$publicId=trim((string)($input['appointment_id']??''));$actorId=(int)$actor['id'];
    $values=[(int)$participant['id'],$round?(int)$round['id']:null,$type,$source,mg_investment_text($input['committee_name']??'',180)?:null,$vote,mg_investment_date($input['starts_at']??null),mg_investment_date($input['ends_at']??null),mg_investment_text($input['appointment_document_reference']??'',220)?:null,$url,$counselStatus,$status,mg_investment_long_text($input['internal_notes']??'',8000)?:null];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_governance_appointments (public_id,participant_id,round_id,appointment_type,appointment_source,committee_name,voting_status,starts_at,ends_at,appointment_document_reference,appointment_document_url,counsel_status,status,internal_notes,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);}
    else{$q=$pdo->prepare('SELECT id FROM investment_governance_appointments WHERE public_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Appointment identifier')]);$id=(int)$q->fetchColumn();if($id<1)throw new MgInvestmentException('Governance appointment not found.',404);$pdo->prepare('UPDATE investment_governance_appointments SET participant_id=?,round_id=?,appointment_type=?,appointment_source=?,committee_name=?,voting_status=?,starts_at=?,ends_at=?,appointment_document_reference=?,appointment_document_url=?,counsel_status=?,status=?,internal_notes=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([...$values,$actorId,$id]);}
    mg_audit('investment_governance_appointment_saved','investment_governance_appointment',['appointment_id'=>$publicId,'participant_id'=>$participant['public_id'],'status'=>$status],$actorId);
    return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_meeting(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');
    $round=mg_investment_governance_round($pdo,$input['round_id']??'',false);
    $types=['regular_board','special_board','committee','investor_update','annual','other'];$statuses=['planning','agenda_review','packet_preparation','ready','held','minutes_drafted','minutes_approved','archived','cancelled'];$conf=['internal','board_only','participants','funded_investors_summary'];$quorum=['not_checked','expected','confirmed','not_met','not_applicable'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];$summaryStatuses=['draft','internal_review','approved','published','archived'];
    $type=(string)($input['meeting_type']??'regular_board');$status=(string)($input['status']??'planning');$confidentiality=(string)($input['confidentiality']??'board_only');$quorumStatus=(string)($input['quorum_status']??'not_checked');$counselStatus=(string)($input['counsel_status']??'not_started');$summaryStatus=(string)($input['summary_status']??'draft');
    if(!in_array($type,$types,true)||!in_array($status,$statuses,true)||!in_array($confidentiality,$conf,true)||!in_array($quorumStatus,$quorum,true)||!in_array($counselStatus,$counsel,true)||!in_array($summaryStatus,$summaryStatuses,true))throw new MgInvestmentException('Invalid meeting configuration.');
    if($summaryStatus==='published'){mg_investment_require_permission($actor,'admin.investment.governance.publish');if($counselStatus==='changes_required')throw new MgInvestmentException('A meeting summary cannot be published while counsel changes are required.',409);}
    $starts=mg_investment_governance_datetime($input['starts_at']??'',true,'Meeting start');$ends=mg_investment_governance_datetime($input['ends_at']??'',false,'Meeting end');if($ends!==null&&strtotime($ends)<strtotime((string)$starts))throw new MgInvestmentException('Meeting end cannot be before the start.');
    $publicId=trim((string)($input['meeting_id']??''));$actorId=(int)$actor['id'];$url=mg_investment_url($input['meeting_url']??'');
    $values=[$round?(int)$round['id']:null,$type,mg_investment_text($input['title']??'',220,2,'Meeting title'),$starts,$ends,mg_investment_text($input['location']??'',300)?:null,$url,$status,$confidentiality,$quorumStatus,$counselStatus,mg_investment_long_text($input['investor_visible_summary']??'',12000)?:null,$summaryStatus,$summaryStatus];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_board_meetings (public_id,round_id,meeting_type,title,starts_at,ends_at,location,meeting_url,status,confidentiality,quorum_status,counsel_status,investor_visible_summary,summary_status,summary_published_at,internal_notes,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?="published",NOW(),NULL),?,?,?,NOW(),NOW())')->execute([$publicId,...$values,mg_investment_long_text($input['internal_notes']??'',12000)?:null,$actorId,$actorId]);}
    else{$meeting=mg_investment_governance_meeting($pdo,$publicId);$pdo->prepare('UPDATE investment_board_meetings SET round_id=?,meeting_type=?,title=?,starts_at=?,ends_at=?,location=?,meeting_url=?,status=?,confidentiality=?,quorum_status=?,counsel_status=?,investor_visible_summary=?,summary_status=?,summary_published_at=IF(?="published",COALESCE(summary_published_at,NOW()),summary_published_at),internal_notes=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([...$values,mg_investment_long_text($input['internal_notes']??'',12000)?:null,$actorId,(int)$meeting['id']]);}
    mg_audit('investment_board_meeting_saved','investment_board_meeting',['meeting_id'=>$publicId,'status'=>$status,'summary_status'=>$summaryStatus],$actorId);
    return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_attendee(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');
    $meeting=mg_investment_governance_meeting($pdo,mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier'));
    $participant=mg_investment_governance_participant($pdo,mg_investment_text($input['participant_id']??'',36,36,'Participant identifier'));
    $roles=['chair','director','observer','officer','presenter','counsel','secretary','guest','other'];$statuses=['invited','accepted','declined','tentative','attended','absent','excused'];$conflicts=['none_recorded','disclosed','recused','not_applicable'];
    $role=(string)($input['attendance_role']??'other');$status=(string)($input['attendance_status']??'invited');$conflict=(string)($input['conflict_status']??'none_recorded');
    if(!in_array($role,$roles,true)||!in_array($status,$statuses,true)||!in_array($conflict,$conflicts,true))throw new MgInvestmentException('Invalid attendee configuration.');
    $pdo->prepare('INSERT INTO investment_board_meeting_attendees (meeting_id,participant_id,attendance_role,attendance_status,conflict_status,joined_at,left_at,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE attendance_role=VALUES(attendance_role),attendance_status=VALUES(attendance_status),conflict_status=VALUES(conflict_status),joined_at=VALUES(joined_at),left_at=VALUES(left_at),notes=VALUES(notes),updated_at=NOW()')->execute([(int)$meeting['id'],(int)$participant['id'],$role,$status,$conflict,mg_investment_governance_datetime($input['joined_at']??''),mg_investment_governance_datetime($input['left_at']??''),mg_investment_long_text($input['notes']??'',6000)?:null]);
    mg_audit('investment_board_attendee_saved','investment_board_meeting',['meeting_id'=>$meeting['public_id'],'participant_id'=>$participant['public_id'],'status'=>$status],(int)$actor['id']);
    return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_agenda(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$meeting=mg_investment_governance_meeting($pdo,mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier'));
    $types=['opening','approval','discussion','report','resolution','executive_session','closing','other'];$decisions=['not_required','pending','approved','declined','tabled','withdrawn'];$conf=['internal','board_only','participants','funded_investors_summary'];$statuses=['draft','ready','completed','tabled','cancelled'];
    $type=(string)($input['item_type']??'discussion');$decision=(string)($input['decision_status']??'not_required');$confidentiality=(string)($input['confidentiality']??'board_only');$status=(string)($input['status']??'draft');
    if(!in_array($type,$types,true)||!in_array($decision,$decisions,true)||!in_array($confidentiality,$conf,true)||!in_array($status,$statuses,true))throw new MgInvestmentException('Invalid agenda item configuration.');
    $presenterId=null;if(!empty($input['presenter_id']))$presenterId=(int)mg_investment_governance_participant($pdo,mg_investment_text($input['presenter_id'],36,36,'Presenter identifier'))['id'];
    $publicId=trim((string)($input['agenda_id']??''));$actorId=(int)$actor['id'];$sequence=max(1,(int)($input['sequence_number']??1));
    $values=[(int)$meeting['id'],$sequence,$type,mg_investment_text($input['title']??'',220,2,'Agenda title'),mg_investment_long_text($input['description']??'',8000)?:null,$presenterId,max(1,(int)($input['planned_minutes']??10)),mg_investment_bool($input['approval_required']??false)?1:0,$decision,mg_investment_long_text($input['decision_summary']??'',8000)?:null,$confidentiality,$status];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_board_agenda_items (public_id,meeting_id,sequence_number,item_type,title,description,presenter_participant_id,planned_minutes,approval_required,decision_status,decision_summary,confidentiality,status,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);}
    else{$q=$pdo->prepare('SELECT id FROM investment_board_agenda_items WHERE public_id=? AND meeting_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Agenda identifier'),(int)$meeting['id']]);$id=(int)$q->fetchColumn();if($id<1)throw new MgInvestmentException('Agenda item not found.',404);$pdo->prepare('UPDATE investment_board_agenda_items SET sequence_number=?,item_type=?,title=?,description=?,presenter_participant_id=?,planned_minutes=?,approval_required=?,decision_status=?,decision_summary=?,confidentiality=?,status=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$sequence,$type,$values[3],$values[4],$presenterId,$values[6],$values[7],$decision,$values[9],$confidentiality,$status,$actorId,$id]);}
    mg_audit('investment_board_agenda_saved','investment_board_meeting',['meeting_id'=>$meeting['public_id'],'agenda_id'=>$publicId],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_packet_document(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$meeting=mg_investment_governance_meeting($pdo,mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier'));
    $types=['agenda','financial_report','operating_report','resolution','presentation','minutes','legal','supporting','other'];$conf=['internal','board_only','participants','funded_investors'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];$statuses=['draft','internal_review','approved','published','superseded','archived'];
    $type=(string)($input['document_type']??'supporting');$confidentiality=(string)($input['confidentiality']??'board_only');$counselStatus=(string)($input['counsel_status']??'not_started');$status=(string)($input['status']??'draft');
    if(!in_array($type,$types,true)||!in_array($confidentiality,$conf,true)||!in_array($counselStatus,$counsel,true)||!in_array($status,$statuses,true))throw new MgInvestmentException('Invalid packet document configuration.');
    if($status==='published'){mg_investment_require_permission($actor,'admin.investment.governance.publish');if($confidentiality!=='funded_investors')throw new MgInvestmentException('Only funded-investor packet documents may be published to the Investor Portal.',409);if($counselStatus==='changes_required')throw new MgInvestmentException('Packet document cannot be published while counsel changes are required.',409);}
    $agendaId=null;if(!empty($input['agenda_id'])){$q=$pdo->prepare('SELECT id FROM investment_board_agenda_items WHERE public_id=? AND meeting_id=? LIMIT 1');$q->execute([mg_investment_text($input['agenda_id'],36,36,'Agenda identifier'),(int)$meeting['id']]);$agendaId=(int)$q->fetchColumn()?:null;}
    $publicId=mg_investment_uuid();$n=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM investment_board_packet_documents WHERE meeting_id=? AND title=?');$title=mg_investment_text($input['title']??'',220,2,'Document title');$n->execute([(int)$meeting['id'],$title]);$version=(int)$n->fetchColumn();$actorId=(int)$actor['id'];
    if($status==='published')$pdo->prepare('UPDATE investment_board_packet_documents SET status="superseded" WHERE meeting_id=? AND title=? AND status="published"')->execute([(int)$meeting['id'],$title]);
    $pdo->prepare('INSERT INTO investment_board_packet_documents (public_id,meeting_id,agenda_item_id,document_type,title,external_url,version_number,confidentiality,counsel_status,status,approved_by_user_id,approved_at,published_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,IF(? IN ("approved","published"),?,NULL),IF(? IN ("approved","published"),NOW(),NULL),IF(?="published",NOW(),NULL),?,NOW())')->execute([$publicId,(int)$meeting['id'],$agendaId,$type,$title,mg_investment_url($input['external_url']??''),$version,$confidentiality,$counselStatus,$status,$status,$actorId,$status,$status,$actorId]);
    mg_audit('investment_board_packet_document_saved','investment_board_meeting',['meeting_id'=>$meeting['public_id'],'document_id'=>$publicId,'version'=>$version,'status'=>$status],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_minutes(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$meeting=mg_investment_governance_meeting($pdo,mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier'));
    $statuses=['draft','internal_review','counsel_review','approved','superseded','archived'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];$status=(string)($input['status']??'draft');$counselStatus=(string)($input['counsel_status']??'not_started');
    if(!in_array($status,$statuses,true)||!in_array($counselStatus,$counsel,true))throw new MgInvestmentException('Invalid minutes status.');if($status==='approved'&&$counselStatus==='changes_required')throw new MgInvestmentException('Minutes cannot be approved while counsel changes are required.',409);
    $n=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM investment_board_minute_versions WHERE meeting_id=?');$n->execute([(int)$meeting['id']]);$version=(int)$n->fetchColumn();$publicId=mg_investment_uuid();$actorId=(int)$actor['id'];
    if($status==='approved')$pdo->prepare('UPDATE investment_board_minute_versions SET status="superseded" WHERE meeting_id=? AND status="approved"')->execute([(int)$meeting['id']]);
    $pdo->prepare('INSERT INTO investment_board_minute_versions (public_id,meeting_id,version_number,minutes_text,status,counsel_status,approved_by_user_id,approved_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,IF(?="approved",?,NULL),IF(?="approved",NOW(),NULL),?,NOW())')->execute([$publicId,(int)$meeting['id'],$version,mg_investment_long_text($input['minutes_text']??'',50000,20,'Meeting minutes'),$status,$counselStatus,$status,$actorId,$status,$actorId]);
    $pdo->prepare('UPDATE investment_board_meetings SET status=IF(?="approved","minutes_approved","minutes_drafted"),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$status,$actorId,(int)$meeting['id']]);
    mg_audit('investment_board_minutes_version_saved','investment_board_meeting',['meeting_id'=>$meeting['public_id'],'minutes_id'=>$publicId,'version'=>$version,'status'=>$status],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_consent(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$round=mg_investment_governance_round($pdo,$input['round_id']??'',false);
    $batchId=null;if(!empty($input['batch_id'])){$q=$pdo->prepare('SELECT id FROM investment_closing_batches WHERE public_id=? LIMIT 1');$q->execute([mg_investment_text($input['batch_id'],36,36,'Batch identifier')]);$batchId=(int)$q->fetchColumn()?:null;}
    $types=['board','stockholder','officer','committee','financing','compensation','contract','banking','other'];$statuses=['draft','internal_review','counsel_review','approved_for_execution','collecting','executed','declined','expired','archived'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];
    $type=(string)($input['consent_type']??'board');$status=(string)($input['status']??'draft');$counselStatus=(string)($input['counsel_status']??'not_started');
    if(!in_array($type,$types,true)||!in_array($status,$statuses,true)||!in_array($counselStatus,$counsel,true))throw new MgInvestmentException('Invalid written-consent configuration.');if(in_array($status,['approved_for_execution','collecting','executed'],true)&&!in_array($counselStatus,['approved','not_applicable'],true))throw new MgInvestmentException('Counsel approval or not-applicable status is required before execution tracking.',409);
    $publicId=trim((string)($input['consent_id']??''));$actorId=(int)$actor['id'];$values=[$round?(int)$round['id']:null,$batchId,$type,mg_investment_text($input['title']??'',220,2,'Consent title'),mg_investment_long_text($input['resolution_text']??'',50000,20,'Resolution text'),mg_investment_text($input['approval_group']??'',180,2,'Approval group'),mg_investment_text($input['approval_threshold']??'',180)?:null,$status,$counselStatus,mg_investment_governance_datetime($input['effective_at']??''),mg_investment_governance_datetime($input['response_due_at']??''),mg_investment_text($input['executed_document_reference']??'',220)?:null,mg_investment_url($input['executed_document_url']??''),mg_investment_long_text($input['internal_notes']??'',12000)?:null];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_written_consents (public_id,round_id,closing_batch_id,consent_type,title,resolution_text,approval_group,approval_threshold,status,counsel_status,effective_at,response_due_at,executed_document_reference,executed_document_url,internal_notes,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);}
    else{$consent=mg_investment_governance_consent($pdo,$publicId);$pdo->prepare('UPDATE investment_written_consents SET round_id=?,closing_batch_id=?,consent_type=?,title=?,resolution_text=?,approval_group=?,approval_threshold=?,status=?,counsel_status=?,effective_at=?,response_due_at=?,executed_document_reference=?,executed_document_url=?,internal_notes=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([...$values,$actorId,(int)$consent['id']]);}
    mg_audit('investment_written_consent_saved','investment_written_consent',['consent_id'=>$publicId,'status'=>$status],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_record_consent_response(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.vote');$consent=mg_investment_governance_consent($pdo,mg_investment_text($input['consent_id']??'',36,36,'Consent identifier'));$participant=mg_investment_governance_participant($pdo,mg_investment_text($input['participant_id']??'',36,36,'Participant identifier'));
    if(!in_array($consent['status'],['approved_for_execution','collecting','executed'],true))throw new MgInvestmentException('Consent responses may only be recorded after approval for external execution.',409);
    $responses=['pending','approved','declined','abstained','not_required'];$response=(string)($input['response']??'pending');if(!in_array($response,$responses,true))throw new MgInvestmentException('Invalid consent response.');
    $pdo->prepare('INSERT INTO investment_consent_participants (consent_id,participant_id,response,external_signature_reference,responded_at,recorded_by_user_id,notes,created_at,updated_at) VALUES (?,?,?,?,IF(?="pending",NULL,NOW()),?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE response=VALUES(response),external_signature_reference=VALUES(external_signature_reference),responded_at=IF(VALUES(response)="pending",NULL,NOW()),recorded_by_user_id=VALUES(recorded_by_user_id),notes=VALUES(notes),updated_at=NOW()')->execute([(int)$consent['id'],(int)$participant['id'],$response,mg_investment_text($input['external_signature_reference']??'',220)?:null,$response,(int)$actor['id'],mg_investment_long_text($input['notes']??'',6000)?:null]);
    mg_audit('investment_consent_response_recorded','investment_written_consent',['consent_id'=>$consent['public_id'],'participant_id'=>$participant['public_id'],'response'=>$response,'external_execution'=>true],(int)$actor['id']);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_save_right(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.rights.manage');$round=mg_investment_governance_round($pdo,$input['round_id']??'');$investorId=(int)($input['investor_user_id']??0);if($investorId<1)throw new MgInvestmentException('Investor is required.');
    $funded=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0');$funded->execute([(int)$round['id'],$investorId]);if((int)$funded->fetchColumn()<1)throw new MgInvestmentException('Investor rights may only be assigned to a verified funded investor for this round.',409);
    $types=['information','inspection','quarterly_reporting','annual_reporting','budget_delivery','tax_document','pro_rata','major_investor','mfn','board_observer','notice','side_letter','consent_reference','other'];$cadences=['none','monthly','quarterly','annual','event_driven','custom'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];$statuses=['draft','active','suspended','expired','terminated','archived'];
    $type=(string)($input['right_type']??'information');$cadence=(string)($input['cadence']??'none');$counselStatus=(string)($input['counsel_status']??'not_started');$status=(string)($input['status']??'draft');
    if(!in_array($type,$types,true)||!in_array($cadence,$cadences,true)||!in_array($counselStatus,$counsel,true)||!in_array($status,$statuses,true))throw new MgInvestmentException('Invalid investor-right configuration.');if($status==='active'&&!in_array($counselStatus,['approved','not_applicable'],true))throw new MgInvestmentException('Counsel approval or not-applicable status is required before activating an investor right.',409);
    $publicId=trim((string)($input['right_id']??''));$actorId=(int)$actor['id'];$values=[(int)$round['id'],$investorId,$type,mg_investment_text($input['title']??'',220,2,'Right title'),mg_investment_long_text($input['description']??'',10000)?:null,mg_investment_text($input['source_document_reference']??'',220,2,'Source document reference'),mg_investment_url($input['source_document_url']??''),$cadence,mg_investment_text($input['custom_cadence']??'',180)?:null,mg_investment_date($input['starts_at']??null),mg_investment_date($input['expires_at']??null),$counselStatus,$status,mg_investment_bool($input['investor_visible']??false)?1:0,mg_investment_long_text($input['internal_notes']??'',10000)?:null];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_investor_rights (public_id,round_id,investor_user_id,right_type,title,description,source_document_reference,source_document_url,cadence,custom_cadence,starts_at,expires_at,counsel_status,status,investor_visible,internal_notes,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);}
    else{$q=$pdo->prepare('SELECT id FROM investment_investor_rights WHERE public_id=? AND round_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Right identifier'),(int)$round['id']]);$id=(int)$q->fetchColumn();if($id<1)throw new MgInvestmentException('Investor right not found.',404);$pdo->prepare('UPDATE investment_investor_rights SET investor_user_id=?,right_type=?,title=?,description=?,source_document_reference=?,source_document_url=?,cadence=?,custom_cadence=?,starts_at=?,expires_at=?,counsel_status=?,status=?,investor_visible=?,internal_notes=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$investorId,$type,$values[3],$values[4],$values[5],$values[6],$cadence,$values[8],$values[9],$values[10],$counselStatus,$status,$values[13],$values[14],$actorId,$id]);}
    mg_audit('investment_investor_right_saved','investment_investor_right',['right_id'=>$publicId,'round_id'=>$round['public_id'],'investor_user_id'=>$investorId,'status'=>$status],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_obligation_event(PDO $pdo,int $obligationId,?int $actorId,string $type,array $details=[]): void
{
    $types=['created','status_changed','assigned','deadline_changed','review_requested','published','completed','reopened','waived','note'];if(!in_array($type,$types,true))throw new MgInvestmentException('Invalid obligation event type.');
    $pdo->prepare('INSERT INTO investment_reporting_obligation_events (public_id,obligation_id,actor_user_id,event_type,details_json,created_at) VALUES (?,?,?,?,?,NOW())')->execute([mg_investment_uuid(),$obligationId,$actorId,$type,$details?mg_investment_json_encode($details):null]);
}

function mg_investment_governance_save_obligation(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.obligations.manage');$round=mg_investment_governance_round($pdo,$input['round_id']??'');$types=['quarterly_report','annual_report','annual_budget','tax_documents','material_event_notice','board_meeting_notice','financing_notice','pro_rata_notice','information_request','custom'];$scopes=['specific_investor','funded_investors','major_investors','rights_holders','board','custom'];$publication=['not_required','draft','approved','published','archived'];$statuses=['planned','in_progress','internal_review','counsel_review','ready','completed','overdue','waived','cancelled'];
    $type=(string)($input['obligation_type']??'quarterly_report');$scope=(string)($input['recipient_scope']??'funded_investors');$pub=(string)($input['portal_publication_status']??'not_required');$status=(string)($input['status']??'planned');if(!in_array($type,$types,true)||!in_array($scope,$scopes,true)||!in_array($pub,$publication,true)||!in_array($status,$statuses,true))throw new MgInvestmentException('Invalid reporting-obligation configuration.');
    $investorId=(int)($input['investor_user_id']??0)?:null;if($scope==='specific_investor'&&$investorId===null)throw new MgInvestmentException('A specific investor is required for this recipient scope.');
    $rightId=null;if(!empty($input['right_id'])){$q=$pdo->prepare('SELECT id FROM investment_investor_rights WHERE public_id=? AND round_id=? LIMIT 1');$q->execute([mg_investment_text($input['right_id'],36,36,'Right identifier'),(int)$round['id']]);$rightId=(int)$q->fetchColumn()?:null;}
    $due=mg_investment_governance_datetime($input['due_at']??'',true,'Obligation due date');$review=mg_investment_governance_datetime($input['internal_review_due_at']??'');if($review!==null&&strtotime($review)>strtotime($due))throw new MgInvestmentException('Internal review deadline cannot be after the final due date.');
    $publicId=trim((string)($input['obligation_id']??''));$actorId=(int)$actor['id'];$values=[(int)$round['id'],$investorId,$rightId,$type,mg_investment_text($input['title']??'',220,2,'Obligation title'),mg_investment_text($input['reporting_period']??'',120)?:null,$due,$review,mg_investment_bool($input['counsel_review_required']??false)?1:0,(int)($input['assigned_user_id']??0)?:null,$scope,mg_investment_text($input['recurrence_rule']??'',500)?:null,mg_investment_text($input['completion_reference']??'',220)?:null,$pub,$status,$status,mg_investment_long_text($input['internal_notes']??'',10000)?:null];
    if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_reporting_obligations (public_id,round_id,investor_user_id,right_id,obligation_type,title,reporting_period,due_at,internal_review_due_at,counsel_review_required,assigned_user_id,recipient_scope,recurrence_rule,completion_reference,portal_publication_status,status,completed_at,internal_notes,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?="completed",NOW(),NULL),?,?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);$id=(int)$pdo->lastInsertId();mg_investment_governance_obligation_event($pdo,$id,$actorId,'created',['status'=>$status,'due_at'=>$due]);}
    else{$q=$pdo->prepare('SELECT * FROM investment_reporting_obligations WHERE public_id=? AND round_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Obligation identifier'),(int)$round['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new MgInvestmentException('Reporting obligation not found.',404);$pdo->prepare('UPDATE investment_reporting_obligations SET investor_user_id=?,right_id=?,obligation_type=?,title=?,reporting_period=?,due_at=?,internal_review_due_at=?,counsel_review_required=?,assigned_user_id=?,recipient_scope=?,recurrence_rule=?,completion_reference=?,portal_publication_status=?,status=?,completed_at=IF(?="completed",COALESCE(completed_at,NOW()),NULL),internal_notes=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$investorId,$rightId,$type,$values[4],$values[5],$due,$review,$values[8],$values[9],$scope,$values[11],$values[12],$pub,$status,$status,$values[16],$actorId,(int)$row['id']]);if((string)$row['status']!==$status)mg_investment_governance_obligation_event($pdo,(int)$row['id'],$actorId,'status_changed',['from'=>$row['status'],'to'=>$status]);if((string)$row['due_at']!==$due)mg_investment_governance_obligation_event($pdo,(int)$row['id'],$actorId,'deadline_changed',['from'=>$row['due_at'],'to'=>$due]);}
    mg_audit('investment_reporting_obligation_saved','investment_reporting_obligation',['obligation_id'=>$publicId,'round_id'=>$round['public_id'],'status'=>$status],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_complete_obligation(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.obligations.manage');$publicId=mg_investment_text($input['obligation_id']??'',36,36,'Obligation identifier');$q=$pdo->prepare('SELECT o.*,r.public_id AS round_public_id FROM investment_reporting_obligations o INNER JOIN investment_rounds r ON r.id=o.round_id WHERE o.public_id=? LIMIT 1');$q->execute([$publicId]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new MgInvestmentException('Reporting obligation not found.',404);
    $reference=mg_investment_text($input['completion_reference']??'',220,2,'Completion reference');$pdo->prepare('UPDATE investment_reporting_obligations SET status="completed",completion_reference=?,completed_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$reference,(int)$actor['id'],(int)$row['id']]);mg_investment_governance_obligation_event($pdo,(int)$row['id'],(int)$actor['id'],'completed',['completion_reference'=>$reference]);mg_audit('investment_reporting_obligation_completed','investment_reporting_obligation',['obligation_id'=>$publicId,'completion_reference'=>$reference],(int)$actor['id']);return mg_investment_governance_dashboard($pdo,['round_id'=>$row['round_public_id']]);
}

function mg_investment_governance_refresh_holdings(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$round=mg_investment_governance_round($pdo,$input['round_id']??'');$actorId=(int)$actor['id'];
    $q=$pdo->prepare('SELECT cr.*,b.batch_name,b.public_id AS batch_public_id,u.full_name,u.display_name,u.email FROM investor_closing_records cr LEFT JOIN investment_closing_batches b ON b.id=cr.batch_id INNER JOIN users u ON u.id=cr.investor_user_id WHERE cr.round_id=? AND cr.verified_funded_cents>0 AND cr.status NOT IN ("withdrawn","declined")');$q->execute([(int)$round['id']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $reconQ=$pdo->prepare('SELECT public_id FROM investment_cap_reconciliation_snapshots WHERE round_id=? ORDER BY created_at DESC LIMIT 1');$reconQ->execute([(int)$round['id']]);$recon=$reconQ->fetchColumn()?:null;
    foreach($rows as $row){$rightsQ=$pdo->prepare('SELECT COUNT(*) FROM investment_investor_rights WHERE round_id=? AND investor_user_id=? AND status="active"');$rightsQ->execute([(int)$round['id'],(int)$row['investor_user_id']]);$rights=(int)$rightsQ->fetchColumn()>0?'active':'none_recorded';$taxQ=$pdo->prepare('SELECT COUNT(*) FROM investment_tax_documents WHERE round_id=? AND investor_user_id=? AND status="published"');$taxQ->execute([(int)$round['id'],(int)$row['investor_user_id']]);$tax=(int)$taxQ->fetchColumn()>0?'available':'not_started';$snapshot=['round_public_id'=>$round['public_id'],'closing_record_public_id'=>$row['public_id'],'investor_user_id'=>(int)$row['investor_user_id'],'instrument_type'=>$row['instrument_type'],'verified_funded_cents'=>(int)$row['verified_funded_cents'],'agreement_reference'=>$row['agreement_reference'],'batch_public_id'=>$row['batch_public_id']];$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_holdings_references (public_id,round_id,investor_user_id,closing_record_id,instrument_type,verified_funded_cents,closing_batch_reference,agreement_reference,conversion_or_maturity_reference,information_rights_status,tax_document_status,latest_reconciliation_public_id,source_snapshot_json,generated_by_user_id,generated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE public_id=VALUES(public_id),instrument_type=VALUES(instrument_type),verified_funded_cents=VALUES(verified_funded_cents),closing_batch_reference=VALUES(closing_batch_reference),agreement_reference=VALUES(agreement_reference),conversion_or_maturity_reference=VALUES(conversion_or_maturity_reference),information_rights_status=VALUES(information_rights_status),tax_document_status=VALUES(tax_document_status),latest_reconciliation_public_id=VALUES(latest_reconciliation_public_id),source_snapshot_json=VALUES(source_snapshot_json),generated_by_user_id=VALUES(generated_by_user_id),generated_at=NOW()')->execute([$publicId,(int)$round['id'],(int)$row['investor_user_id'],(int)$row['id'],(string)$row['instrument_type'],(int)$row['verified_funded_cents'],mg_investment_text($row['batch_name']??'',220)?:null,mg_investment_text($row['agreement_reference']??'',220)?:null,mg_investment_text($input['conversion_or_maturity_reference']??'',220)?:null,$rights,$tax,$recon,mg_investment_json_encode($snapshot),$actorId]);}
    mg_audit('investment_holdings_references_refreshed','investment_round',['round_id'=>$round['public_id'],'records'=>count($rows),'official_stock_ledger'=>false],$actorId);return mg_investment_governance_dashboard($pdo,['round_id'=>$round['public_id']]);
}
