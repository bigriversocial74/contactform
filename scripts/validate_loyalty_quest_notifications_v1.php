<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$required=[
    'api/communications/_delivery.php',
    'api/communications/_loyalty_quest_notifications.php',
    'api/communications/_loyalty_quest_worker.php',
    'api/communications/loyalty-quest-worker.php',
    'api/merchant/loyalty-quest-invitations.php',
    'api/merchant/loyalty-quest-deliveries.php',
    'merchant-loyalty-quest-delivery.php',
    'includes/merchant-loyalty-quest-delivery-view.php',
    'includes/merchant-navigation.php',
    'assets/css/loyalty-quest-delivery.css',
    'assets/js/loyalty-quest-delivery.js',
    'scripts/run_loyalty_quest_notifications.php',
    'database/loyalty_quest_notifications_transactional_delivery_v1.sql',
    'docs/deployment/loyalty_quest_notifications_v1.md',
    '.github/workflows/loyalty-quest-notifications-validation.yml',
];
$checks=[];
foreach($required as $path)$checks[]=['name'=>'file:'.$path,'ok'=>is_file($root.'/'.$path)];

$delivery=$read('api/communications/_delivery.php');
$service=$read('api/communications/_loyalty_quest_notifications.php');
$worker=$read('api/communications/_loyalty_quest_worker.php');
$workerApi=$read('api/communications/loyalty-quest-worker.php');
$invite=$read('api/merchant/loyalty-quest-invitations.php');
$history=$read('api/merchant/loyalty-quest-deliveries.php');
$start=$read('api/public/loyalty-quest/start.php');
$submit=$read('api/public/loyalty-quest/submit.php');
$reward=$read('api/public/loyalty-quest/_reward.php');
$reviews=$read('api/merchant/loyalty-quest-reviews.php');
$preferences=$read('api/communications/preferences.php');
$cli=$read('scripts/run_loyalty_quest_notifications.php');
$migration=$read('database/loyalty_quest_notifications_transactional_delivery_v1.sql');
$manifest=$read('config/migrations.php');
$page=$read('merchant-loyalty-quest-delivery.php');
$view=$read('includes/merchant-loyalty-quest-delivery-view.php');
$js=$read('assets/js/loyalty-quest-delivery.js');
$css=$read('assets/css/loyalty-quest-delivery.css');
$nav=$read('includes/merchant-navigation.php');
$router=$read('includes/merchant-view.php');
$docs=$read('docs/deployment/loyalty_quest_notifications_v1.md');

