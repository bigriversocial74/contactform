<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_campaign_contact_row(array $row): array
{
 return [
  'id'=>(string)$row['public_id'],
  'crm_contact_id'=>(string)($row['crm_contact_id']??''),
  'campaign_id'=>(string)($row['campaign_public_id']??''),
  'campaign_title'=>(string)($row['campaign_title']??''),
  'campaign_type'=>(string)($row['campaign_type']??''),
  'email'=>(string)$row['email'],
  'phone'=>(string)($row['phone']??''),
  'name'=>(string)($row['name'] ?: ($row['crm_display_name'] ?? '')),
  'source'=>(string)$row['source'],
  'opt_in_status'=>(string)$row['opt_in_status'],
  'stage'=>(string)($row['lifecycle_stage'] ?? 'lead'),
  'crm_status'=>(string)($row['crm_status'] ?? 'active'),
  'has_account'=>(int)($row['user_id']??0)>0,
  'email_verified'=>!empty($row['email_verified_at']),
  'total_purchase_cents'=>(int)($row['total_purchase_cents'] ?? 0),
  'wallet_count'=>(int)($row['wallet_count']??0),
  'issued_count'=>(int)($row['issued_count']??0),
  'claimed_count'=>(int)($row['claimed_count']??0),
  'redeemed_count'=>(int)($row['redeemed_count']??0),
  'emails_queued_count'=>(int)($row['emails_queued_count']??0),
  'emails_delivered_count'=>(int)($row['emails_delivered_count']??0),
  'emails_failed_count'=>(int)($row['emails_failed_count']??0),
  'followups_queued_count'=>(int)($row['followups_queued_count']??0),
  'followups_sent_count'=>(int)($row['followups_sent_count']??0),
  'last_seen_at'=>$row['last_seen_at'] ?? null,
  'created_at'=>$row['created_at']??null,
  'updated_at'=>$row['updated_at']??null
 ];
}

mg_require_method('GET');
$user=mg_require_permission('merchant.campaigns.view');$merchantId=(int)$user['id'];$pdo=mg_db();mg_merchant_ensure_workspace($pdo,$user);$campaignPublicId=strtolower(trim((string)($_GET['campaign_id']??$_GET['campaign']??'')));
try{
 $sql="SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,u.email_verified_at,
             crm.public_id crm_contact_id,crm.display_name crm_display_name,crm.lifecycle_stage,crm.crm_status,crm.total_purchase_cents,crm.last_seen_at,
             COUNT(DISTINCT wi.id) wallet_count,
             COUNT(DISTINCT CASE WHEN wi.status='issued' THEN wi.id END) issued_count,
             COUNT(DISTINCT CASE WHEN wi.status='claimed' THEN wi.id END) claimed_count,
             COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redeemed_count,
             COUNT(DISTINCT CASE WHEN ce.event_type='outbound_email.queued' THEN ce.id END) emails_queued_count,
             COUNT(DISTINCT CASE WHEN fj.status='queued' THEN fj.id END) followups_queued_count,
             COUNT(DISTINCT CASE WHEN fj.status='sent' THEN fj.id END) followups_sent_count,
             COUNT(DISTINCT CASE WHEN mdj.status='delivered' THEN mdj.id END) emails_delivered_count,
             COUNT(DISTINCT CASE WHEN mdj.status IN ('failed','dead_letter') THEN mdj.id END) emails_failed_count
      FROM campaign_contacts cc
      INNER JOIN campaigns c ON c.id=cc.campaign_id
      LEFT JOIN users u ON u.id=cc.user_id
      LEFT JOIN merchant_crm_contacts crm ON crm.merchant_user_id=cc.merchant_user_id AND (crm.primary_email=cc.email OR (cc.user_id IS NOT NULL AND crm.user_id=cc.user_id))
      LEFT JOIN wallet_items wi ON wi.contact_id=cc.id
      LEFT JOIN campaign_events ce ON ce.contact_id=cc.id
      LEFT JOIN campaign_followup_jobs fj ON fj.contact_id=cc.id
      LEFT JOIN message_events me ON me.event_type='campaign.outbound_email' AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.contact_public_id'))=cc.public_id
      LEFT JOIN message_delivery_jobs mdj ON mdj.message_event_id=me.id AND mdj.channel='email'
      WHERE cc.merchant_user_id=?";
 $params=[$merchantId];
 if($campaignPublicId!==''){$sql.=' AND (c.public_id=? OR c.public_slug=?)';$params[]=$campaignPublicId;$params[]=$campaignPublicId;}
 $sql.=' GROUP BY cc.id,c.public_id,c.title,c.campaign_type,u.email_verified_at,crm.id ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 250';
 $stmt=$pdo->prepare($sql);$stmt->execute($params);$contacts=array_map('mg_campaign_contact_row',$stmt->fetchAll(PDO::FETCH_ASSOC));
 $totals=['contacts'=>count($contacts),'accounts'=>count(array_filter($contacts,fn($c)=>$c['has_account'])),'verified'=>count(array_filter($contacts,fn($c)=>$c['email_verified'])),'wallets'=>array_sum(array_column($contacts,'wallet_count')),'redeemed'=>array_sum(array_column($contacts,'redeemed_count')),'purchase_cents'=>array_sum(array_column($contacts,'total_purchase_cents')),'emails_queued'=>array_sum(array_column($contacts,'emails_queued_count')),'emails_delivered'=>array_sum(array_column($contacts,'emails_delivered_count')),'emails_failed'=>array_sum(array_column($contacts,'emails_failed_count')),'followups_queued'=>array_sum(array_column($contacts,'followups_queued_count')),'followups_sent'=>array_sum(array_column($contacts,'followups_sent_count'))];
 mg_ok(['contacts'=>$contacts,'totals'=>$totals,'count'=>count($contacts),'schema_ready'=>true]);
}catch(Throwable $error){mg_security_log('warning','merchant.campaign_contacts.unavailable','Campaign contacts unavailable.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);mg_ok(['contacts'=>[],'totals'=>['contacts'=>0,'accounts'=>0,'verified'=>0,'wallets'=>0,'redeemed'=>0,'purchase_cents'=>0,'emails_queued'=>0,'emails_delivered'=>0,'emails_failed'=>0,'followups_queued'=>0,'followups_sent'=>0],'count'=>0,'schema_ready'=>false],'Campaign contacts unavailable until schemas are installed.');}
