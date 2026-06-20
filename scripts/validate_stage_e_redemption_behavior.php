<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/microgifts/_lifecycle.php';
require_once dirname(__DIR__).'/api/microgifts/_action_center_projection.php';
require_once dirname(__DIR__).'/api/microgifts/_atomic_merchant_redemption.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_stage_e_deliver(
    PDO $pdo,
    int $merchantId,
    int $recipientId,
    array $version,
    string $runId,
    string $suffix
): array {
    $issue=mg_microgift_issue($pdo,$merchantId,[
        'template_version_id'=>$version['version_id'],
        'source_type'=>'merchant',
        'source_reference'=>'stage-e-'.$runId.'-'.$suffix,
        'idempotency_key'=>'stage-e:issue:'.$runId.':'.$suffix,
    ]);
    $instance=mg_microgift_load_instance($pdo,(string)$issue['instance_id']);
    mg_action_center_project_lifecycle($pdo,$instance);

    $pppm=mg_it_pppm($pdo,$merchantId,$runId.'-'.$suffix);
    $pdo->prepare('UPDATE microgift_instances SET pppm_item_id=? WHERE id=?')
        ->execute([$pppm['id'],(int)$instance['id']]);
    mg_pppm_transfer_owner_canonical(
        $pdo,$pppm['public_id'],$recipientId,'stage_e_delivery','stage-e:deliver:'.$runId.':'.$suffix,
        $merchantId,['microgift_instance_id'=>(string)$instance['public_id']]
    );
    $pdo->prepare("UPDATE microgift_instances
        SET owner_user_id=?,recipient_user_id=?,status='delivered',delivered_at=NOW(),updated_at=NOW()
        WHERE id=?")
        ->execute([$recipientId,$recipientId,(int)$instance['id']]);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    $projection=mg_action_center_sent($pdo,(int)$instance['id'],$merchantId,$recipientId,[
        'sent_at'=>date('Y-m-d H:i:s'),
        'received_at'=>date('Y-m-d H:i:s'),
        'occurred_at'=>date('Y-m-d H:i:s'),
    ]);

    return ['issue'=>$issue,'instance'=>$instance,'pppm'=>$pppm,'projection'=>$projection];
}

$pdo=mg_db();
$runId='stage_e_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'stage_e_atomic_redemption_behavior',
    'run_id'=>$runId,
    'delivered_gift_redeemed'=>false,
    'merchant_location_authorized'=>false,
    'pppm_redeemed'=>false,
    'claim_code_usage_recorded'=>false,
    'action_center_reconciled'=>false,
    'customer_confirmation_created'=>false,
    'merchant_confirmation_created'=>false,
    'replay_idempotent'=>false,
    'invalid_code_attempt_recorded'=>false,
    'invalid_code_left_gift_available'=>false,
    'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $merchantEmail=$runId.'-merchant@example.test';
    $recipientEmail=$runId.'-recipient@example.test';
    $merchant=mg_it_user($pdo,$merchantEmail,'Stage E Merchant');
    $recipient=mg_it_user($pdo,$recipientEmail,'Stage E Recipient');

    $template=mg_microgift_create_template($pdo,$merchant,[
        'owner_type'=>'merchant','name'=>'Stage E Redemption','gift_type'=>'value',
        'visibility'=>'private','default_currency'=>'USD',
    ]);
    $version=mg_microgift_create_version($pdo,$merchant,(string)$template['template_id'],[
        'title'=>'Stage E Redemption','currency'=>'USD','face_value_cents'=>2500,
        'recipient_policy'=>'open_claim','claim_policy'=>[],'redemption_policy'=>[],
        'location_policy'=>['mode'=>'unrestricted'],
    ]);
    mg_microgift_publish_version($pdo,$merchant,(string)$version['version_id']);
    $location=mg_it_location($pdo,$merchant,$runId);

    $valid=mg_stage_e_deliver($pdo,$merchant,$recipient,$version,$runId,'valid');
    $instance=$valid['instance'];
    $redeemInput=[
        'instance_id'=>(string)$instance['public_id'],
        'merchant_user_id'=>$merchant,
        'location_id'=>$location['public_id'],
        'claim_code'=>$location['code'],
        'idempotency_key'=>'stage-e:redeem:'.$runId,
        'source_reference'=>'stage_e_redemption',
        'correlation_id'=>'stage-e-valid-'.$runId,
    ];
    $redemption=mg_microgift_atomic_merchant_redeem($pdo,$merchant,$redeemInput);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);

    mg_it_assert($redemption['duplicate']===false,'Stage E redemption was not created.');
    mg_it_assert((string)$instance['status']==='redeemed','Delivered Microgift did not become redeemed.');
    mg_it_assert(!empty($instance['claimed_at'])&&!empty($instance['redeemed_at']),'Redemption timestamps were not recorded.');
    mg_it_assert((int)$instance['owner_user_id']===$recipient,'Redemption changed the current owner.');
    $summary['delivered_gift_redeemed']=true;

    $redemptionRow=$pdo->prepare('SELECT merchant_user_id,location_id,merchant_claim_code_id,claim_attempt_id,status FROM microgift_redemptions WHERE public_id=?');
    $redemptionRow->execute([(string)$redemption['redemption_id']]);
    $redemptionState=$redemptionRow->fetch(PDO::FETCH_ASSOC);
    mg_it_assert((int)$redemptionState['merchant_user_id']===$merchant,'Redemption merchant authority is wrong.');
    mg_it_assert((int)$redemptionState['location_id']===(int)$location['id'],'Redemption location authority is wrong.');
    mg_it_assert((int)$redemptionState['merchant_claim_code_id']>0&&(int)$redemptionState['claim_attempt_id']>0,'Redemption authority references are missing.');
    mg_it_assert((string)$redemptionState['status']==='completed','Redemption record is not completed.');
    $summary['merchant_location_authorized']=true;

    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM pppm_items WHERE id=?',[$valid['pppm']['id']])==='redeemed','PPPM item was not redeemed.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[$valid['pppm']['id']])===$recipient,'PPPM ownership changed during redemption.');
    $summary['pppm_redeemed']=true;

    mg_it_assert((int)mg_it_scalar($pdo,'SELECT usage_count FROM merchant_claim_codes WHERE location_id=? AND status=?',[$location['id'],'active'])===1,'Location claim-code usage was not incremented once.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_claim_attempts WHERE instance_id=? AND result=?',[(int)$instance['id'],'approved'])===1,'Approved claim attempt is missing.');
    $summary['claim_code_usage_recorded']=true;

    $senderRow=$pdo->prepare('SELECT folder,state,can_tip FROM microgift_inbox_items WHERE instance_id=? AND user_id=?');
    $senderRow->execute([(int)$instance['id'],$merchant]);
    $senderState=$senderRow->fetch(PDO::FETCH_ASSOC);
    $recipientRow=$pdo->prepare('SELECT folder,state,can_tip,redemption_id,location_id FROM microgift_inbox_items WHERE instance_id=? AND user_id=?');
    $recipientRow->execute([(int)$instance['id'],$recipient]);
    $recipientState=$recipientRow->fetch(PDO::FETCH_ASSOC);
    mg_it_assert(($senderState['folder']??'')==='sent'&&($senderState['state']??'')==='redeemed'&&(int)($senderState['can_tip']??0)===0,'Historical sender projection is inconsistent.');
    mg_it_assert(($recipientState['folder']??'')==='claimed'&&($recipientState['state']??'')==='redeemed'&&(int)($recipientState['can_tip']??0)===1,'Recipient Claimed projection is inconsistent.');
    mg_it_assert((int)$recipientState['location_id']===(int)$location['id']&&(int)$recipientState['redemption_id']>0,'Recipient redemption references are missing.');
    $summary['action_center_reconciled']=true;

    mg_it_assert(!empty($redemption['customer_notification_id']),'Customer confirmation ID is missing.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='microgift_redeemed'",[$recipient])===1,'Customer redemption confirmation was not created exactly once.');
    $summary['customer_confirmation_created']=true;

    mg_it_assert(!empty($redemption['merchant_notification_id']),'Merchant confirmation ID is missing.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='merchant_redemption'",[$merchant])===1,'Merchant redemption confirmation was not created exactly once.');
    $summary['merchant_confirmation_created']=true;

    $replay=mg_microgift_atomic_merchant_redeem($pdo,$merchant,$redeemInput);
    mg_it_assert($replay['duplicate']===true,'Redemption replay was not idempotent.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions WHERE instance_id=?',[(int)$instance['id']])===1,'Redemption replay duplicated the redemption row.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_claim_attempts WHERE instance_id=? AND result=?',[(int)$instance['id'],'approved'])===1,'Redemption replay duplicated the approved attempt.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT usage_count FROM merchant_claim_codes WHERE location_id=? AND status=?',[$location['id'],'active'])===1,'Redemption replay incremented code usage twice.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='microgift_redeemed'",[$recipient])===1,'Redemption replay duplicated the customer confirmation.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='merchant_redemption'",[$merchant])===1,'Redemption replay duplicated the merchant confirmation.');
    $summary['replay_idempotent']=true;

    $invalid=mg_stage_e_deliver($pdo,$merchant,$recipient,$version,$runId,'invalid');
    $invalidFailed=false;
    $invalidReason='';
    try{
        mg_microgift_atomic_merchant_redeem($pdo,$merchant,[
            'instance_id'=>(string)$invalid['instance']['public_id'],
            'merchant_user_id'=>$merchant,
            'location_id'=>$location['public_id'],
            'claim_code'=>'WRONG-'.$runId,
            'idempotency_key'=>'stage-e:invalid-code:'.$runId,
            'source_reference'=>'stage_e_invalid_code',
            'correlation_id'=>'stage-e-invalid-'.$runId,
        ]);
    }catch(Throwable $error){
        $invalidFailed=true;
        $invalidReason=$error instanceof MgLocationClaimAuthorityException?$error->resultCode:'';
    }
    mg_it_assert($invalidFailed&&$invalidReason==='invalid_claim_code','Invalid location code was not rejected canonically.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_claim_attempts WHERE instance_id=? AND result=?',[(int)$invalid['instance']['id'],'invalid_claim_code'])===1,'Invalid-code attempt was not recorded exactly once.');
    $summary['invalid_code_attempt_recorded']=true;

    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM microgift_instances WHERE id=?',[(int)$invalid['instance']['id']])==='delivered','Invalid code changed the Microgift lifecycle.');
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM pppm_items WHERE id=?',[$invalid['pppm']['id']])==='available','Invalid code changed the PPPM lifecycle.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions WHERE instance_id=?',[(int)$invalid['instance']['id']])===0,'Invalid code created a redemption.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT usage_count FROM merchant_claim_codes WHERE location_id=? AND status=?',[$location['id'],'active'])===1,'Invalid code changed claim-code usage.');
    $summary['invalid_code_left_gift_available']=true;

    $pdo->rollBack();
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$merchantEmail,$recipientEmail])===0,'Stage E user fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances WHERE idempotency_key LIKE ?',['stage-e:issue:'.$runId.'%'])===0,'Stage E Microgift fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
