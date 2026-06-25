<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_delivery.php';

function mg_campaign_timeline_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $raw = (string)$raw;
    if ($raw === '') return [];
    try { $d = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); return is_array($d) ? $d : []; } catch (Throwable) { return []; }
}
function mg_campaign_timeline_event(string $source,string $type,?string $contactId,?string $walletId,?string $jobId,?string $status,?string $title,string $createdAt,array $context=[]): array
{
    return ['source'=>$source,'type'=>$type,'contact_id'=>$contactId,'wallet_item_id'=>$walletId,'delivery_job_id'=>$jobId,'status'=>$status,'title'=>$title ?: $type,'created_at'=>$createdAt,'context'=>$context];
}
function mg_campaign_timeline_sort(array &$events): void
{
    usort($events, fn($a,$b)=>strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')) ?: strcmp((string)($a['type']??''),(string)($b['type']??'')));
}

mg_require_method('GET');
$user=mg_require_permission('merchant.campaigns.view');$merchantId=(int)$user['id'];$pdo=mg_db();mg_merchant_ensure_workspace($pdo,$user);
$campaignRef=strtolower(trim((string)($_GET['campaign']??$_GET['campaign_id']??'')));
$contactRef=strtolower(trim((string)($_GET['contact']??$_GET['contact_id']??'')));
$email=strtolower(trim((string)($_GET['email']??'')));
$limit=max(1,min(500,(int)($_GET['limit']??200)));
if($campaignRef==='')mg_fail('Campaign is required.',422);

try{
    mg_delivery_install_schema($pdo);
    $c=$pdo->prepare('SELECT c.*,rt.public_id reward_template_public_id,rt.title reward_template_title FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.merchant_user_id=? AND (c.public_id=? OR c.public_slug=?) LIMIT 1');
    $c->execute([$merchantId,$campaignRef,$campaignRef]);$campaign=$c->fetch(PDO::FETCH_ASSOC);if(!$campaign)mg_fail('Campaign not found.',404);
    $campaignId=(int)$campaign['id'];$contact=null;$contactId=null;$crmContactId=null;
    if($contactRef!==''||$email!==''){
        $q='SELECT cc.*,crm.id crm_contact_db_id,crm.public_id crm_contact_public_id FROM campaign_contacts cc LEFT JOIN merchant_crm_contacts crm ON crm.merchant_user_id=cc.merchant_user_id AND (crm.primary_email=cc.email OR (cc.user_id IS NOT NULL AND crm.user_id=cc.user_id)) WHERE cc.merchant_user_id=? AND cc.campaign_id=? AND (' . ($contactRef!==''?'cc.public_id=? OR crm.public_id=?':'cc.email=?') . ') LIMIT 1';
        $p=$contactRef!==''?[$merchantId,$campaignId,$contactRef,$contactRef]:[$merchantId,$campaignId,$email];$s=$pdo->prepare($q);$s->execute($p);$contact=$s->fetch(PDO::FETCH_ASSOC);if(!$contact)mg_fail('Contact not found for this campaign.',404);$contactId=(int)$contact['id'];$crmContactId=isset($contact['crm_contact_db_id'])?(int)$contact['crm_contact_db_id']:null;
    }
    $events=[];
    $sql='SELECT ce.*,cc.public_id contact_public_id,wi.public_id wallet_public_id FROM campaign_events ce LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id LEFT JOIN wallet_items wi ON wi.id=ce.wallet_item_id WHERE ce.merchant_user_id=? AND ce.campaign_id=?';
    $params=[$merchantId,$campaignId]; if($contactId){$sql.=' AND ce.contact_id=?';$params[]=$contactId;} $sql.=' ORDER BY ce.created_at ASC,ce.id ASC LIMIT '.$limit;
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$ctx=mg_campaign_timeline_json($r['event_context_json']??null);$events[]=mg_campaign_timeline_event('campaign_event',(string)$r['event_type'],$r['contact_public_id']??null,$r['wallet_public_id']??null,null,null,(string)$r['event_type'],(string)$r['created_at'],$ctx);}
    $wsql='SELECT wi.*,cc.public_id contact_public_id FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.merchant_user_id=? AND wi.campaign_id=?';$wparams=[$merchantId,$campaignId]; if($contactId){$wsql.=' AND wi.contact_id=?';$wparams[]=$contactId;} $wsql.=' ORDER BY wi.created_at ASC LIMIT '.$limit;
    $ws=$pdo->prepare($wsql);$ws->execute($wparams);
    foreach($ws->fetchAll(PDO::FETCH_ASSOC) as $w){$cid=$w['contact_public_id']??null;$wid=(string)$w['public_id'];if(!empty($w['issued_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.issued',$cid,$wid,null,(string)$w['status'],(string)$w['title_snapshot'],(string)$w['issued_at'],['value_cents'=>(int)$w['value_cents_snapshot'],'currency'=>(string)$w['currency_snapshot']]);if(!empty($w['viewed_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.viewed',$cid,$wid,null,(string)$w['status'],'Reward viewed',(string)$w['viewed_at']);if(!empty($w['claimed_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.claimed',$cid,$wid,null,(string)$w['status'],'Reward claimed',(string)$w['claimed_at']);if(!empty($w['redeemed_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.redeemed',$cid,$wid,null,(string)$w['status'],'Reward redeemed',(string)$w['redeemed_at']);}
    $crmSql='SELECT e.*,crm.public_id crm_contact_public_id FROM merchant_crm_contact_events e INNER JOIN merchant_crm_contacts crm ON crm.id=e.crm_contact_id WHERE e.merchant_user_id=? AND e.campaign_id=?';$crmParams=[$merchantId,$campaignId]; if($crmContactId){$crmSql.=' AND e.crm_contact_id=?';$crmParams[]=$crmContactId;} $crmSql.=' ORDER BY e.created_at ASC LIMIT '.$limit;
    $crmStmt=$pdo->prepare($crmSql);$crmStmt->execute($crmParams);
    foreach($crmStmt->fetchAll(PDO::FETCH_ASSOC) as $e){$events[]=mg_campaign_timeline_event('crm_event',(string)$e['event_type'],$contact?(string)$contact['public_id']:null,null,null,null,(string)$e['event_type'],(string)$e['created_at'],['crm_contact_id'=>(string)$e['crm_contact_public_id'],'source_type'=>(string)$e['source_type'],'value_cents'=>$e['value_cents']===null?null:(int)$e['value_cents'],'metadata'=>mg_campaign_timeline_json($e['metadata_json']??null)]);}
    $followSql='SELECT fj.*,fr.public_id rule_public_id,fr.name rule_name,cc.public_id contact_public_id,wi.public_id wallet_public_id FROM campaign_followup_jobs fj INNER JOIN campaign_followup_rules fr ON fr.id=fj.rule_id LEFT JOIN campaign_contacts cc ON cc.id=fj.contact_id LEFT JOIN wallet_items wi ON wi.id=fj.wallet_item_id WHERE fj.merchant_user_id=? AND fj.campaign_id=?';$followParams=[$merchantId,$campaignId]; if($contactId){$followSql.=' AND fj.contact_id=?';$followParams[]=$contactId;} $followSql.=' ORDER BY fj.created_at ASC LIMIT '.$limit;
    $fs=$pdo->prepare($followSql);$fs->execute($followParams);
    foreach($fs->fetchAll(PDO::FETCH_ASSOC) as $f){$events[]=mg_campaign_timeline_event('followup_job','followup.'.$f['status'],$f['contact_public_id']??null,$f['wallet_public_id']??null,(string)$f['public_id'],(string)$f['status'],(string)($f['rule_name'] ?: 'Follow-up'),(string)$f['created_at'],['due_at'=>$f['due_at'],'trigger_event'=>$f['trigger_event'],'rule_id'=>$f['rule_public_id']]);if(!empty($f['sent_at']))$events[]=mg_campaign_timeline_event('followup_job','followup.sent',$f['contact_public_id']??null,$f['wallet_public_id']??null,(string)$f['public_id'],'sent',(string)($f['rule_name'] ?: 'Follow-up sent'),(string)$f['sent_at']);}
    $esql="SELECT me.public_id event_public_id,me.event_type,me.payload_json,me.created_at event_created_at,mdj.public_id job_public_id,mdj.status job_status,mdj.template_key,mdj.attempt_count,mdj.delivered_at,mdj.failed_at,mdj.created_at job_created_at FROM message_events me LEFT JOIN message_delivery_jobs mdj ON mdj.message_event_id=me.id WHERE me.event_type='campaign.outbound_email' AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.campaign_public_id'))=?";$eparams=[(string)$campaign['public_id']]; if($contact){$esql.=" AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.contact_public_id'))=?";$eparams[]=(string)$contact['public_id'];}$esql.=' ORDER BY me.created_at ASC LIMIT '.$limit;
    $ms=$pdo->prepare($esql);$ms->execute($eparams);
    foreach($ms->fetchAll(PDO::FETCH_ASSOC) as $m){$payload=mg_campaign_timeline_json($m['payload_json']??null);$cid=(string)($payload['contact_public_id']??'')?:null;$events[]=mg_campaign_timeline_event('email_delivery','email.queued',$cid,null,$m['job_public_id']??null,(string)($m['job_status']??'queued'),(string)($payload['subject']??$m['template_key']??'Email queued'),(string)($m['job_created_at']??$m['event_created_at']),['template_key'=>$m['template_key']??null,'message_type'=>$payload['message_type']??null]);if(!empty($m['delivered_at']))$events[]=mg_campaign_timeline_event('email_delivery','email.delivered',$cid,null,$m['job_public_id']??null,'delivered','Email delivered',(string)$m['delivered_at']);if(!empty($m['failed_at']))$events[]=mg_campaign_timeline_event('email_delivery','email.failed',$cid,null,$m['job_public_id']??null,(string)$m['job_status'],'Email failed',(string)$m['failed_at']);}
    mg_campaign_timeline_sort($events); if(count($events)>$limit)$events=array_slice($events,-$limit);
    $summary=['events'=>count($events),'campaign'=>0,'crm'=>0,'wallets'=>0,'emails'=>0,'followups'=>0]; foreach($events as $e){if(($e['source']??'')==='campaign_event')$summary['campaign']++; if(($e['source']??'')==='crm_event')$summary['crm']++; if(($e['source']??'')==='wallet')$summary['wallets']++; if(($e['source']??'')==='email_delivery')$summary['emails']++; if(($e['source']??'')==='followup_job')$summary['followups']++;}
    mg_ok(['campaign'=>['id'=>(string)$campaign['public_id'],'slug'=>$campaign['public_slug']??null,'title'=>(string)$campaign['title'],'campaign_type'=>(string)$campaign['campaign_type'],'reward_template_title'=>$campaign['reward_template_title']??null],'contact'=>$contact?['id'=>(string)$contact['public_id'],'crm_contact_id'=>(string)($contact['crm_contact_public_id']??''),'email'=>(string)$contact['email'],'name'=>$contact['name']??null,'user_id'=>$contact['user_id']? (int)$contact['user_id']:null]:null,'timeline'=>$events,'summary'=>$summary,'schema_ready'=>true]);
}catch(Throwable $error){mg_security_log('warning','merchant.campaign_timeline.failed','Unable to load campaign timeline.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);mg_ok(['campaign'=>null,'contact'=>null,'timeline'=>[],'summary'=>['events'=>0,'campaign'=>0,'crm'=>0,'wallets'=>0,'emails'=>0,'followups'=>0],'schema_ready'=>false],'Campaign timeline unavailable until schemas are installed.');}
