<?php
declare(strict_types=1);

require_once __DIR__ . '/_lifecycle.php';
require_once __DIR__ . '/_idempotency.php';
require_once __DIR__ . '/_action_center_projection.php';

final class MgMicrogiftClaimAuthorityException extends RuntimeException
{
    public function __construct(string $message,public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_microgift_claim_input(array $input): array
{
    $instanceId=strtolower(trim((string)($input['instance_id']??'')));
    $key=trim((string)($input['idempotency_key']??''));
    if(strlen($instanceId)!==36||preg_match('/^[a-f0-9-]{36}$/',$instanceId)!==1){
        throw new MgMicrogiftClaimAuthorityException('A valid Microgift instance is required.',422);
    }
    if(strlen($key)<12||strlen($key)>190||preg_match('/^[A-Za-z0-9._:-]+$/',$key)!==1){
        throw new MgMicrogiftClaimAuthorityException('A valid claim idempotency key is required.',422);
    }
    $input['instance_id']=$instanceId;
    $input['idempotency_key']=$key;
    return $input;
}

function mg_microgift_assert_claim_result(PDO $pdo,array $instance,int $claimantUserId): void
{
    if((int)($instance['owner_user_id']??0)!==$claimantUserId){
        throw new MgMicrogiftClaimAuthorityException('Microgift ownership was not assigned to the claimant.',409);
    }
    if(!in_array((string)$instance['status'],['claimed','redeemable','redeemed'],true)){
        throw new MgMicrogiftClaimAuthorityException('Microgift did not enter a claimed lifecycle state.',409);
    }
    if(!empty($instance['pppm_item_id'])){
        $stmt=$pdo->prepare('SELECT owner_user_id,status FROM pppm_items WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$instance['pppm_item_id']]);
        $pppm=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$pppm||(int)($pppm['owner_user_id']??0)!==$claimantUserId){
            throw new MgMicrogiftClaimAuthorityException('PPPM ownership is not synchronized with the Microgift claimant.',409);
        }
        if(in_array((string)$pppm['status'],['cancelled','revoked','expired','replaced'],true)){
            throw new MgMicrogiftClaimAuthorityException('The linked PPPM item is not claimable.',409);
        }
    }
}

function mg_microgift_claim_canonical(PDO $pdo,int $claimantUserId,array $input,?callable $executor=null): array
{
    if(!$pdo->inTransaction())throw new LogicException('Canonical Microgift claims require an active transaction.');
    if($claimantUserId<1)throw new MgMicrogiftClaimAuthorityException('Authenticated claimant is required.',401);
    $input=mg_microgift_claim_input($input);
    $instanceId=(string)$input['instance_id'];
    $key=(string)$input['idempotency_key'];

    $existing=mg_microgift_assert_claim_replay($pdo,$key,$instanceId,$claimantUserId);
    if($existing){
        $result=[
            'claim_id'=>(string)$existing['public_id'],
            'instance_id'=>$instanceId,
            'status'=>(string)$existing['status'],
            'duplicate'=>true,
        ];
    }else{
        $result=$executor
            ? $executor($pdo,$claimantUserId,$input)
            : mg_microgift_claim($pdo,$claimantUserId,$input);
        if(!is_array($result)||!hash_equals($instanceId,(string)($result['instance_id']??''))){
            throw new MgMicrogiftClaimAuthorityException('Claim authority returned an invalid instance.',409);
        }
    }

    $instance=mg_microgift_load_instance($pdo,$instanceId);
    mg_microgift_assert_claim_result($pdo,$instance,$claimantUserId);
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance,[
        'sender_user_id'=>(int)($instance['issuer_user_id']??0),
        'recipient_user_id'=>$claimantUserId,
        'occurred_at'=>(string)($instance['claimed_at']??date('Y-m-d H:i:s')),
    ]);
    $result['pppm_item_id']=!empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null;
    $result['owner_user_id']=$claimantUserId;
    $result['lifecycle_status']=(string)$instance['status'];
    return $result;
}