$templates=['quest_invitation','participant_joined','merchant_participant_joined','evidence_submitted','merchant_review_required','evidence_approved','evidence_rejected','progress_verified','reward_delivered','quest_expiring','reward_expiring','redemption_receipt','merchant_redemption_receipt'];
$templateCoverage=true;foreach($templates as $template)$templateCoverage=$templateCoverage&&str_contains($service,"'{$template}'");
$checks[]=['name'=>'complete lifecycle templates','ok'=>$templateCoverage];
$checks[]=['name'=>'single in-app projection authority','ok'=>str_contains($service,'function mg_lqn_create_in_app')&&str_contains($service,"'loyalty_quest'")&&str_contains($service,'NULL,?,NULL,?')&&!str_contains($service,'mg_queue_notification_deliveries')];
$checks[]=['name'=>'durable email delivery authority','ok'=>str_contains($service,'mg_delivery_enqueue')&&str_contains($service,"'category'=>'loyalty_quest'")&&str_contains($service,"'max_attempts'=>5")&&str_contains($delivery,"status='retrying'")&&str_contains($delivery,"'dead_letter'")];
$checks[]=['name'=>'preferences digest and quiet hours','ok'=>str_contains($preferences,"'loyalty_quest'")&&str_contains($service,"mg_notification_delivery_time(\$preference, 'email')")&&str_contains($service,'digest_mode')];
$checks[]=['name'=>'external invitation recipients','ok'=>str_contains($delivery,'function mg_delivery_recipient_identity')&&str_contains($delivery,'$externalRecipient')&&str_contains($delivery,"\$channel==='email'")&&str_contains($delivery,'recipient_snapshot')];
$checks[]=['name'=>'delivery payload redaction','ok'=>str_contains($delivery,"str_contains(\$lk,'token')")&&str_contains($delivery,"str_contains(\$lk,'claim_code')")&&str_contains($delivery,"str_contains(\$lk,'password')")&&str_contains($delivery,"'[REDACTED]'")];
$checks[]=['name'=>'merchant invitation authority','ok'=>str_contains($invite,'mg_merchant_require_permission')&&str_contains($invite,'mg_require_csrf_for_write')&&str_contains($invite,'c.merchant_user_id=?')&&str_contains($invite,"c.campaign_type='loyalty_quest'")&&str_contains($invite,"c.status='active'")&&str_contains($invite,'count($contactRefs)>100')];
$checks[]=['name'=>'invitation entitlement and consent','ok'=>str_contains($invite,'email_stamps_enabled')&&str_contains($service,"['opted_out','bounced','complained']")&&str_contains($service,'mg_campaign_email_is_suppressed')&&str_contains($service,'mg_campaign_email_unsubscribe_url')];
$checks[]=['name'=>'quest start hooks','ok'=>str_contains($start,"'participant_joined'")&&str_contains($start,"'merchant_participant_joined'")&&str_contains($start,'_loyalty_quest_notifications.php')];
$checks[]=['name'=>'evidence and progress hooks','ok'=>str_contains($submit,"'evidence_submitted'")&&str_contains($submit,"'merchant_review_required'")&&str_contains($submit,"'progress_verified'")];
$checks[]=['name'=>'review decision hooks','ok'=>str_contains($reviews,"'evidence_rejected'")&&str_contains($reviews,"'evidence_approved'")&&str_contains($reviews,'mg_lqn_notify_participant')];
$checks[]=['name'=>'Inbox reward delivery hook','ok'=>str_contains($reward,"'reward_delivered'")&&str_contains($reward,'pppm_item_id')&&str_contains($service,"'/inbox.php'")];
$checks[]=['name'=>'expiration scheduling','ok'=>str_contains($worker,'INTERVAL 48 HOUR')&&str_contains($worker,'INTERVAL 72 HOUR')&&str_contains($worker,"'quest_expiring'")&&str_contains($worker,"'reward_expiring'")&&str_contains($worker,'NOT EXISTS')];
$checks[]=['name'=>'canonical redemption receipts','ok'=>str_contains($worker,"ce.event_type='wallet_item.redeemed'")&&str_contains($worker,"c.campaign_type='loyalty_quest'")&&str_contains($worker,"'redemption_receipt'")&&str_contains($worker,"'merchant_redemption_receipt'")];
$checks[]=['name'=>'worker concurrency and CLI','ok'=>str_contains($worker,"GET_LOCK('microgifter_loyalty_quest_notifications',0)")&&str_contains($worker,'RELEASE_LOCK')&&str_contains($cli,"PHP_SAPI!=='cli'")&&str_contains($cli,'--limit=')];
$checks[]=['name'=>'admin worker control','ok'=>str_contains($workerApi,"mg_require_permission('admin.users.view')")&&str_contains($workerApi,'mg_require_csrf_for_write')&&str_contains($workerApi,'mg_lqn_worker_run')];
$checks[]=['name'=>'merchant delivery history','ok'=>str_contains($history,'j.merchant_user_id=?')&&str_contains($history,"e.event_type LIKE 'loyalty_quest.%'")&&str_contains($history,'message_delivery_attempts')&&str_contains($history,"'dead_letter'")];
$checks[]=['name'=>'safe merchant retries','ok'=>str_contains($history,'Only failed Loyalty Quest deliveries can be retried')&&str_contains($history,"status='queued'")&&str_contains($history,'mg_require_csrf_for_write')&&str_contains($history,'merchant.loyalty_quest_delivery_retried')];
$checks[]=['name'=>'merchant workspace route','ok'=>str_contains($page,"\$merchantView='quest_delivery'")&&str_contains($nav,"'quest_delivery' => 'loyalty_quests'")&&str_contains($router,'merchant-loyalty-quest-delivery-view.php')];
$checks[]=['name'=>'accessible responsive workspace','ok'=>str_contains($view,'aria-live="polite"')&&str_contains($view,'data-lqd-select-all')&&str_contains($js,'data-lqd-contact-check')&&str_contains($css,'@media(max-width:980px)')&&str_contains($css,':focus-visible')];
$checks[]=['name'=>'migration schema evidence','ok'=>str_contains($migration,'CREATE TABLE IF NOT EXISTS message_delivery_attempts')&&str_contains($migration,'CREATE TABLE IF NOT EXISTS message_provider_callbacks')&&str_contains($migration,'CREATE TABLE IF NOT EXISTS message_suppression_rules')&&str_contains($migration,'merchant_user_id')&&str_contains($migration,'campaign_id')&&str_contains($migration,'source_public_id')];
$checks[]=['name'=>'migration registered','ok'=>str_contains($manifest,"'loyalty_quest_notifications_transactional_delivery_v1.sql'")&&str_contains($migration,"'loyalty_quest_notifications_transactional_delivery_v1'")];
$checks[]=['name'=>'deployment operations documented','ok'=>str_contains($docs,'scripts/run_loyalty_quest_notifications.php --limit=50')&&str_contains($docs,'HostGator cron cadence')&&str_contains($docs,'mail configuration')&&str_contains($docs,'production database migration')];
$checks[]=['name'=>'no sensitive quest evidence in delivery service','ok'=>!str_contains($service,'signed_payload')&&!str_contains($service,'qr_code_token')&&!str_contains($service,'proof_url')&&!str_contains($service,'code_hash')&&!str_contains($service,'latitude')&&!str_contains($service,'longitude')];
$checks[]=['name'=>'PPPM and Inbox remain ownership authority','ok'=>str_contains($reward,'pppm_item_id')&&str_contains($service,'Microgifter Inbox')&&!str_contains($view,'Claim code')&&!str_contains($view,'Wallet item')];

$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));
$score=max(0,10-count($failed)*.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
