<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__,2) . '/includes/loyalty-quest-analytics.php';
require_once dirname(__DIR__,2) . '/includes/loyalty-quest-analytics-accuracy.php';

mg_require_method('GET');
$user=mg_merchant_require_permission('merchant.campaigns.view');
$merchantId=(int)$user['id'];
$pdo=mg_db();
mg_merchant_ensure_workspace($pdo,$user);

try{
    $days=mg_lqa_days($_GET['days']??30);
    $campaignRef=mg_lqa_campaign_ref($_GET['campaign_id']??'');
    $report=mg_lqa_apply_accuracy($pdo,$merchantId,mg_lqa_report($pdo,$merchantId,$days,$campaignRef));
    $optionsStmt=$pdo->prepare("SELECT public_id,title,status,starts_at,ends_at FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest' ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END,updated_at DESC,id DESC LIMIT 100");
    $optionsStmt->execute([$merchantId]);
    $report['campaign_options']=$optionsStmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $report['generated_at']=gmdate('c');
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($report,'Loyalty Quest analytics loaded.');
}catch(InvalidArgumentException $error){
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    mg_fail($error->getMessage(),str_contains($error->getMessage(),'require')?409:404);
}catch(Throwable $error){
    mg_security_log('error','merchant.loyalty_quest_analytics_failed','Unable to load Loyalty Quest analytics.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to load Loyalty Quest analytics.',500);
}
