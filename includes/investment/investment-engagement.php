<?php
declare(strict_types=1);

function mg_investment_engagement_calculate(PDO $pdo,int $investorUserId,?int $roundId=null): array
{
    $roundClause=$roundId!==null?' AND round_id='.(int)$roundId:'';
    $events=$pdo->query('SELECT event_type,COUNT(*) AS total,COUNT(DISTINCT subject_public_id) AS unique_subjects,MAX(created_at) AS latest FROM investment_portal_events WHERE investor_user_id='.(int)$investorUserId.$roundClause.' GROUP BY event_type')->fetchAll(PDO::FETCH_ASSOC);
    $counts=['portal_sessions'=>0,'round_views'=>0,'document_views'=>0,'unique_documents_viewed'=>0,'metric_views'=>0,'questions_submitted'=>0,'communications_viewed'=>0,'meetings_completed'=>0];$last=null;
    foreach($events as $event){$type=(string)$event['event_type'];$total=(int)$event['total'];if($type==='portal_open')$counts['portal_sessions']=$total;elseif($type==='round_view')$counts['round_views']=$total;elseif($type==='document_open'){$counts['document_views']=$total;$counts['unique_documents_viewed']=(int)$event['unique_subjects'];}elseif($type==='metric_view')$counts['metric_views']=$total;elseif($type==='communication_view')$counts['communications_viewed']=$total;if(!empty($event['latest'])&&($last===null||strtotime((string)$event['latest'])>strtotime($last)))$last=(string)$event['latest'];}
    $q=$pdo->query('SELECT COUNT(*) AS total,MAX(created_at) AS latest FROM investor_diligence_requests WHERE investor_user_id='.(int)$investorUserId.($roundId!==null?' AND round_id='.(int)$roundId:''))->fetch(PDO::FETCH_ASSOC)?:[];$counts['questions_submitted']=(int)($q['total']??0);if(!empty($q['latest'])&&($last===null||strtotime((string)$q['latest'])>strtotime($last)))$last=(string)$q['latest'];
    $m=$pdo->query('SELECT COUNT(*) AS total,MAX(starts_at) AS latest FROM investor_meetings WHERE investor_user_id='.(int)$investorUserId.' AND status="completed"'.($roundId!==null?' AND round_id='.(int)$roundId:''))->fetch(PDO::FETCH_ASSOC)?:[];$counts['meetings_completed']=(int)($m['total']??0);if(!empty($m['latest'])&&($last===null||strtotime((string)$m['latest'])>strtotime($last)))$last=(string)$m['latest'];
    $days=$last!==null?max(0,(int)floor((time()-strtotime($last))/86400)):null;
    $components=[
        'portal_sessions'=>min(15,$counts['portal_sessions']*2),
        'round_views'=>min(10,$counts['round_views']*2),
        'document_views'=>min(20,$counts['document_views']*2),
        'unique_documents'=>min(10,$counts['unique_documents_viewed']*2),
        'metric_views'=>min(8,$counts['metric_views']),
        'questions'=>min(12,$counts['questions_submitted']*4),
        'communications'=>min(10,$counts['communications_viewed']*2),
        'meetings'=>min(10,$counts['meetings_completed']*5),
        'recency'=>$days===null?0:($days<=7?15:($days<=30?8:($days<=60?3:0))),
    ];
    $score=min(100,array_sum($components));$stalled=$days===null||$days>30;
    return $counts+['days_since_last_engagement'=>$days,'engagement_score'=>$score,'stalled'=>$stalled,'calculation'=>['components'=>$components,'last_engagement_at'=>$last,'formula_version'=>'v3.1','maximum_score'=>100]];
}

function mg_investment_engagement_refresh(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.engagement.view');$investorUserId=(int)($input['investor_user_id']??0);$roundId=null;$roundPublicId='';if(!empty($input['round_id'])){$round=mg_investment_diligence_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'));$roundId=(int)$round['id'];$roundPublicId=(string)$round['public_id'];}
    $users=[];if($investorUserId>0)$users=[$investorUserId];else $users=array_map('intval',$pdo->query('SELECT user_id FROM investor_profiles WHERE status="active"')->fetchAll(PDO::FETCH_COLUMN));
    $stmt=$pdo->prepare('INSERT INTO investor_engagement_snapshots (public_id,investor_user_id,round_id,portal_sessions,round_views,document_views,unique_documents_viewed,metric_views,questions_submitted,communications_viewed,meetings_completed,days_since_last_engagement,engagement_score,stalled,calculation_json,snapshot_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    foreach($users as $userId){$c=mg_investment_engagement_calculate($pdo,$userId,$roundId);$stmt->execute([mg_investment_uuid(),$userId,$roundId,$c['portal_sessions'],$c['round_views'],$c['document_views'],$c['unique_documents_viewed'],$c['metric_views'],$c['questions_submitted'],$c['communications_viewed'],$c['meetings_completed'],$c['days_since_last_engagement'],$c['engagement_score'],$c['stalled']?1:0,mg_investment_json_encode($c['calculation'])]);}
    mg_audit('investor_engagement_refreshed','investor_engagement',['round_id'=>$roundPublicId?:null,'investor_user_id'=>$investorUserId?:null,'count'=>count($users)],(int)$actor['id']);return mg_investment_diligence_dashboard($pdo,$roundPublicId!==''?['round_id'=>$roundPublicId]:[]);
}

