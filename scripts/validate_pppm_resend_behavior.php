<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__).'/api/microgifts/_delivery.php';
require_once dirname(__DIR__).'/api/microgifts/_action_center_projection.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

$pdo=mg_db();
$runId='resend'.bin2hex(random_bytes(5));
$result=array_fill_keys([
    'initial_send_timestamp','resend_timestamp','same_pppm_identity','ownership_unchanged',
    'recipient_reactivated','idempotent_replay','event_count','rollback_clean',
],false);

$pdo->beginTransaction();
try{
    $senderId=mg_it_user($pdo,$runId.'-sender@example.test','Resend Sender');
    $recipientId=mg_it_user($pdo,$runId.'-recipient@example.test','Resend Recipient');
    $pppm=mg_it_pppm($pdo,$senderId,$runId);
    $now=gmdate('Y-m-d H:i:s');

    $templatePublic=mg_public_uuid();
    $templateId=mg_it_insert($pdo,'microgift_templates',[
        'public_id'=>$templatePublic,'owner_type'=>'merchant','owner_user_id'=>$senderId,
        'name'=>'Resend Behavior Gift','slug'=>$runId.'-gift','gift_type'=>'product','status'=>'active',
        'visibility'=>'public','default_currency'=>'USD','created_by_user_id'=>$senderId,
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    $versionPublic=mg_public_uuid();
    $versionId=mg_it_insert($pdo,'microgift_template_versions',[
        'public_id'=>$versionPublic,'template_id'=>$templateId,'version_number'=>1,'status'=>'published',
        'title'=>'Resend Behavior Gift','currency'=>'USD','face_value_cents'=>2500,
        'recipient_policy'=>'purchaser','claim_policy_json'=>'{}','redemption_policy_json'=>'{}',
        'location_policy_json'=>'{}','expiration_policy_json'=>'{}','terms_snapshot_json'=>'{}',
        'future_demand_metadata_json'=>'{}','published_at'=>$now,'published_by_user_id'=>$senderId,
        'created_by_user_id'=>$senderId,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $pdo->prepare('UPDATE microgift_templates SET active_version_id=? WHERE id=?')->execute([$versionId,$templateId]);

    $instancePublic=mg_public_uuid();
    $instanceId=mg_it_insert($pdo,'microgift_instances',[
        'public_id'=>$instancePublic,'template_id'=>$templateId,'template_version_id'=>$versionId,
        'status'=>'delivered','source_type'=>'behavior','source_reference'=>$runId,
        'idempotency_key'=>$runId.'-issue','issuer_user_id'=>$senderId,'owner_user_id'=>$recipientId,
        'recipient_user_id'=>$recipientId,'pppm_item_id'=>(int)$pppm['id'],'title_snapshot'=>'Resend Behavior Gift',
        'currency'=>'USD','face_value_cents'=>2500,'recipient_policy'=>'purchaser',
        'claim_policy_json'=>'{}','redemption_policy_json'=>'{}','location_policy_json'=>'{}',
        'expiration_policy_json'=>'{}','terms_snapshot_json'=>'{}','metadata_json'=>'{}',
        'issued_at'=>$now,'delivered_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $pdo->prepare('UPDATE pppm_items SET owner_user_id=?,recipient_user_id=?,status=\'delivered\',updated_at=NOW() WHERE id=?')
        ->execute([$recipientId,$recipientId,(int)$pppm['id']]);
    $instance=$pdo->query('SELECT * FROM microgift_instances WHERE id='.(int)$instanceId)->fetch(PDO::FETCH_ASSOC);

    $send=mg_microgift_delivery_event(
        $pdo,$instance,'sent',$senderId,$recipientId,$runId.'-send',null,
        ['pppm_item_id'=>$pppm['public_id']]
    );
    $projection=mg_action_center_sent($pdo,$instanceId,$senderId,$recipientId,[
        'sent_at'=>$send['occurred_at'],'received_at'=>$send['occurred_at'],'occurred_at'=>$send['occurred_at'],
    ]);
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT sent_at FROM microgift_inbox_items WHERE public_id=?',[$projection['sent_item_id']])===(string)$send['occurred_at'],'Sent projection did not keep the actual send timestamp.');
    $result['initial_send_timestamp']=true;

    $pdo->prepare('UPDATE microgift_inbox_items SET read_at=NOW() WHERE public_id=?')->execute([$projection['recipient_inbox_item_id']]);
    $resend=mg_microgift_delivery_event(
        $pdo,$instance,'resent',$senderId,$recipientId,$runId.'-resend',$projection['sent_item_id'],
        ['pppm_item_id'=>$pppm['public_id'],'original_sent_at'=>$send['occurred_at']]
    );
    mg_action_center_sent($pdo,$instanceId,$senderId,$recipientId,[
        'occurred_at'=>$resend['occurred_at'],'received_at'=>$resend['occurred_at'],
    ]);
    $pdo->prepare("UPDATE microgift_inbox_items SET read_at=NULL,updated_at=? WHERE instance_id=? AND user_id=? AND folder='inbox'")
        ->execute([$resend['occurred_at'],$instanceId,$recipientId]);
    $result['resend_timestamp']=!empty($resend['occurred_at']);

    mg_it_assert((string)mg_it_scalar($pdo,'SELECT public_id FROM pppm_items WHERE id=?',[(int)$pppm['id']])===(string)$pppm['public_id'],'Resend changed the PPPM identity.');
    $result['same_pppm_identity']=true;
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[(int)$pppm['id']])===$recipientId,'Resend changed PPPM ownership.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT owner_user_id FROM microgift_instances WHERE id=?',[$instanceId])===$recipientId,'Resend changed Microgift ownership.');
    $result['ownership_unchanged']=true;
    mg_it_assert(mg_it_scalar($pdo,'SELECT read_at FROM microgift_inbox_items WHERE public_id=?',[$projection['recipient_inbox_item_id']])===false||mg_it_scalar($pdo,'SELECT read_at FROM microgift_inbox_items WHERE public_id=?',[$projection['recipient_inbox_item_id']])===null,'Resend did not reactivate the recipient Inbox item.');
    $result['recipient_reactivated']=true;

    $replay=mg_microgift_delivery_event(
        $pdo,$instance,'resent',$senderId,$recipientId,$runId.'-resend',$projection['sent_item_id'],
        ['pppm_item_id'=>$pppm['public_id']]
    );
    mg_it_assert(!empty($replay['duplicate']),'Exact resend replay was not idempotent.');
    mg_it_assert((string)$replay['event_id']===(string)$resend['event_id'],'Resend replay returned a different event.');
    $result['idempotent_replay']=true;

    $summary=mg_microgift_delivery_summary($pdo,$instanceId);
    mg_it_assert((int)$summary['resend_count']===1,'Resend count is incorrect.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_delivery_events WHERE instance_id=?',[$instanceId])===2,'Delivery timeline duplicated events.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances WHERE pppm_item_id=?',[(int)$pppm['id']])===1,'Resend created another Microgift instance.');
    $result['event_count']=true;

    $pdo->rollBack();
    $result['rollback_clean']=true;
    echo json_encode($result+['suite'=>'pppm_resend_behavior'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
