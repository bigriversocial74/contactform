<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_communications.php';
require_once __DIR__ . '/_delivery.php';
require_once dirname(__DIR__) . '/public/campaigns/_followups.php';
require_once dirname(__DIR__, 2) . '/includes/mail.php';

function mg_followup_tpl(string $value,array $vars): string { foreach($vars as $k=>$v)$value=str_replace('{{'.$k.'}}',(string)$v,$value); return $value; }
function mg_followup_subject(array $r,array $vars): string { $s=trim((string)($r['subject']??'')); if($s==='')$s='A quick follow-up from {{campaign_title}}'; return mb_substr(mg_followup_tpl($s,$vars),0,180); }
function mg_followup_body(array $r,array $vars): string { $b=trim((string)($r['body']??'')); if($b===''){$t=(string)$r['trigger_event'];$b=str_contains($t,'wallet_item')?'Your reward from {{campaign_title}} is waiting in your Microgifter inbox.':'Thanks for connecting with {{campaign_title}}. Your Microgifter reward and updates are waiting in your inbox.';} return mg_followup_tpl($b,$vars); }
function mg_followup_claim(PDO $pdo): ?array
{
 $pdo->beginTransaction();
 $sql="SELECT j.*,r.public_id rule_public_id,r.name rule_name,r.channel,r.message_mode,r.subject,r.body,r.trigger_event rule_trigger,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,cc.public_id contact_public_id,cc.email,cc.name,cc.user_id,wi.public_id wallet_public_id,wi.title_snapshot reward_title FROM campaign_followup_jobs j INNER JOIN campaign_followup_rules r ON r.id=j.rule_id INNER JOIN campaigns c ON c.id=j.campaign_id LEFT JOIN campaign_contacts cc ON cc.id=j.contact_id LEFT JOIN wallet_items wi ON wi.id=j.wallet_item_id WHERE j.status='queued' AND j.due_at<=NOW() AND r.status='active' ORDER BY j.due_at ASC,j.id ASC LIMIT 1 FOR UPDATE";
 $row=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC); if(!$row){$pdo->commit();return null;}
 $pdo->prepare("UPDATE campaign_followup_jobs SET status='processing',attempt_count=attempt_count+1,updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
 $pdo->commit(); return $row;
}
function mg_followup_finish(PDO $pdo,int $jobId,string $status,array $result=[],?string $error=null): void { $pdo->prepare('UPDATE campaign_followup_jobs SET status=?,payload_json=?,last_error=?,sent_at=IF(?="sent",NOW(),sent_at),updated_at=NOW() WHERE id=?')->execute([$status,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$error,$status,$jobId]); }
function mg_followup_run(PDO $pdo,int $limit=25): array
{
 mg_campaign_followup_install($pdo); mg_delivery_install_schema($pdo); $processed=0;$sent=0;$skipped=0;$failed=0;$items=[];
 for($i=0;$i<$limit;$i++){
  $row=mg_followup_claim($pdo); if(!$row)break; $processed++; $jobId=(int)$row['id'];
  try{$email=strtolower(trim((string)($row['email']??'')));$userId=(int)($row['user_id']??0);$channel=(string)$row['channel'];$vars=['name'=>trim((string)($row['name']??''))?:'there','campaign_title'=>(string)$row['campaign_title'],'campaign_type'=>(string)$row['campaign_type'],'reward_title'=>(string)($row['reward_title']??'your reward'),'inbox_url'=>mg_app_base_url().'/inbox.php'];$subject=mg_followup_subject($row,$vars);$text=mg_followup_body($row,$vars);$html=mg_email_layout($subject,'<p style="margin:0 0 14px;color:#334155;font-size:16px;line-height:1.6;">'.nl2br(mg_mail_escape($text)).'</p>'.mg_email_button($vars['inbox_url'],'Open Microgifter'),'Open Microgifter to continue.');$result=['job_id'=>(string)$row['public_id'],'rule_id'=>(string)$row['rule_public_id'],'channel'=>$channel,'trigger_event'=>(string)$row['trigger_event']];
   if(($channel==='email'||$channel==='both')&&filter_var($email,FILTER_VALIDATE_EMAIL)){$result['email_delivery']=mg_delivery_enqueue($pdo,['idempotency_key'=>'campaign-followup:'.$row['public_id'],'event_type'=>'campaign.followup','category'=>'campaign','channel'=>'email','template_key'=>'campaign.followup','recipient_user_id'=>$userId,'recipient_snapshot'=>['email'=>$email,'name'=>(string)($row['name']??'')],'payload'=>['subject'=>$subject,'html'=>$html,'text'=>$text,'campaign_public_id'=>(string)$row['campaign_public_id'],'contact_public_id'=>(string)($row['contact_public_id']??''),'campaign_type'=>(string)$row['campaign_type'],'message_type'=>'campaign_followup','rule_id'=>(string)$row['rule_public_id'],'job_id'=>(string)$row['public_id']],'max_attempts'=>3]);}
   if(($channel==='microgifter_message'||$channel==='both')&&$userId>0){$result['notification_id']=mg_create_notification($pdo,$userId,'campaign_followup',$subject,mb_substr($text,0,500),'/inbox.php',['actor_user_id'=>(int)$row['merchant_user_id'],'event_key'=>'campaign.followup.'.$row['public_id'],'campaign_id'=>(int)$row['campaign_id'],'contact_id'=>(int)($row['contact_id']??0)]);}
   if(empty($result['email_delivery'])&&empty($result['notification_id'])){mg_followup_finish($pdo,$jobId,'skipped',$result,'No reachable channel.');$skipped++;}else{mg_followup_finish($pdo,$jobId,'sent',$result);$sent++;}$items[]=$result;
  }catch(Throwable $e){mg_followup_finish($pdo,$jobId,'failed',['job_id'=>(string)$row['public_id']],$e->getMessage());$failed++;}
 }
 return ['processed'=>$processed,'sent'=>$sent,'skipped'=>$skipped,'failed'=>$failed,'items'=>$items];
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');$user=mg_require_permission('admin.users.view');$pdo=mg_db();
if($method==='GET'){mg_campaign_followup_install($pdo);$counts=$pdo->query("SELECT status,COUNT(*) count FROM campaign_followup_jobs GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);mg_ok(['counts'=>$counts,'schema_ready'=>true]);}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);$limit=max(1,min(100,(int)($input['limit']??25)));$result=mg_followup_run($pdo,$limit);mg_audit('campaign.followup_worker_run','campaign_followups',$result,(int)$user['id']);mg_ok($result,'Campaign follow-up worker complete.');
