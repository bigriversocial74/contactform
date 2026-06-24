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
    $campaignId=(int)$campaign['id'];$contact=null;$contactId=null;
    if($contactRef!==''||$email!==''){
        $q='SELECT * FROM campaign_contacts WHERE merchant_user_id=? AND campaign_id=? AND (' . ($contactRef!==''?'public_id=?':'email=?') . ') LIMIT 1';
        $p=$contactRef!==''?[$merchantId,$campaignId,$contactRef]:[$merchantId,$campaignId,$email];$s=$pdo->prepare($q);$s->execute($p);$contact=$s->fetch(PDO::FETCH_ASSOC);if(!$contact)mg_fail('Contact not found for this campaign.',404);$contactId=(int)$contact['id'];
    }
    $events=[];
    $sql='SELECT ce.*,cc.public_id contact_public_id,wi.public_id wallet_public_id FROM campaign_events ce LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id LEFT JOIN wallet_items wi ON wi.id=ce.wallet_item_id WHERE ce.merchant_user_id=? AND ce.campaign_id=?';
    $params=[$merchantId,$campaignId]; if($contactId){$sql.=' AND ce.contact_id=?';$params[]=$contactId;} $sql.=' ORDER BY ce.created_at ASC,ce.id ASC LIMIT '.$limit;
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$ctx=mg_campaign_timeline_json($r['event_context_json']??null);$events[]=mg_campaign_timeline_event('campaign_event',(string)$r['event_type'],$r['contact_public_id']??null,$r['wallet_public_id']??null,null,null,(string)$r['event_type'],(string)$r['created_at'],$ctx);}
    $wsql='SELECT wi.*,cc.public_id contact_public_id FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.merchant_user_id=? AND wi.campaign_id=?';$wparams=[$merchantId,$campaignId]; if($contactId){$wsql.=' AND wi.contact_id=?';$wparams[]=$contactId;} $wsql.=' ORDER BY wi.created_at ASC LIMIT '.$limit;
    $ws=$pdo->prepare($wsql);$ws->execute($wparams);
    foreach($ws->fetchAll(PDO::FETCH_ASSOC) as $w){$cid=$w['contact_public_id']??null;$wid=(string)$w['public_id'];if(!empty($w['issued_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.issued',$cid,$wid,null,(string)$w['status'],(string)$w['title_snapshot'],(string)$w['issued_at'],['value_cents'=>(int)$w['value_cents_snapshot'],'currency'=>(string)$w['currency_snapshot']]);if(!empty($w['viewed_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.viewed',$cid,$wid,null,(string)$w['status'],'Reward viewed',(string)$w['viewed_at']);if(!empty($w['claimed_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.claimed',$cid,$wid,null,(string)$w['status'],'Reward claimed',(string)$w['claimed_at']);if(!empty($w['redeemed_at']))$events[]=mg_campaign_timeline_event('wallet','wallet_item.redeemed',$cid,$wid,null,(string)$w['status'],'Reward redeemed',(string)$w['redeemed_at']);}
    $esql="SELECT me.public_id event_public_id,me.event_type,me.payload_json,me.created_at event_created_at,mdj.public_id job_public_id,mdj.status job_status,mdj.template_key,mdj.attempt_count,mdj.delivered_at,mdj.failed_at,mdj.created_at job_created_at FROM message_events me LEFT JOIN message_delivery_jobs mdj ON mdj.message_event_id=me.id WHERE me.event_type='campaign.outbound_email' AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.campaign_public_id'))=?";$eparams=[(string)$campaign['public_id']]; if($contact){$esql.=" AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.contact_public_id'))=?";$eparams[]=(string)$contact['public_id'];}$esql.=' ORDER BY me.created_at ASC LIMIT '.$limit;
    $ms=$pdo->prepare($esql);$ms->execute($eparams);
    foreach($ms->fetchAll(PDO::FETCH_ASSOC) as $m){$payload=mg_campaign_timeline_json($m['payload_json']??null);$cid=(string)($payload['contact_public_id']??'')?:null;$events[]=mg_campaign_timeline_event('email_delivery','email.queued',$cid,null,$m['job_public_id']??null,(string)($m['job_status']??'queued'),(string)($payload['subject']??$m['template_key']??'Email queued'),(string)($m['job_created_at']??$m['event_created_at']),['template_key'=>$m['template_key']??null,'message_type'=>$payload['message_type']??null]);if(!empty($m['delivered_at']))$events[]=mg_campaign_timeline_event('email_delivery','email.delivered',$cid,null,$m['job_public_id']??null,'delivered','Email delivered',(string)$m['delivered_at']);if(!empty($m['failed_at']))$events[]=mg_campaign_timeline_event('email_delivery','email.failed',$cid,null,$m['job_public_id']??null,(string)$m['job_status'],'Email failed',(string)$m['failed_at']);}
    mg_campaign_timeline_sort($events); if(count($events)>$limit)$events=array_slice($events,-$limit);
    $summary=['events'=>count($events),'contacts'=>0,'wallets'=>0,'emails'=>0]; foreach($events as $e){if(($e['source']??'')==='campaign_event')$summary['contacts']++; if(($e['source']??'')==='wallet')$summary['wallets']++; if(($e['source']??'')==='email_delivery')$summary['emails']++;}
    mg_ok(['campaign'=>['id'=>(string)$campaign['public_id'],'slug'=>$campaign['public_slug']??null,'title'=>(string)$campaign['title'],'campaign_type'=>(string)$campaign['campaign_type'],'reward_template_title'=>$campaign['reward_template_title']??null],'contact'=>$contact?['id'=>(string)$contact['public_id'],'email'=>(string)$contact['email'],'name'=>$contact['name']??null,'user_id'=>$contact['user_id']? (int)$contact['user_id']:null]:null,'timeline'=>$events,'summary'=>$summary,'schema_ready'=>true]);
}catch(Throwable $error){mg_security_log('warning','merchant.campaign_timeline.failed','Unable to load campaign timeline.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);mg_ok(['campaign'=>null,'contact'=>null,'timeline'=>[],'summary'=>['events'=>0,'contacts'=>0,'wallets'=>0,'emails'=>0],'schema_ready'=>false],'Campaign timeline unavailable until schemas are installed.');}
