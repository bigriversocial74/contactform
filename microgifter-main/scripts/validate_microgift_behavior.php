<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/microgifts/_lifecycle.php';
require_once dirname(__DIR__).'/api/microgifts/_action_center_projection.php';
require_once dirname(__DIR__).'/api/microgifts/_atomic_merchant_redemption.php';
require_once dirname(__DIR__).'/api/messages/_messaging.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

$pdo=mg_db();
$runId='microgift_'.bin2hex(random_bytes(6));
$summary=['suite'=>'microgift_lifecycle_behavior','run_id'=>$runId];

$pdo->beginTransaction();
try{
    $ledgerGroupsBefore=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups');
    $ledgerEntriesBefore=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_entries');

    $merchant=mg_it_user($pdo,$runId.'-merchant@example.test','Behavior Merchant');
    $recipient=mg_it_user($pdo,$runId.'-recipient@example.test','Behavior Recipient');
    $outsider=mg_it_user($pdo,$runId.'-outsider@example.test','Behavior Outsider');

    $template=mg_microgift_create_template($pdo,$merchant,[
        'owner_type'=>'merchant','name'=>'Behavior Gift','gift_type'=>'value','visibility'=>'private','default_currency'=>'USD',
    ]);
    $version=mg_microgift_create_version($pdo,$merchant,(string)$template['template_id'],[
        'title'=>'Behavior Gift','currency'=>'USD','face_value_cents'=>2500,'recipient_policy'=>'open_claim',
        'claim_policy'=>[],'redemption_policy'=>[],'location_policy'=>['mode'=>'unrestricted'],
    ]);
    mg_microgift_publish_version($pdo,$merchant,(string)$version['version_id']);

    $issueInput=[
        'template_version_id'=>$version['version_id'],'source_type'=>'merchant','source_reference'=>'behavior-'.$runId,
        'idempotency_key'=>'behavior:issue:'.$runId,
    ];
    $issue=mg_microgift_issue($pdo,$merchant,$issueInput);
    $instance=mg_microgift_load_instance($pdo,(string)$issue['instance_id']);
    mg_action_center_project_lifecycle($pdo,$instance);
    mg_it_assert($issue['duplicate']===false&&(string)$instance['status']==='issued','Issuance failed.');
    mg_it_assert(mg_microgift_issue($pdo,$merchant,$issueInput)['duplicate']===true,'Issuance replay failed.');
    $summary['issued']=true;

    $pppm=mg_it_pppm($pdo,$merchant,$runId);
    $pdo->prepare('UPDATE microgift_instances SET pppm_item_id=? WHERE id=?')->execute([$pppm['id'],(int)$instance['id']]);
    $sendKey='behavior:send:'.$runId;
    $send=mg_pppm_transfer_owner_canonical($pdo,$pppm['public_id'],$recipient,'action_center_send',$sendKey,$merchant,['microgift_instance_id'=>$instance['public_id']]);
    $pdo->prepare("UPDATE microgift_instances SET issuer_user_id=?,owner_user_id=?,recipient_user_id=?,status='delivered',delivered_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([$merchant,$recipient,$recipient,(int)$instance['id']]);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    $projection=mg_action_center_sent($pdo,(int)$instance['id'],$merchant,$recipient);
    mg_it_assert(!empty($projection['sent_item_id'])&&!empty($projection['recipient_inbox_item_id']),'Send projection failed.');
    mg_it_assert((int)$instance['owner_user_id']===$recipient&&(string)$instance['status']==='delivered','Send state failed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[$pppm['id']])===$recipient,'PPPM owner did not follow send.');
    $sendReplay=mg_pppm_transfer_owner_canonical($pdo,$pppm['public_id'],$recipient,'action_center_send',$sendKey,$merchant,['microgift_instance_id'=>$instance['public_id']]);
    mg_action_center_sent($pdo,(int)$instance['id'],$merchant,$recipient);
    mg_it_assert($sendReplay['duplicate']===true,'Send replay failed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_inbox_items WHERE instance_id=?',[(int)$instance['id']])===2,'Send replay duplicated projections.');
    $summary['sent']=true;$summary['send_replay']=true;$summary['ownership_after_send']=true;

    $messageKey='behavior:message:'.$runId;
    $messageSource='behavior-message-'.$runId;
    $messageBody='Behavior lifecycle message';
    $message=mg_message_send_microgift($pdo,$instance,$merchant,$recipient,$messageBody,$messageKey,$messageSource);
    mg_it_assert($message['duplicate']===false,'Message send failed.');
    $messageReplay=mg_message_send_microgift($pdo,$instance,$merchant,$recipient,$messageBody,$messageKey,$messageSource);
    mg_it_assert($messageReplay['duplicate']===true,'Message replay failed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM messages WHERE sender_user_id=? AND idempotency_key=?',[$merchant,$messageKey])===1,'Message replay duplicated messages.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM message_threads WHERE public_id=?',[$message['thread_id']])===1,'Message thread was not created.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM message_thread_participants p INNER JOIN message_threads t ON t.id=p.thread_id WHERE t.public_id=?',[$message['thread_id']])===2,'Message participants are inconsistent.');
    $notificationId=(int)mg_it_scalar($pdo,'SELECT n.id FROM notifications n INNER JOIN message_threads t ON t.id=n.thread_id WHERE t.public_id=? AND n.user_id=? AND n.type=? LIMIT 1',[$message['thread_id'],$recipient,'message']);
    mg_it_assert($notificationId>0,'Message notification was not created.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM notification_delivery_jobs WHERE notification_id=?',[$notificationId])>=1,'Message delivery jobs were not queued.');

    $pdo->exec('SAVEPOINT unauthorized_message');
    $unauthorizedFailed=false;
    try{
        mg_message_send_microgift($pdo,$instance,$merchant,$outsider,'Unauthorized message','behavior:unauthorized-message:'.$runId,'behavior-unauthorized-'.$runId);
    }catch(Throwable){$unauthorizedFailed=true;}
    mg_it_assert($unauthorizedFailed,'Unauthorized Microgift message did not fail.');
    $pdo->exec('ROLLBACK TO SAVEPOINT unauthorized_message');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM messages WHERE source_reference=?',['behavior-unauthorized-'.$runId])===0,'Unauthorized message left partial state.');
    $summary['messaged']=true;$summary['message_replay']=true;$summary['message_authorization']=true;

    $claimInput=['instance_id'=>$instance['public_id'],'code'=>$issue['credential']['code'],'idempotency_key'=>'behavior:claim:'.$runId];
    $claim=mg_microgift_claim($pdo,$recipient,$claimInput);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    mg_action_center_project_lifecycle($pdo,$instance);
    mg_it_assert($claim['duplicate']===false&&(string)$instance['status']==='redeemable','Claim failed.');
    mg_it_assert(mg_microgift_claim($pdo,$recipient,$claimInput)['duplicate']===true,'Claim replay failed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_claims WHERE instance_id=?',[(int)$instance['id']])===1,'Claim replay duplicated claims.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[$pppm['id']])===$recipient,'PPPM owner did not remain with claimant.');
    $summary['claimed']=true;$summary['claim_replay']=true;$summary['ownership_after_claim']=true;

    $location=mg_it_location($pdo,$merchant,$runId);
    $redeemInput=[
        'instance_id'=>$instance['public_id'],'claimant_user_id'=>$recipient,'merchant_user_id'=>$merchant,
        'location_id'=>$location['public_id'],'claim_code'=>$location['code'],'idempotency_key'=>'behavior:redeem:'.$runId,
        'source_reference'=>'behavior_redemption','correlation_id'=>'behavior-'.$runId,
    ];
    $redemption=mg_microgift_atomic_merchant_redeem($pdo,$merchant,$redeemInput);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    $redemptionDbId=(int)mg_it_scalar($pdo,'SELECT id FROM microgift_redemptions WHERE public_id=?',[$redemption['redemption_id']]);
    mg_action_center_project_lifecycle($pdo,$instance,[
        'redemption_id'=>$redemptionDbId,'merchant_user_id'=>$merchant,'location_id'=>$location['id'],'can_tip'=>1,
    ]);
    mg_it_assert($redemption['duplicate']===false&&(string)$instance['status']==='redeemed','Redemption failed.');
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM pppm_items WHERE id=?',[$pppm['id']])==='redeemed','PPPM redemption failed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[$pppm['id']])===$recipient,'PPPM owner changed during redemption.');
    mg_it_assert(mg_microgift_atomic_merchant_redeem($pdo,$merchant,$redeemInput)['duplicate']===true,'Redemption replay failed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions WHERE instance_id=?',[(int)$instance['id']])===1,'Redemption replay duplicated rows.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_claim_attempts WHERE instance_id=? AND result=?',[(int)$instance['id'],'approved'])===1,'Approved merchant claim attempt is missing or duplicated.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_operational_outbox WHERE public_id=? AND topic=?',[$redemption['outbox_id'],'merchant_claim.completed'])===1,'Redemption outbox event is missing.');
    $summary['redeemed']=true;$summary['redemption_replay']=true;$summary['ownership_after_redemption']=true;$summary['operational_outbox']=true;

    $sender=$pdo->prepare('SELECT folder,state FROM microgift_inbox_items WHERE instance_id=? AND user_id=?');
    $sender->execute([(int)$instance['id'],$merchant]);$senderRow=$sender->fetch(PDO::FETCH_ASSOC);
    $recipientRow=$pdo->prepare('SELECT folder,state,can_tip FROM microgift_inbox_items WHERE instance_id=? AND user_id=?');
    $recipientRow->execute([(int)$instance['id'],$recipient]);$recipientState=$recipientRow->fetch(PDO::FETCH_ASSOC);
    mg_it_assert(($senderRow['folder']??'')==='sent'&&($senderRow['state']??'')==='redeemed','Sender projection is inconsistent.');
    mg_it_assert(($recipientState['folder']??'')==='claimed'&&($recipientState['state']??'')==='redeemed'&&(int)($recipientState['can_tip']??0)===1,'Recipient projection is inconsistent.');
    $summary['action_center_consistent']=true;

    $requiredEvents=[
        'microgift.instance_issued',
        'message.sent',
        'microgift.claim_completed',
        'gift.claim_attempted',
        'gift.claimed',
        'claim.approved',
        'merchant_location.redemption_completed',
        'microgift.redemption_completed',
    ];
    foreach($requiredEvents as $eventType){
        mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_events WHERE instance_id=? AND event_type=?',[(int)$instance['id'],$eventType])===1,'Lifecycle audit event is missing or duplicated: '.$eventType);
    }
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM pppm_item_events WHERE pppm_item_id=? AND event_type=?',[$pppm['id'],'owner_transferred'])>=1,'PPPM ownership audit event is missing.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM pppm_item_events WHERE pppm_item_id=? AND event_type=?',[$pppm['id'],'redeemed'])===1,'PPPM redemption audit event is missing or duplicated.');
    $summary['audit_trail_consistent']=true;

    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups')===$ledgerGroupsBefore,'Merchant-funded Microgift lifecycle created an unexpected ledger transaction group.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_entries')===$ledgerEntriesBefore,'Merchant-funded Microgift lifecycle created unexpected ledger entries.');
    $summary['ledger_neutral_for_merchant_funded']=true;

    $pdo->exec('SAVEPOINT invalid_redeem');
    $invalid=$redeemInput;$invalid['idempotency_key']='behavior:invalid:'.$runId;$invalid['source_reference']='invalid';
    $failed=false;
    try{mg_microgift_atomic_merchant_redeem($pdo,$merchant,$invalid);}catch(Throwable){$failed=true;}
    mg_it_assert($failed,'Invalid post-redemption action did not fail.');
    $pdo->exec('ROLLBACK TO SAVEPOINT invalid_redeem');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions WHERE instance_id=?',[(int)$instance['id']])===1,'Invalid action left partial redemption state.');
    $summary['invalid_transition_rolled_back']=true;

    $self=mg_microgift_issue($pdo,$merchant,[
        'template_version_id'=>$version['version_id'],'source_type'=>'merchant','source_reference'=>'self-'.$runId,
        'idempotency_key'=>'behavior:self:'.$runId,
    ]);
    $selfInstance=mg_microgift_load_instance($pdo,(string)$self['instance_id']);
    mg_action_center_project_lifecycle($pdo,$selfInstance);
    $selfProjection=mg_action_center_sent($pdo,(int)$selfInstance['id'],$merchant,$merchant);
    mg_it_assert($selfProjection['sent_item_id']===null,'Self-owned gift created a Sent row.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_inbox_items WHERE instance_id=?',[(int)$selfInstance['id']])===1,'Self-owned projection duplicated rows.');
    $summary['self_owned_projection']=true;

    $pdo->rollBack();
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?,?)',[$runId.'-merchant@example.test',$runId.'-recipient@example.test',$runId.'-outsider@example.test'])===0,'User fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances WHERE idempotency_key LIKE ?',['behavior:%'.$runId.'%'])===0,'Microgift fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM messages WHERE idempotency_key LIKE ?',['behavior:%'.$runId.'%'])===0,'Message fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
