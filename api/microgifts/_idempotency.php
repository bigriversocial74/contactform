<?php
declare(strict_types=1);

function mg_microgift_assert_claim_replay(PDO $pdo,string $key,string $instancePublicId,int $claimantUserId): ?array
{
    $stmt=$pdo->prepare('SELECT c.public_id,c.status,c.claimant_user_id,c.source_reference,i.public_id instance_public_id FROM microgift_claims c INNER JOIN microgift_instances i ON i.id=c.instance_id WHERE c.idempotency_key=? LIMIT 1');
    $stmt->execute([$key]);
    $row=$stmt->fetch();
    if(!$row) return null;
    $same=(int)$row['claimant_user_id']===$claimantUserId
        && hash_equals((string)$row['instance_public_id'],$instancePublicId)
        && hash_equals((string)$row['source_reference'],$instancePublicId);
    if(!$same) throw new RuntimeException('Microgift claim idempotency key is already bound to a different request.');
    return $row;
}

function mg_microgift_assert_redemption_replay(PDO|array $subject,mixed ...$args): ?array
{
    if($subject instanceof PDO){
        [$key,$instancePublicId,$userId,$merchantId,$location,$source]=$args+[null,null,null,null,null,null];
        $stmt=$subject->prepare('SELECT r.public_id,r.status,r.claimant_user_id,r.merchant_user_id,r.location_reference,r.source_reference,i.public_id instance_public_id FROM microgift_redemptions r INNER JOIN microgift_instances i ON i.id=r.instance_id WHERE r.idempotency_key=? LIMIT 1');
        $stmt->execute([(string)$key]);
        $row=$stmt->fetch();
        if(!$row) return null;
        $existing=$row;
    }else{
        [$instancePublicId,$userId,$merchantId,$source,$location]=$args+[null,null,null,null,null];
        $existing=$subject;
    }

    $same=(int)$existing['claimant_user_id']===(int)$userId
        && (int)$existing['merchant_user_id']===(int)$merchantId
        && hash_equals((string)$existing['instance_public_id'],(string)$instancePublicId)
        && hash_equals((string)($existing['location_reference']??''),(string)($location??''))
        && hash_equals((string)$existing['source_reference'],(string)$source);
    if(!$same) throw new RuntimeException('Microgift redemption idempotency key is already bound to a different request.');
    return $existing;
}

function mg_microgift_assert_lifecycle_replay(PDO $pdo,string $key,string $instancePublicId,string $action,string $sourceType,string $sourceReference): ?array
{
    $stmt=$pdo->prepare('SELECT a.public_id,a.action_type,a.to_status,a.source_type,a.source_reference,i.public_id instance_public_id FROM microgift_lifecycle_actions a INNER JOIN microgift_instances i ON i.id=a.instance_id WHERE a.idempotency_key=? LIMIT 1');
    $stmt->execute([$key]);
    $row=$stmt->fetch();
    if(!$row) return null;
    $same=hash_equals((string)$row['instance_public_id'],$instancePublicId)
        && hash_equals((string)$row['action_type'],$action)
        && hash_equals((string)$row['source_type'],$sourceType)
        && hash_equals((string)$row['source_reference'],$sourceReference);
    if(!$same) throw new RuntimeException('Microgift lifecycle idempotency key is already bound to a different request.');
    return $row;
}
