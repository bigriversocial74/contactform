<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-directory.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-creator-campaign-bridge.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$query = (string)($_GET['q'] ?? $_GET['search'] ?? '');
$limit = (int)($_GET['limit'] ?? 250);
$offset = (int)($_GET['offset'] ?? 0);

try {
    $directory = mg_merchant_crm_directory_list($pdo, $merchantId, $query, $limit, $offset);
    $totalsStmt = $pdo->prepare("SELECT COUNT(*) total_contacts,SUM(last_source_type IN ('newsletter_signup','contest_entry','qr_scan','referral','birthday_vip','agent_discovery')) campaign_contacts,SUM(lifecycle_stage='customer') purchasing_customers,SUM(lifecycle_stage='follower') followers,SUM(crm_status='active') active_contacts,SUM(total_rewards_redeemed>0) redeemers FROM merchant_crm_contacts WHERE merchant_user_id=?" . (mg_merchant_crm_search_column_exists($pdo, 'merchant_crm_contacts', 'merged_into_contact_id') ? ' AND merged_into_contact_id IS NULL' : ''));
    $totalsStmt->execute([$merchantId]);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $directory['totals'] = [
        'total_contacts'=>(int)($totals['total_contacts'] ?? 0),
        'campaign_contacts'=>(int)($totals['campaign_contacts'] ?? 0),
        'purchasing_customers'=>(int)($totals['purchasing_customers'] ?? 0),
        'followers'=>(int)($totals['followers'] ?? 0),
        'active_contacts'=>(int)($totals['active_contacts'] ?? 0),
        'redeemers'=>(int)($totals['redeemers'] ?? 0),
    ];
    $directory = mg_merchant_crm_creator_campaign_enrich_directory($pdo,$merchantId,$directory,$query);
    mg_ok($directory);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm.unavailable', 'Merchant CRM unavailable.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
    mg_ok([
        'schema_ready'=>false,
        'contract_version'=>MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION,
        'query'=>mg_merchant_crm_search_query($query),
        'contacts'=>[],
        'total'=>0,
        'limit'=>max(1, min(250, $limit)),
        'offset'=>max(0, $offset),
        'has_more'=>false,
        'next_offset'=>null,
        'totals'=>['total_contacts'=>0,'campaign_contacts'=>0,'purchasing_customers'=>0,'followers'=>0,'active_contacts'=>0,'redeemers'=>0],
    ], 'Merchant CRM schema is not installed yet.');
}
