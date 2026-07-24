<?php
declare(strict_types=1);

function mg_investment_publication_default_sections(): array
{
    return [
        'company_summary'=>true,'round_terms'=>true,'raise_progress'=>true,'use_of_funds'=>true,
        'goals'=>true,'evidence_metrics'=>true,'documents'=>true,'founder_update'=>true,'important_notice'=>true,
    ];
}

function mg_investment_publication_get(PDO $pdo,int $roundId): array
{
    $stmt=$pdo->prepare('SELECT * FROM investment_round_publication WHERE round_id=? LIMIT 1');$stmt->execute([$roundId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)return ['publication_status'=>'draft','sections'=>mg_investment_publication_default_sections(),'founder_update'=>null,'important_notice'=>null,'published_at'=>null,'updated_at'=>null];
    $row['sections']=array_replace(mg_investment_publication_default_sections(),mg_investment_json($row['sections_json']));unset($row['sections_json'],$row['preview_token_hash']);return $row;
}

function mg_investment_publication_save(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.publish');$round=mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$status=(string)($input['publication_status']??'draft');
    if(!in_array($status,['draft','internal_preview','private_preview','published','paused','archived'],true))throw new MgInvestmentException('Invalid publication status.');
    if(in_array($status,['private_preview','published'],true)&&!in_array((string)$round['status'],['private_preview','open','minimum_reached','closing','closed'],true))throw new MgInvestmentException('The official round must be in private preview or a later round status before portal publication.',409);
    if(in_array($status,['private_preview','published'],true)&&(string)$round['counsel_status']!=='approved')throw new MgInvestmentException('Counsel status must be approved before private publication.',409);
    $sections=mg_investment_publication_default_sections();foreach($sections as $key=>$default)$sections[$key]=mg_investment_bool($input['sections'][$key]??$default);
    $actorId=(int)$actor['id'];$founder=mg_investment_long_text($input['founder_update']??'',12000);$notice=mg_investment_long_text($input['important_notice']??'',6000);
    $stmt=$pdo->prepare('INSERT INTO investment_round_publication (round_id,publication_status,sections_json,founder_update,important_notice,published_by_user_id,published_at,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,IF(? IN ("private_preview","published"),?,NULL),IF(? IN ("private_preview","published"),NOW(),NULL),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE publication_status=VALUES(publication_status),sections_json=VALUES(sections_json),founder_update=VALUES(founder_update),important_notice=VALUES(important_notice),published_by_user_id=IF(VALUES(publication_status) IN ("private_preview","published"),VALUES(updated_by_user_id),published_by_user_id),published_at=IF(VALUES(publication_status) IN ("private_preview","published"),COALESCE(published_at,NOW()),published_at),updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()');
    $stmt->execute([(int)$round['id'],$status,mg_investment_json_encode($sections),$founder?:null,$notice?:null,$status,$actorId,$status,$actorId]);
    mg_audit('investment_round_publication_saved','investment_round',['round_id'=>$round['public_id'],'publication_status'=>$status,'sections'=>$sections],$actorId);
    return mg_investment_publication_preview($pdo,(string)$round['public_id']);
}

function mg_investment_publication_preview(PDO $pdo,string $roundPublicId): array
{
    $round=mg_investment_pipeline_round($pdo,$roundPublicId);$publication=mg_investment_publication_get($pdo,(int)$round['id']);$snapshot=mg_investment_json($round['snapshot_json']);
    $metrics=$pdo->prepare('SELECT public_id,metric_key,name,description,unit,value_type,confidence,current_value,last_verified_at FROM investment_metrics WHERE workspace_id=? AND investor_visible=1 ORDER BY name');$metrics->execute([(int)$round['workspace_id']]);
    $documents=$pdo->prepare('SELECT public_id,title,document_type,status,external_url,visibility FROM investment_documents WHERE workspace_id=? AND status="published" ORDER BY title');$documents->execute([(int)$round['workspace_id']]);
    $sections=$publication['sections'];
    return ['round'=>['public_id'=>$round['public_id'],'public_name'=>$round['public_name'],'status'=>$round['status'],'visibility'=>$round['visibility'],'instrument_type'=>$round['instrument_type'],'minimum_raise_cents'=>(int)$round['minimum_raise_cents'],'target_raise_cents'=>(int)$round['target_raise_cents'],'maximum_raise_cents'=>(int)$round['maximum_raise_cents'],'valuation_cap_cents'=>(int)$round['valuation_cap_cents'],'discount_bps'=>(int)$round['discount_bps'],'minimum_investment_cents'=>(int)$round['minimum_investment_cents'],'soft_commitment_cents'=>(int)$round['soft_commitment_cents'],'signed_cents'=>(int)$round['signed_cents'],'funded_cents'=>(int)$round['funded_cents'],'opens_at'=>$round['opens_at'],'target_close_at'=>$round['target_close_at'],'counsel_status'=>$round['counsel_status']],
      'publication'=>$publication,
      'company_summary'=>$sections['company_summary']?($snapshot['workspace']['company']??[]):null,
      'use_of_funds'=>$sections['use_of_funds']?array_values(array_filter($snapshot['budgets']??[],static fn($item)=>(int)($item['investor_visible']??0)===1)):[],
      'goals'=>$sections['goals']?array_values(array_filter($snapshot['goals']??[],static fn($item)=>(int)($item['investor_visible']??0)===1)):[],
      'metrics'=>$sections['evidence_metrics']?$metrics->fetchAll(PDO::FETCH_ASSOC):[],
      'documents'=>$sections['documents']?$documents->fetchAll(PDO::FETCH_ASSOC):[]];
}

function mg_investment_table_exists(PDO $pdo,string $table): bool
{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$stmt->execute([$table]);return (int)$stmt->fetchColumn()>0;
}

function mg_investment_column_exists(PDO $pdo,string $table,string $column): bool
{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$stmt->execute([$table,$column]);return (int)$stmt->fetchColumn()>0;
}

function mg_investment_count_status(PDO $pdo,string $table,array $statuses=[]): ?int
{
    if(!mg_investment_table_exists($pdo,$table))return null;
    if($statuses&&mg_investment_column_exists($pdo,$table,'status')){$placeholders=implode(',',array_fill(0,count($statuses),'?'));$stmt=$pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE status IN ({$placeholders})");$stmt->execute($statuses);return (int)$stmt->fetchColumn();}
    return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

function mg_investment_metric_adapter_value(PDO $pdo,string $adapterKey,?array $round=null): ?float
{
    return match($adapterKey){
      'registered_users'=>(float)$pdo->query("SELECT COUNT(*) FROM users WHERE privacy_state NOT IN ('restricted','anonymized')")->fetchColumn(),
      'active_investors'=>(float)$pdo->query("SELECT COUNT(*) FROM investor_profiles WHERE status='active'")->fetchColumn(),
      'active_merchants'=>(float)(mg_investment_count_status($pdo,'merchant_profiles',['active','approved','published'])??mg_investment_count_status($pdo,'merchants',['active','approved','published'])??0),
      'published_products'=>(float)(mg_investment_count_status($pdo,'products',['active','published'])??mg_investment_count_status($pdo,'merchant_products',['active','published'])??0),
      'active_campaigns'=>(float)(mg_investment_count_status($pdo,'campaigns',['active','published','running'])??mg_investment_count_status($pdo,'merchant_campaigns',['active','published','running'])??0),
      'completed_orders'=>(float)(mg_investment_count_status($pdo,'orders',['paid','completed','fulfilled'])??0),
      'funded_round_total'=>(float)$pdo->query('SELECT COALESCE(SUM(funded_cents),0)/100 FROM investment_rounds')->fetchColumn(),
      default=>null,
    };
}

function mg_investment_metric_adapters(PDO $pdo): array
{
    return $pdo->query('SELECT metric_key,label,adapter_key,description,unit,value_type,enabled,updated_at FROM investment_metric_adapters ORDER BY label')->fetchAll(PDO::FETCH_ASSOC)?:[];
}

function mg_investment_metrics_refresh(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.metrics.refresh');$workspace=mg_investment_workspace_by_public_id($pdo,mg_investment_text($input['workspace_id']??'',36,36,'Workspace identifier'));$selected=is_array($input['metric_keys']??null)?$input['metric_keys']:[];$snapshotType=(string)($input['snapshot_type']??'manual');if(!in_array($snapshotType,['round_start','monthly','quarterly','closing','manual'],true))$snapshotType='manual';$round=null;if(!empty($input['round_id']))$round=mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'));
    $adapters=mg_investment_metric_adapters($pdo);$actorId=(int)$actor['id'];$refreshed=[];$pdo->beginTransaction();try{
      foreach($adapters as $adapter){if(!(int)$adapter['enabled']||($selected&&!in_array($adapter['metric_key'],$selected,true)))continue;$value=mg_investment_metric_adapter_value($pdo,(string)$adapter['adapter_key'],$round);if($value===null)continue;$stmt=$pdo->prepare('INSERT INTO investment_metrics (public_id,workspace_id,metric_key,name,description,source_system,calculation_method,unit,value_type,confidence,current_value,investor_visible,refresh_frequency,last_calculated_at,last_verified_at,created_at,updated_at) VALUES (?,?,?,?,?,"microgifter_live_adapter",?,?,?,"system_calculated",?,0,"manual",NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),source_system=VALUES(source_system),calculation_method=VALUES(calculation_method),unit=VALUES(unit),value_type=VALUES(value_type),confidence="system_calculated",current_value=VALUES(current_value),last_calculated_at=NOW(),last_verified_at=NOW(),updated_at=NOW()');$stmt->execute([mg_investment_uuid(),(int)$workspace['id'],$adapter['metric_key'],$adapter['label'],$adapter['description'],'Governed adapter: '.$adapter['adapter_key'],$adapter['unit'],$adapter['value_type'],$value]);$metric=$pdo->prepare('SELECT id,public_id FROM investment_metrics WHERE workspace_id=? AND metric_key=? LIMIT 1');$metric->execute([(int)$workspace['id'],$adapter['metric_key']]);$metricRow=$metric->fetch(PDO::FETCH_ASSOC);$pdo->prepare('INSERT INTO investment_metric_snapshots (metric_id,round_id,snapshot_type,value,confidence,definition_version,source_reference,snapshot_at,created_by_user_id,created_at) VALUES (?,?,?,?,"system_calculated","v2",?,NOW(),?,NOW())')->execute([(int)$metricRow['id'],$round?(int)$round['id']:null,$snapshotType,$value,'adapter:'.$adapter['adapter_key'],$actorId]);$refreshed[]=['metric_key'=>$adapter['metric_key'],'value'=>$value,'unit'=>$adapter['unit']];}
      $pdo->commit();mg_audit('investment_metrics_refreshed','investment_workspace',['workspace_id'=>$workspace['public_id'],'metrics'=>$refreshed,'snapshot_type'=>$snapshotType],$actorId);return ['refreshed'=>$refreshed,'workspace'=>mg_investment_workspace_detail($pdo,(string)$workspace['public_id'])];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_investment_metric_history(PDO $pdo,string $workspacePublicId): array
{
    $workspace=mg_investment_workspace_by_public_id($pdo,$workspacePublicId);$stmt=$pdo->prepare('SELECT m.public_id,m.metric_key,m.name,m.unit,m.current_value,m.confidence,s.snapshot_type,s.value,s.snapshot_at,r.public_name AS round_name FROM investment_metrics m LEFT JOIN investment_metric_snapshots s ON s.metric_id=m.id LEFT JOIN investment_rounds r ON r.id=s.round_id WHERE m.workspace_id=? ORDER BY m.name,s.snapshot_at DESC');$stmt->execute([(int)$workspace['id']]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}

function mg_investment_portal_log(PDO $pdo,int $userId,?int $roundId,string $eventType,?string $subjectPublicId=null,array $metadata=[]): void
{
    if(!in_array($eventType,['portal_open','round_view','document_open','metric_view'],true))return;$stmt=$pdo->prepare('INSERT INTO investment_portal_events (public_id,investor_user_id,round_id,event_type,subject_public_id,metadata_json,created_at) VALUES (?,?,?,?,?,?,NOW())');$stmt->execute([mg_investment_uuid(),$userId,$roundId,$eventType,$subjectPublicId,$metadata?mg_investment_json_encode($metadata):null]);
    mg_investment_pipeline_activity($pdo,$userId,$roundId,$eventType==='portal_open'?'portal_view':($eventType==='document_open'?'document_view':'portal_view'),$eventType==='document_open'?'Investor opened a document':'Investor viewed the portal','',null,$metadata);
}

function mg_investment_pipeline_ai_draft(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.ai');$record=mg_investment_pipeline_record($pdo,mg_investment_text($input['investor_id']??'',36,36,'Investor identifier'));$detail=mg_investment_pipeline_detail($pdo,(string)$record['public_id']);$type=(string)($input['analysis_type']??'follow_up_email');if(!in_array($type,['investor_briefing','follow_up_email','meeting_questions','objection_analysis','stalled_opportunity'],true))throw new MgInvestmentException('Invalid Investor Operations AI action.');
    $model=mg_investment_claude_model($pdo);require_once dirname(__DIR__).'/ai/anthropic-client.php';$system='You are the Microgifter Investor Operations Assistant. Use only the supplied pipeline facts. Create a professional internal draft. Never claim legal approval, accreditation, allocation, investment commitment, signed documents, or funded money unless explicitly present. Do not send anything or change records. Return JSON with keys title, summary, draft, questions, risks, recommended_next_steps.';$response=mg_anthropic_messages(['model'=>$model,'max_tokens'=>1600,'temperature'=>0.25,'system'=>$system,'messages'=>[['role'=>'user','content'=>'Action: '.$type."\nInvestor pipeline snapshot:\n".mg_investment_json_encode($detail)]]]);$text=mg_anthropic_text_from_response($response);$structured=[];try{$structured=mg_anthropic_extract_json_object($text);}catch(Throwable){}mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],null,'ai_draft','Claude '.str_replace('_',' ',$type).' draft',$text,(int)$actor['id'],['model'=>$model,'structured'=>$structured]);mg_audit('investor_pipeline_ai_draft','investor_pipeline',['investor_id'=>$record['public_id'],'analysis_type'=>$type,'model'=>$model],(int)$actor['id']);return ['draft'=>$text,'structured'=>$structured,'detail'=>mg_investment_pipeline_detail($pdo,(string)$record['public_id'])];
}