function mg_investment_diligence_ai_draft(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.ai');$type=mg_investment_text($input['draft_type']??'diligence_response',80,3,'Draft type');$allowed=['diligence_response','meeting_briefing','meeting_recap','monthly_update','missing_materials','engagement_summary','question_comparison'];if(!in_array($type,$allowed,true))throw new MgInvestmentException('Invalid diligence AI action.');
    $context=['draft_type'=>$type];$roundPublicId='';if(!empty($input['round_id'])){$round=mg_investment_diligence_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'));$roundPublicId=(string)$round['public_id'];$context['round']=['public_id'=>$round['public_id'],'public_name'=>$round['public_name'],'status'=>$round['status'],'target_raise_cents'=>$round['target_raise_cents'],'funded_cents'=>$round['funded_cents'],'counsel_status'=>$round['counsel_status']];}
    if(!empty($input['request_id'])){$q=$pdo->prepare('SELECT q.*,u.full_name,u.email,r.public_name FROM investor_diligence_requests q INNER JOIN users u ON u.id=q.investor_user_id INNER JOIN investment_rounds r ON r.id=q.round_id WHERE q.public_id=? LIMIT 1');$q->execute([mg_investment_text($input['request_id'],36,36,'Request identifier')]);$context['request']=$q->fetch(PDO::FETCH_ASSOC)?:null;}
    if(!empty($input['investor_user_id'])){$userId=(int)$input['investor_user_id'];$q=$pdo->prepare('SELECT u.full_name,u.display_name,u.email,ip.firm_name,pr.stage,pr.priority,pr.qualification_score,pr.internal_notes FROM users u LEFT JOIN investor_profiles ip ON ip.user_id=u.id LEFT JOIN investor_pipeline_records pr ON pr.investor_user_id=u.id WHERE u.id=? LIMIT 1');$q->execute([$userId]);$context['investor']=$q->fetch(PDO::FETCH_ASSOC)?:null;$context['engagement']=mg_investment_engagement_calculate($pdo,$userId,!empty($round)?(int)$round['id']:null);}
    $context['admin_instruction']=mg_investment_long_text($input['instruction']??'',6000);
    $model=mg_investment_claude_model($pdo);$publicId=mg_investment_uuid();$workspaceId=null;if(!empty($round))$workspaceId=(int)$round['workspace_id'];else{$workspaceId=(int)$pdo->query('SELECT id FROM investment_workspaces ORDER BY updated_at DESC LIMIT 1')->fetchColumn();}if($workspaceId<1)throw new MgInvestmentException('Create an Investment Wizard workspace before using Claude.',409);
    $insert=$pdo->prepare('INSERT INTO investment_ai_analyses (public_id,workspace_id,round_id,requested_by_user_id,provider,model,analysis_type,input_snapshot_json,status,created_at) VALUES (?,?,?,? ,"anthropic",?,?,?,"requested",NOW())');$insert->execute([$publicId,$workspaceId,!empty($round)?(int)$round['id']:null,(int)$actor['id'],$model,'diligence_'.$type,mg_investment_json_encode($context)]);$analysisId=(int)$pdo->lastInsertId();
    try{require_once dirname(__DIR__).'/ai/anthropic-client.php';$system='You are the Microgifter Investor Due-Diligence Assistant. Draft from supplied records only. Do not invent financial results, legal approval, commitments, accreditation, terms, documents, or evidence. Clearly mark missing facts and professional-review needs. Your output is an internal editable draft and cannot be sent or published automatically.';$response=mg_anthropic_messages(['model'=>$model,'max_tokens'=>1800,'temperature'=>0.2,'system'=>$system,'messages'=>[['role'=>'user','content'=>mg_investment_json_encode($context)]]]);$text=mg_anthropic_text_from_response($response);$usage=is_array($response['usage']??null)?$response['usage']:[];$pdo->prepare('UPDATE investment_ai_analyses SET response_text=?,status="completed",input_tokens=?,output_tokens=?,completed_at=NOW() WHERE id=?')->execute([$text,(int)($usage['input_tokens']??0),(int)($usage['output_tokens']??0),$analysisId]);mg_audit('investor_diligence_ai_completed','investment_ai',['analysis_id'=>$publicId,'draft_type'=>$type],(int)$actor['id']);}catch(Throwable $e){$pdo->prepare('UPDATE investment_ai_analyses SET status="failed",error_message=?,completed_at=NOW() WHERE id=?')->execute([mb_substr($e->getMessage(),0,1000),$analysisId]);}
    $q=$pdo->prepare('SELECT public_id,analysis_type,response_text,status,error_message,created_at FROM investment_ai_analyses WHERE id=?');$q->execute([$analysisId]);return ['analysis'=>$q->fetch(PDO::FETCH_ASSOC),'dashboard'=>mg_investment_diligence_dashboard($pdo,$roundPublicId!==''?['round_id'=>$roundPublicId]:[])];
}
