<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
try {
    $stmt = $pdo->prepare('SELECT public_id,display_name,primary_email,primary_phone,lifecycle_stage,crm_status,last_campaign_type,last_source_type,last_seen_at,last_engaged_at,total_purchase_cents,total_rewards_issued,total_rewards_claimed,total_rewards_redeemed FROM merchant_crm_contacts WHERE merchant_user_id=? ORDER BY last_seen_at DESC,id DESC LIMIT 75');
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
        'total_purchase_cents'=>(int)$r['total_purchase_cents'],
        'total_rewards_issued'=>(int)$r['total_rewards_issued'],
        'total_rewards_claimed'=>(int)$r['total_rewards_claimed'],
        'total_rewards_redeemed'=>(int)$r['total_rewards_redeemed'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    $totalsStmt = $pdo->prepare("SELECT COUNT(*) total_contacts,SUM(last_source_type IN ('newsletter_signup','contest_entry','qr_scan','referral','birthday_vip','agent_discovery')) campaign_contacts,SUM(lifecycle_stage='customer') purchasing_customers,SUM(lifecycle_stage='follower') followers FROM merchant_crm_contacts WHERE merchant_user_id=?");
    $totalsStmt->execute([$merchantId]);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    mg_ok(['schema_ready'=>true,'contacts'=>$contacts,'totals'=>[
        'total_contacts'=>(int)($totals['total_contacts'] ?? 0),
        'campaign_contacts'=>(int)($totals['campaign_contacts'] ?? 0),
        'purchasing_customers'=>(int)($totals['purchasing_customers'] ?? 0),
        'followers'=>(int)($totals['followers'] ?? 0),
    ]]);
} catch (Throwable $error) {
    mg_security_log('warning','merchant.crm.unavailable','Merchant CRM unavailable.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);
    mg_ok(['schema_ready'=>false,'contacts'=>[],'totals'=>['total_contacts'=>0,'campaign_contacts'=>0,'purchasing_customers'=>0,'followers'=>0]],'Merchant CRM schema is not installed yet.');
}
