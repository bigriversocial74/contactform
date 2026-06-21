<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
function mg_merchant_overview_row(PDO $pdo, string $sql, array $params, array $fallback): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? array_merge($fallback, $row) : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}
function mg_merchant_overview_rows(PDO $pdo, string $sql, array $params): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
$locations = mg_merchant_overview_row($pdo,"SELECT COUNT(*) total,SUM(status='active') active_count,SUM(is_primary=1) primary_count FROM merchant_locations WHERE workspace_id=?",[(int)$workspace['id']],['total'=>0,'active_count'=>0,'primary_count'=>0]);
$team = mg_merchant_overview_row($pdo,"SELECT COUNT(*) total,SUM(status='active') active_count,SUM(status='invited') invited_count FROM merchant_team_members WHERE workspace_id=?",[(int)$workspace['id']],['total'=>0,'active_count'=>0,'invited_count'=>0]);
$payments = mg_merchant_overview_row($pdo,'SELECT mode,provider_key,account_connected,identity_verified,charges_enabled,payouts_enabled,tax_setup_complete,test_payment_complete,live_approved,state_json FROM merchant_payment_readiness WHERE workspace_id=? LIMIT 1',[(int)$workspace['id']],['mode'=>'test','provider_key'=>null,'account_connected'=>0,'identity_verified'=>0,'charges_enabled'=>0,'payouts_enabled'=>0,'tax_setup_complete'=>0,'test_payment_complete'=>0,'live_approved'=>0,'state_json'=>null]);
$paymentState = json_decode((string)($payments['state_json'] ?? ''), true);
if(!is_array($paymentState))$paymentState=[];
$payments['cash_payments_enabled']=!empty($paymentState['payment_methods']['cash']['enabled']);
unset($payments['state_json']);
$productCounts = mg_merchant_overview_row($pdo,"SELECT COUNT(*) total,SUM(status='published') published_count,SUM(status='draft') draft_count,SUM(status='archived') archived_count FROM catalog_products WHERE merchant_user_id=?",[(int)$user['id']],['total'=>0,'published_count'=>0,'draft_count'=>0,'archived_count'=>0]);
if((int)($productCounts['published_count']??0)>0){
 try{
  $pdo->prepare("UPDATE merchant_onboarding_steps SET status='completed',completed_at=COALESCE(completed_at,NOW()),completed_by_user_id=COALESCE(completed_by_user_id,?),updated_at=NOW() WHERE workspace_id=? AND step_key='first_product' AND status<>'completed'")->execute([(int)$user['id'],(int)$workspace['id']]);
  $pdo->prepare("UPDATE merchant_onboarding_steps SET status='available',updated_at=NOW() WHERE workspace_id=? AND step_key='storefront' AND status='locked'")->execute([(int)$workspace['id']]);
  $workspace['onboarding_percent']=mg_merchant_recalculate_onboarding($pdo,(int)$workspace['id']);
 }catch(Throwable $e){}
}
$steps = mg_merchant_overview_rows($pdo,'SELECT step_key,step_order,status,completed_at,state_json FROM merchant_onboarding_steps WHERE workspace_id=? ORDER BY step_order',[(int)$workspace['id']]);
$programs = mg_merchant_overview_row($pdo,"SELECT COUNT(*) total,SUM(status='active') active_count FROM distribution_programs WHERE merchant_user_id=?",[(int)$user['id']],['total'=>0,'active_count'=>0]);
mg_ok(['workspace'=>$workspace,'steps'=>$steps,'locations'=>$locations,'team'=>$team,'payments'=>$payments,'products'=>$productCounts,'programs'=>$programs]);