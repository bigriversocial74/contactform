<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
 'sql'=>$root.'/database/20260722_creator_campaign_messaging_notifications_v9_single_install.sql',
 'definitions'=>$root.'/includes/creator-campaigns/message-definitions.php',
 'repository'=>$root.'/includes/creator-campaigns/message-repository.php',
 'notification'=>$root.'/includes/creator-campaigns/message-notification.php',
 'service'=>$root.'/includes/creator-campaigns/message-service.php',
 'query'=>$root.'/includes/creator-campaigns/message-query.php',
 'merchant_api'=>$root.'/api/merchant/creator-campaign-messages.php',
 'creator_api'=>$root.'/api/creator/campaign-messages.php',
 'message_send'=>$root.'/api/messages/send.php',
 'message_thread'=>$root.'/api/messages/thread.php',
 'merchant_page'=>$root.'/merchant-creator-messages.php',
 'creator_page'=>$root.'/creator-campaign-messages.php',
 'merchant_js'=>$root.'/assets/js/merchant-creator-campaign-messages.js',
 'creator_js'=>$root.'/assets/js/creator-campaign-messages.js',
 'workflow'=>$root.'/.github/workflows/creator-campaign-messaging-v9.yml',
 'mysql'=>$root.'/scripts/validate_creator_campaign_messaging_v9_mysql.php',
 'phpunit'=>$root.'/tests/phpunit/CreatorCampaignMessagingV9ContractTest.php',
 'docs'=>$root.'/docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE9_MESSAGING.md',
];
$content=[];
foreach($files as $key=>$file){if(!is_file($file)){fwrite(STDERR,"Missing {$file}\n");exit(1);} $content[$key]=file_get_contents($file)?:'';}
$checks=[];
$add=function(string $label,bool $ok)use(&$checks):void{$checks[]=[$label,$ok,4];};
$has=static fn(string $file,string $needle):bool=>str_contains($file,$needle);

$add('Reuses canonical message threads',$has($content['sql'],'REFERENCES message_threads'));
$add('Reuses canonical messages',$has($content['sql'],'REFERENCES messages'));
$add('Does not recreate canonical message tables',!preg_match('/CREATE TABLE IF NOT EXISTS\s+(message_threads|messages|notifications|notification_delivery_jobs)\b/i',$content['sql']));
$add('One context per participant',$has($content['sql'],'uq_cc_message_context_participant'));
$add('Separate merchant-only notes',$has($content['sql'],'creator_campaign_internal_notes'));

$add('Message-level context links',$has($content['sql'],'creator_campaign_message_links'));
$add('Message idempotency uniqueness',$has($content['sql'],'uq_cc_message_link_idempotency'));
$add('Asset references are JSON links',$has($content['sql'],'asset_public_ids_json'));
$add('Optimistic thread lock',$has($content['sql'],'lock_version'));
$add('Internal note idempotency',$has($content['sql'],'uq_cc_internal_note_idempotency'));

$add('Workspace participant ownership',$has($content['repository'],'c.workspace_id=?'));
$add('Creator participant ownership',$has($content['repository'],'p.creator_user_id=?'));
$add('Canonical participant membership',$has($content['repository'],'message_thread_participants') && $has($content['query'],"COALESCE(mc.status,'not_started')"));
$add('CSRF on merchant writes',$has($content['merchant_api'],'mg_require_csrf_for_write'));
$add('CSRF on Creator writes',$has($content['creator_api'],'mg_require_csrf_for_write'));

$add('Existing notification service reused',$has($content['notification'],'mg_create_notification'));
$add('Thread mute respected',$has($content['notification'],'muted_until'));
$add('Creator Campaign delivery type',$has($content['notification'],"'creator_campaign_message'"));
$add('Canonical reply source preserved',$has($content['message_send'],'creator_campaign:') && $has($content['message_send'],'creator_campaign_message_links'));
$add('Messages center source context',$has($content['message_thread'],'creator_campaign_message_contexts') && $has($content['message_send'],"status']!=='open'"));

$add('Merchant workspace page',$has($content['merchant_page'],'mg-app-shell'));
$add('Creator authenticated page',$has($content['creator_page'],'mg_require_auth'));
$add('Merchant UI opens canonical Messages',$has($content['merchant_js'],'/messages.php?thread='));
$add('Clean MySQL lifecycle contract',$has($content['workflow'],'Phase 9 tables') && $has($content['workflow'],'Phase 9 Creator center access') && $has($content['workflow'],'Existing message delivery database bridges'));
$add('No external-send or financial execution boundary',$has($content['docs'],'does not send marketing broadcasts'));

$score=0;
foreach($checks as [$label,$ok,$points]){echo sprintf("[%s] %s (%d)\n",$ok?'PASS':'FAIL',$label,$points);if($ok)$score+=$points;}
echo "Creator Campaign Messaging v9 score: {$score}/100\n";
if($score!==100)exit(1);
