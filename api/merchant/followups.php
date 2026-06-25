<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/public/campaigns/_followups.php';

function mg_followup_rule_row(array $r): array
{
    return ['id'=>(string)$r['public_id'],'campaign_id'=>(string)$r['campaign_public_id'],'campaign_title'=>(string)$r['campaign_title'],'name'=>(string)$r['name'],'trigger_event'=>(string)$r['trigger_event'],'delay_preset'=>(string)$r['delay_preset'],'delay_seconds'=>(int)$r['delay_seconds'],'channel'=>(string)$r['channel'],'message_mode'=>(string)$r['message_mode'],'subject'=>$r['subject'],'status'=>(string)$r['status'],'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at']];
}
function mg_followup_job_row(array $r): array
{
    return ['id'=>(string)$r['public_id'],'rule_id'=>(string)$r['rule_public_id'],'rule_name'=>(string)$r['rule_name'],'campaign_id'=>(string)$r['campaign_public_id'],'campaign_title'=>(string)$r['campaign_title'],'contact_id'=>$r['contact_public_id'] ?? null,'wallet_item_id'=>$r['wallet_public_id'] ?? null,'trigger_event'=>(string)$r['trigger_event'],'status'=>(string)$r['status'],'due_at'=>$r['due_at'],'attempt_count'=>(int)$r['attempt_count'],'last_error'=>$r['last_error'],'sent_at'=>$r['sent_at'],'created_at'=>$r['created_at']];
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=$method==='GET'?mg_require_permission('merchant.campaigns.view'):mg_require_permission('merchant.campaigns.manage');
$merchantId=(int)$user['id'];$pdo=mg_db();mg_merchant_ensure_workspace($pdo,$user);
try{mg_campaign_followup_install($pdo);}catch(Throwable $e){}

if($method==='GET'){
 try{
  $campaign=strtolower(trim((string)($_GET['campaign']??'')));
  $ruleSql="SELECT fr.*,c.public_id campaign_public_id,c.title campaign_title FROM campaign_followup_rules fr INNER JOIN campaigns c ON c.id=fr.campaign_id WHERE fr.merchant_user_id=?";$params=[$merchantId];
  if($campaign!==''){$ruleSql.=' AND (c.public_id=? OR c.public_slug=?)';$params[]=$campaign;$params[]=$campaign;}
  $ruleSql.=' ORDER BY fr.updated_at DESC LIMIT 100';$rs=$pdo->prepare($ruleSql);$rs->execute($params);
  $jobSql="SELECT fj.*,fr.public_id rule_public_id,fr.name rule_name,c.public_id campaign_public_id,c.title campaign_title,cc.public_id contact_public_id,wi.public_id wallet_public_id FROM campaign_followup_jobs fj INNER JOIN campaign_followup_rules fr ON fr.id=fj.rule_id INNER JOIN campaigns c ON c.id=fj.campaign_id LEFT JOIN campaign_contacts cc ON cc.id=fj.contact_id LEFT JOIN wallet_items wi ON wi.id=fj.wallet_item_id WHERE fj.merchant_user_id=?";$jparams=[$merchantId];
  if($campaign!==''){$jobSql.=' AND (c.public_id=? OR c.public_slug=?)';$jparams[]=$campaign;$jparams[]=$campaign;}
  $jobSql.=' ORDER BY fj.due_at DESC,fj.id DESC LIMIT 150';$js=$pdo->prepare($jobSql);$js->execute($jparams);
  $jobs=array_map('mg_followup_job_row',$js->fetchAll(PDO::FETCH_ASSOC));
  $totals=['rules'=>0,'queued'=>0,'sent'=>0,'failed'=>0,'skipped'=>0];
  foreach($jobs as $j){if(isset($totals[$j['status']]))$totals[$j['status']]++;}
  $rules=array_map('mg_followup_rule_row',$rs->fetchAll(PDO::FETCH_ASSOC));$totals['rules']=count($rules);
  mg_ok(['rules'=>$rules,'jobs'=>$jobs,'totals'=>$totals,'schema_ready'=>true]);
 }catch(Throwable $e){mg_security_log('warning','merchant.followups.unavailable','Follow-ups unavailable.',['exception_class'=>$e::class,'message'=>$e->getMessage()],$merchantId);mg_ok(['rules'=>[],'jobs'=>[],'totals'=>['rules'=>0,'queued'=>0,'sent'=>0,'failed'=>0,'skipped'=>0],'schema_ready'=>false],'Follow-ups unavailable until schema is installed.');}
}
if($method==='POST'){
 $input=mg_input();mg_require_csrf_for_write($input);
 $campaignRef=strtolower(trim((string)($input['campaign_id']??$input['campaign']??'')));$name=trim((string)($input['name']??''));$trigger=trim((string)($input['trigger_event']??'form.submitted'));$delay=trim((string)($input['delay_preset']??'1_hour'));$custom=max(0,(int)($input['custom_delay_minutes']??0));$channel=trim((string)($input['channel']??'email'));$mode=trim((string)($input['message_mode']??'automatic'));$subject=trim((string)($input['subject']??''))?:null;$body=trim((string)($input['body']??''))?:null;
 if($campaignRef===''||$name===''||mb_strlen($name)>160||!in_array($delay,['1_hour','6_hours','1_day','15_days','custom'],true)||!in_array($channel,['email','microgifter_message','both'],true)||!in_array($mode,['automatic','custom'],true))mg_fail('Invalid follow-up rule.',422);
 $c=$pdo->prepare('SELECT id,public_id FROM campaigns WHERE merchant_user_id=? AND (public_id=? OR public_slug=?) LIMIT 1');$c->execute([$merchantId,$campaignRef,$campaignRef]);$campaign=$c->fetch(PDO::FETCH_ASSOC);if(!$campaign)mg_fail('Campaign not found.',404);
 $delaySeconds=mg_campaign_followup_delay($delay,$custom);$publicId=mg_campaign_followup_uuid();
 $stmt=$pdo->prepare("INSERT INTO campaign_followup_rules (public_id,merchant_user_id,campaign_id,name,trigger_event,delay_preset,custom_delay_minutes,delay_seconds,channel,message_mode,subject,body,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'active', ?,NOW(),NOW())");
 $stmt->execute([$publicId,$merchantId,(int)$campaign['id'],$name,$trigger,$delay,$custom?:null,$delaySeconds,$channel,$mode,$subject,$body,json_encode(['created_from'=>'merchant_followups_api'],JSON_UNESCAPED_SLASHES)]);
 mg_audit('merchant.followup_rule_created','campaign_followup_rule',['rule_id'=>$publicId,'campaign_id'=>(string)$campaign['public_id'],'trigger_event'=>$trigger],$merchantId);
 mg_ok(['rule_id'=>$publicId,'campaign_id'=>(string)$campaign['public_id'],'delay_seconds'=>$delaySeconds],'Follow-up rule created.',201);
}
mg_fail('Method not allowed.',405);
