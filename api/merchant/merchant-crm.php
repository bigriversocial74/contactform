<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
try {
    $stmt = $pdo->prepare('SELECT public_id,display_name,primary_email,primary_phone,lifecycle_stage,crm_status,last_campaign_type,last_source_type,last_seen_at,last_engaged_at,last_purchased_at,last_reward_issued_at,last_reward_claimed_at,last_reward_redeemed_at,total_purchase_cents,total_rewards_issued,total_rewards_claimed,total_rewards_redeemed FROM merchant_crm_contacts WHERE merchant_user_id=? ORDER BY last_seen_at DESC,id DESC LIMIT 150');
    $stmt->execute([$merchantId]);
    $contacts = array_map(static fn(array $r): array => [
        'id'=>(string)$r['public_id'],
        'display_name'=>$r['display_name'] ?: 'Unnamed contact',
        'email'=>$r['primary_email'],
        'phone'=>$r['primary_phone'],
        'stage'=>(string)$r['lifecycle_stage'],
        'status'=>(string)$r['crm_status'],
        'campaign_type'=>(string)$r['last_campaign_type'],
        'source_type'=>(string)$r['last_source_type'],
        'last_seen_at'=>$r['last_seen_at'],
        'last_engaged_at'=>$r['last_engaged_at'],
        'last_purchased_at'=>$r['last_purchased_at'],
        'last_reward_issued_at'=>$r['last_reward_issued_at'],
        'last_reward_claimed_at'=>$r['last_reward_claimed_at'],
        'last_reward_redeemed_at'=>$r['last_reward_redeemed_at'],
        'total_purchase_cents'=>(int)$r['total_purchase_cents'],
        'total_rewards_issued'=>(int)$r['total_rewards_issued'],
        'total_rewards_claimed'=>(int)$r['total_rewards_claimed'],
        'total_rewards_redeemed'=>(int)$r['total_rewards_redeemed'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    $totalsStmt = $pdo->prepare("SELECT COUNT(*) total_contacts,SUM(last_source_type IN ('newsletter_signup','contest_entry','qr_scan','referral','birthday_vip','agent_discovery')) campaign_contacts,SUM(lifecycle_stage='customer') purchasing_customers,SUM(lifecycle_stage='follower') followers,SUM(lifecycle_stage='redeemer') redeemers,COALESCE(SUM(total_purchase_cents),0) purchase_cents,COALESCE(SUM(total_rewards_issued),0) rewards_issued,COALESCE(SUM(total_rewards_claimed),0) rewards_claimed,COALESCE(SUM(total_rewards_redeemed),0) rewards_redeemed FROM merchant_crm_contacts WHERE merchant_user_id=?");
    $totalsStmt->execute([$merchantId]);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $segmentsStmt = $pdo->prepare("SELECT lifecycle_stage segment,COUNT(*) contacts,COALESCE(SUM(total_purchase_cents),0) purchase_cents,COALESCE(SUM(total_rewards_redeemed),0) rewards_redeemed FROM merchant_crm_contacts WHERE merchant_user_id=? GROUP BY lifecycle_stage ORDER BY contacts DESC");
    $segmentsStmt->execute([$merchantId]);
    $sourceStmt = $pdo->prepare("SELECT last_source_type source_type,COUNT(*) contacts FROM merchant_crm_contacts WHERE merchant_user_id=? GROUP BY last_source_type ORDER BY contacts DESC");
    $sourceStmt->execute([$merchantId]);
    $eventStmt = $pdo->prepare('SELECT public_id,event_type,source_type,campaign_type,source_public_id,value_cents,created_at FROM merchant_crm_contact_events WHERE merchant_user_id=? ORDER BY created_at DESC,id DESC LIMIT 100');
    $eventStmt->execute([$merchantId]);
    $followStmt = $pdo->prepare("SELECT status,COUNT(*) count FROM campaign_followup_jobs WHERE merchant_user_id=? GROUP BY status");
    try { $followStmt->execute([$merchantId]); $followups = $followStmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) { $followups = []; }
    mg_ok(['schema_ready'=>true,'contacts'=>$contacts,'segments'=>$segmentsStmt->fetchAll(PDO::FETCH_ASSOC),'sources'=>$sourceStmt->fetchAll(PDO::FETCH_ASSOC),'recent_events'=>$eventStmt->fetchAll(PDO::FETCH_ASSOC),'followups'=>$followups,'totals'=>[
        'total_contacts'=>(int)($totals['total_contacts'] ?? 0),
        'campaign_contacts'=>(int)($totals['campaign_contacts'] ?? 0),
        'purchasing_customers'=>(int)($totals['purchasing_customers'] ?? 0),
        'followers'=>(int)($totals['followers'] ?? 0),
        'redeemers'=>(int)($totals['redeemers'] ?? 0),
        'purchase_cents'=>(int)($totals['purchase_cents'] ?? 0),
        'rewards_issued'=>(int)($totals['rewards_issued'] ?? 0),
        'rewards_claimed'=>(int)($totals['rewards_claimed'] ?? 0),
        'rewards_redeemed'=>(int)($totals['rewards_redeemed'] ?? 0),
    ]]);
} catch (Throwable $error) {
    mg_security_log('warning','merchant.crm.unavailable','Merchant CRM unavailable.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);
    mg_ok(['schema_ready'=>false,'contacts'=>[],'segments'=>[],'sources'=>[],'recent_events'=>[],'followups'=>[],'totals'=>['total_contacts'=>0,'campaign_contacts'=>0,'purchasing_customers'=>0,'followers'=>0,'redeemers'=>0,'purchase_cents'=>0,'rewards_issued'=>0,'rewards_claimed'=>0,'rewards_redeemed'=>0]],'Merchant CRM schema is not installed yet.');
}
