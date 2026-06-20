<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/microgifts/_lifecycle.php';
require_once dirname(__DIR__).'/api/microgifts/_action_center_projection.php';
require_once dirname(__DIR__).'/api/microgifts/_delivery.php';
require_once dirname(__DIR__).'/api/pppm/_ownership.php';
require_once dirname(__DIR__).'/api/messages/_messaging.php';
require_once dirname(__DIR__).'/api/communications/_communications.php';
require_once dirname(__DIR__).'/api/account/_action_center.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_stage_d_transfer(
    PDO $pdo,
    array $instance,
    string $pppmPublicId,
    int $senderUserId,
    int $recipientUserId,
    string $key
): array {
    if((int)$instance['owner_user_id']!==$senderUserId){
        throw new RuntimeException('Transfer actor does not own the Microgift.');
    }
    $transfer=mg_pppm_transfer_owner_canonical(
        $pdo,$pppmPublicId,$recipientUserId,'stage_d_regift',$key,$senderUserId,
        ['microgift_instance_id'=>(string)$instance['public_id']]
    );
    $pdo->prepare("UPDATE microgift_instances
        SET owner_user_id=?,recipient_user_id=?,status='delivered',delivered_at=COALESCE(delivered_at,NOW()),updated_at=NOW()
        WHERE id=?")
        ->execute([$recipientUserId,$recipientUserId,(int)$instance['id']]);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    $delivery=mg_microgift_delivery_event(
        $pdo,$instance,'sent',$senderUserId,$recipientUserId,$key,null,
        ['transfer_id'=>$transfer['transfer_id']??null,'transfer_type'=>'regift']
    );
    $projection=mg_action_center_sent(
        $pdo,(int)$instance['id'],$senderUserId,$recipientUserId,
        ['sent_at'=>$delivery['occurred_at'],'received_at'=>$delivery['occurred_at'],'occurred_at'=>$delivery['occurred_at']]
    );
    $notification=mg_create_notification(
        $pdo,$recipientUserId,'microgift_received','A Microgift was regifted to you',
        (string)$instance['title_snapshot'],
        '/inbox.php?item='.rawurlencode((string)$projection['recipient_inbox_item_id']),
        [
            'actor_user_id'=>$senderUserId,
            'event_key'=>'stage_d_received:'.(string)$delivery['event_id'],
            'pppm_item_id'=>(int)$instance['pppm_item_id'],
            'microgift_instance_id'=>(string)$instance['public_id'],
        ]
    );
    return compact('instance','transfer','delivery','projection','notification');
}

function mg_stage_d_sent_item(PDO $pdo,int $userId,int $instanceId): array
{
    $stmt=$pdo->prepare("SELECT public_id FROM microgift_inbox_items WHERE user_id=? AND instance_id=? AND folder='sent' LIMIT 1");
    $stmt->execute([$userId,$instanceId]);
    $publicId=(string)($stmt->fetchColumn()?:'');
    if($publicId==='')throw new RuntimeException('Expected Sent item was not found.');
    $item=mg_action_center_detail($pdo,$userId,$publicId);
    if(!$item)throw new RuntimeException('Sent item detail was not available.');
    return $item;
}

$pdo=mg_db();
$runId='stage_d_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'stage_d_regift_follow_up_behavior',
    'run_id'=>$runId,
    'original_issuer_preserved'=>false,
    'pppm_owner_recipient_aligned'=>false,
    'three_transfers_completed'=>false,
    'recipient_notifications_created'=>false,
    'latest_sender_follow_up_only'=>false,
    'transfer_conversations_isolated'=>false,
    'reverse_reply_uses_same_thread'=>false,
    'follow_up_replay_safe'=>false,
    'action_center_folders_consistent'=>false,
    'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $merchantEmail=$runId.'-merchant@example.test';
    $sarahEmail=$runId.'-sarah@example.test';
    $markEmail=$runId.'-mark@example.test';
    $daveEmail=$runId.'-dave@example.test';
    $merchant=mg_it_user($pdo,$merchantEmail,'Stage D Merchant');
    $sarah=mg_it_user($pdo,$sarahEmail,'Sarah');
    $mark=mg_it_user($pdo,$markEmail,'Mark');
    $dave=mg_it_user($pdo,$daveEmail,'Dave');

    $template=mg_microgift_create_template($pdo,$merchant,[
        'owner_type'=>'merchant','name'=>'Stage D Regift','gift_type'=>'value','visibility'=>'private','default_currency'=>'USD',
    ]);
    $version=mg_microgift_create_version($pdo,$merchant,(string)$template['template_id'],[
        'title'=>'Stage D Regift','currency'=>'USD','face_value_cents'=>2500,'recipient_policy'=>'open_claim',
        'claim_policy'=>[],'redemption_policy'=>[],'location_policy'=>['mode'=>'unrestricted'],
    ]);
    mg_microgift_publish_version($pdo,$merchant,(string)$version['version_id']);
    $issue=mg_microgift_issue($pdo,$merchant,[
        'template_version_id'=>$version['version_id'],
        'source_type'=>'merchant',
        'source_reference'=>'stage-d-'.$runId,
        'idempotency_key'=>'stage-d:issue:'.$runId,
    ]);
    $instance=mg_microgift_load_instance($pdo,(string)$issue['instance_id']);
    $pppm=mg_it_pppm($pdo,$merchant,$runId);
    $pdo->prepare('UPDATE microgift_instances SET pppm_item_id=? WHERE id=?')->execute([$pppm['id'],(int)$instance['id']]);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    mg_action_center_receive($pdo,(int)$instance['id'],$merchant,$merchant);

    $toSarah=mg_stage_d_transfer($pdo,$instance,$pppm['public_id'],$merchant,$sarah,'stage-d:merchant-sarah:'.$runId);
    $instance=$toSarah['instance'];
    $toMark=mg_stage_d_transfer($pdo,$instance,$pppm['public_id'],$sarah,$mark,'stage-d:sarah-mark:'.$runId);
    $instance=$toMark['instance'];

    $sarahMarkKey=mg_message_conversation_key($pdo,$instance,$sarah,$mark);
    $sarahFollow=mg_message_send_microgift(
        $pdo,$instance,$sarah,$mark,'Hope you enjoy it.','stage-d:follow-sarah-mark:'.$runId,
        (string)$toMark['projection']['sent_item_id'],[$sarah,$mark],$sarahMarkKey,'follow_up',true
    );

    $toDave=mg_stage_d_transfer($pdo,$instance,$pppm['public_id'],$mark,$dave,'stage-d:mark-dave:'.$runId);
    $instance=$toDave['instance'];
    $markDaveKey=mg_message_conversation_key($pdo,$instance,$mark,$dave);
    $markFollow=mg_message_send_microgift(
        $pdo,$instance,$mark,$dave,'Checking that the gift arrived.','stage-d:follow-mark-dave:'.$runId,
        (string)$toDave['projection']['sent_item_id'],[$mark,$dave],$markDaveKey,'follow_up',true
    );
    $markFollowReplay=mg_message_send_microgift(
        $pdo,$instance,$mark,$dave,'Checking that the gift arrived.','stage-d:follow-mark-dave:'.$runId,
        (string)$toDave['projection']['sent_item_id'],[$mark,$dave],$markDaveKey,'follow_up',true
    );
    $daveReplyKey=mg_message_conversation_key($pdo,$instance,$dave,$mark);
    $daveReply=mg_message_send_microgift(
        $pdo,$instance,$dave,$mark,'It arrived—thank you.','stage-d:reply-dave-mark:'.$runId,
        (string)$toDave['projection']['recipient_inbox_item_id'],[$dave,$mark],$daveReplyKey,'message',true
    );

    mg_it_assert((int)$instance['issuer_user_id']===$merchant,'Regift changed the original merchant issuer.');
    mg_it_assert((int)$instance['owner_user_id']===$dave&&(int)$instance['recipient_user_id']===$dave,'Final Microgift ownership is wrong.');
    $summary['original_issuer_preserved']=true;

    $pppmRow=$pdo->prepare('SELECT issuer_user_id,merchant_user_id,owner_user_id,recipient_user_id FROM pppm_items WHERE id=?');
    $pppmRow->execute([$pppm['id']]);
    $pppmState=$pppmRow->fetch(PDO::FETCH_ASSOC);
    mg_it_assert((int)$pppmState['issuer_user_id']===$merchant&&(int)$pppmState['merchant_user_id']===$merchant,'PPPM issuer authority changed.');
    mg_it_assert((int)$pppmState['owner_user_id']===$dave&&(int)$pppmState['recipient_user_id']===$dave,'PPPM owner and recipient are not aligned.');
    $summary['pppm_owner_recipient_aligned']=true;

    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM microgift_delivery_events WHERE instance_id=? AND event_type='sent'",[(int)$instance['id']])===3,'Expected three immutable transfer events.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM pppm_item_events WHERE pppm_item_id=? AND event_type=?',[$pppm['id'],'owner_transferred'])===3,'Expected three PPPM owner transfers.');
    $summary['three_transfers_completed']=true;

    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE type='microgift_received' AND user_id IN (?,?,?)",[$sarah,$mark,$dave])===3,'Recipient notifications were not created once per transfer.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE type='microgift_follow_up' AND user_id IN (?,?)",[$mark,$dave])===2,'Follow Up notifications were not created once.');
    $summary['recipient_notifications_created']=true;

    $merchantSent=mg_stage_d_sent_item($pdo,$merchant,(int)$instance['id']);
    $sarahSent=mg_stage_d_sent_item($pdo,$sarah,(int)$instance['id']);
    $markSent=mg_stage_d_sent_item($pdo,$mark,(int)$instance['id']);
    mg_it_assert($merchantSent['can_follow_up']===false,'Original merchant remained eligible after later transfers.');
    mg_it_assert($sarahSent['can_follow_up']===false,'Sarah remained eligible after Mark regifted the Microgift.');
    mg_it_assert($markSent['can_follow_up']===true,'Mark is not recognized as the latest sender to Dave.');
    $summary['latest_sender_follow_up_only']=true;

    mg_it_assert($sarahMarkKey!==$markDaveKey,'Separate transfers reused one conversation key.');
    mg_it_assert($sarahFollow['thread_id']!==$markFollow['thread_id'],'Separate transfers reused one message thread.');
    $sarahThreadParticipants=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM message_thread_participants p INNER JOIN message_threads t ON t.id=p.thread_id WHERE t.public_id=? AND p.user_id IN (?,?)',[$sarahFollow['thread_id'],$sarah,$mark]);
    $sarahThreadDave=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM message_thread_participants p INNER JOIN message_threads t ON t.id=p.thread_id WHERE t.public_id=? AND p.user_id=?',[$sarahFollow['thread_id'],$dave]);
    $markThreadParticipants=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM message_thread_participants p INNER JOIN message_threads t ON t.id=p.thread_id WHERE t.public_id=? AND p.user_id IN (?,?)',[$markFollow['thread_id'],$mark,$dave]);
    $markThreadSarah=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM message_thread_participants p INNER JOIN message_threads t ON t.id=p.thread_id WHERE t.public_id=? AND p.user_id=?',[$markFollow['thread_id'],$sarah]);
    mg_it_assert($sarahThreadParticipants===2&&$sarahThreadDave===0,'Sarah-to-Mark conversation leaked to Dave.');
    mg_it_assert($markThreadParticipants===2&&$markThreadSarah===0,'Mark-to-Dave conversation leaked to Sarah.');
    $summary['transfer_conversations_isolated']=true;

    mg_it_assert($daveReplyKey===$markDaveKey,'Reverse reply did not resolve the active transfer conversation.');
    mg_it_assert($daveReply['thread_id']===$markFollow['thread_id'],'Dave reply created a second transfer thread.');
    $summary['reverse_reply_uses_same_thread']=true;

    mg_it_assert($markFollowReplay['duplicate']===true,'Follow Up replay was not idempotent.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM messages WHERE sender_user_id=? AND idempotency_key=?',[$mark,'stage-d:follow-mark-dave:'.$runId])===1,'Follow Up replay duplicated the message.');
    $summary['follow_up_replay_safe']=true;

    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items WHERE instance_id=? AND folder='sent'",[(int)$instance['id']])===3,'Sent projections are incomplete.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items WHERE instance_id=? AND user_id=? AND folder='inbox'",[(int)$instance['id'],$dave])===1,'Final recipient Inbox projection is missing.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_inbox_items WHERE instance_id=?',[(int)$instance['id']])===4,'Action Center created duplicate user projections.');
    $summary['action_center_folders_consistent']=true;

    $pdo->rollBack();
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?,?,?)',[$merchantEmail,$sarahEmail,$markEmail,$daveEmail])===0,'Stage D user fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances WHERE idempotency_key=?',['stage-d:issue:'.$runId])===0,'Stage D Microgift fixture remains.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
