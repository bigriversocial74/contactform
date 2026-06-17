<?php
declare(strict_types=1);

require_once __DIR__ . '/_atomic_merchant_redemption.php';
require_once __DIR__ . '/_operations.php';

function mg_claim_rate_bucket_key(string $scope,string $subject): string
{
    return hash('sha256',$scope.'|'.trim($subject));
}

function mg_claim_rate_limit(PDO $pdo,string $scope,string $subject,int $limit,int $windowSeconds,int $blockSeconds): array
{
    $key = mg_claim_rate_bucket_key($scope,$subject);
    $now = time();
    $stmt = $pdo->prepare('SELECT * FROM microgift_claim_rate_limits WHERE bucket_key=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->prepare('INSERT INTO microgift_claim_rate_limits (bucket_key,scope,subject_reference,window_started_at,window_seconds,attempt_count,limit_count,last_attempt_at,created_at,updated_at) VALUES (?,?,?,NOW(),?,1,?,NOW(),NOW(),NOW())')
            ->execute([$key,$scope,$subject,$windowSeconds,$limit]);
        return ['allowed'=>true,'remaining'=>max(0,$limit-1),'retry_after'=>0];
    }

    if ($row['blocked_until'] !== null && strtotime((string)$row['blocked_until']) > $now) {
        return ['allowed'=>false,'remaining'=>0,'retry_after'=>strtotime((string)$row['blocked_until'])-$now];
    }

    $windowStarted = strtotime((string)$row['window_started_at']);
    $count = (int)$row['attempt_count'];
    if ($windowStarted + (int)$row['window_seconds'] <= $now) {
        $pdo->prepare('UPDATE microgift_claim_rate_limits SET window_started_at=NOW(),window_seconds=?,attempt_count=1,limit_count=?,blocked_until=NULL,last_attempt_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$windowSeconds,$limit,(int)$row['id']]);
        return ['allowed'=>true,'remaining'=>max(0,$limit-1),'retry_after'=>0];
    }

    $count++;
    if ($count > $limit) {
        $pdo->prepare('UPDATE microgift_claim_rate_limits SET attempt_count=?,blocked_until=DATE_ADD(NOW(),INTERVAL ? SECOND),last_attempt_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$count,$blockSeconds,(int)$row['id']]);
        return ['allowed'=>false,'remaining'=>0,'retry_after'=>$blockSeconds];
    }

    $pdo->prepare('UPDATE microgift_claim_rate_limits SET attempt_count=?,last_attempt_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute([$count,(int)$row['id']]);
    return ['allowed'=>true,'remaining'=>max(0,$limit-$count),'retry_after'=>0];
}

function mg_claim_rate_policy(PDO $pdo,string $scope,?int $merchantUserId,?int $locationId,array $fallback): array
{
    $stmt = $pdo->prepare("SELECT limit_count,window_seconds,block_seconds FROM microgift_claim_rate_policies WHERE scope=? AND status='active' AND (merchant_user_id IS NULL OR merchant_user_id=?) AND (location_id IS NULL OR location_id=?) ORDER BY (location_id IS NOT NULL) DESC,(merchant_user_id IS NOT NULL) DESC,priority ASC,id DESC LIMIT 1");
    $stmt->execute([$scope,$merchantUserId,$locationId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: $fallback;
}

function mg_claim_apply_rate_limits(PDO $pdo,int $actorUserId,int $merchantUserId,string $locationPublicId,string $instancePublicId,string $networkFingerprint): void
{
    $locationId = null;
    if ($locationPublicId !== '') {
        $location = $pdo->prepare('SELECT id FROM merchant_locations WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $location->execute([$locationPublicId,$merchantUserId]);
        $locationId = $location->fetchColumn() ?: null;
    }

    $defaults = [
        'actor'=>['limit_count'=>20,'window_seconds'=>60,'block_seconds'=>120],
        'merchant'=>['limit_count'=>120,'window_seconds'=>60,'block_seconds'=>120],
        'location'=>['limit_count'=>60,'window_seconds'=>60,'block_seconds'=>180],
        'gift'=>['limit_count'=>10,'window_seconds'=>300,'block_seconds'=>300],
        'network'=>['limit_count'=>40,'window_seconds'=>60,'block_seconds'=>180],
    ];
    $subjects = [
        'actor'=>(string)$actorUserId,
        'merchant'=>(string)$merchantUserId,
        'location'=>$locationPublicId,
        'gift'=>$instancePublicId,
    ];
    if ($networkFingerprint !== '') $subjects['network'] = $networkFingerprint;

    foreach ($subjects as $scope=>$subject) {
        $policy = mg_claim_rate_policy($pdo,$scope,$merchantUserId,$locationId,$defaults[$scope]);
        $result = mg_claim_rate_limit($pdo,$scope,$subject,(int)$policy['limit_count'],(int)$policy['window_seconds'],(int)$policy['block_seconds']);
        if (!$result['allowed']) {
            throw new MgLocationClaimAuthorityException('rate_limited','Claim submission is temporarily unavailable.');
        }
    }
}

function mg_claim_create_escalation(PDO $pdo,array $context,string $triggerType,string $severity,string $summary,array $details=[]): string
{
    $publicId = mg_microgift_uuid();
    $reviewPublicId = mg_microgift_create_review(
        $pdo,
        'claim_security',
        'merchant_claim',
        (string)($context['correlation_id'] ?? $publicId),
        $summary,
        [
            'priority'=>$severity,
            'instance_id'=>$context['instance_id'] ?? null,
            'user_id'=>$context['actor_user_id'] ?? null,
            'merchant_user_id'=>$context['merchant_user_id'] ?? null,
        ],
        $details
    );

    $reviewStmt = $pdo->prepare('SELECT id FROM microgift_review_items WHERE public_id=? LIMIT 1');
    $reviewStmt->execute([$reviewPublicId]);
    $reviewId = $reviewStmt->fetchColumn() ?: null;

    $pdo->prepare("INSERT INTO microgift_claim_escalations (public_id,merchant_user_id,location_id,instance_id,actor_user_id,review_item_id,trigger_type,severity,status,attempt_count,summary,details_json,first_seen_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'open',1,?,?,NOW(),NOW(),NOW(),NOW())")
        ->execute([$publicId,$context['merchant_user_id']??null,$context['location_id']??null,$context['instance_id']??null,$context['actor_user_id']??null,$reviewId,$triggerType,$severity,$summary,mg_microgift_json($details)]);
    return $publicId;
}

function mg_claim_should_escalate(PDO $pdo,array $context,string $result): ?array
{
    if (in_array($result,['merchant_mismatch','location_not_allowed','internal_error'],true)) {
        return [$result,$result==='internal_error'?'critical':'high'];
    }
    if ($result === 'rate_limited') return ['rate_limit','high'];
    if ($result === 'invalid_claim_code') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM microgift_claim_attempts WHERE merchant_user_id=? AND actor_user_id=? AND result='invalid_claim_code' AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
        $stmt->execute([$context['merchant_user_id']??0,$context['actor_user_id']??0]);
        if ((int)$stmt->fetchColumn() >= 5) return ['repeated_invalid_code','high'];
    }
    return null;
}

function mg_claim_execute_operation(PDO $pdo,int $actorUserId,int $merchantUserId,array $input): array
{
    $locationPublicId = trim((string)($input['location_id'] ?? ''));
    $instancePublicId = trim((string)($input['instance_id'] ?? ''));
    $correlationId = trim((string)($input['correlation_id'] ?? '')) ?: mg_microgift_uuid();
    $networkFingerprint = trim((string)($input['network_fingerprint'] ?? ''));

    $pdo->beginTransaction();
    try {
        mg_claim_apply_rate_limits($pdo,$actorUserId,$merchantUserId,$locationPublicId,$instancePublicId,$networkFingerprint);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $context = ['merchant_user_id'=>$merchantUserId,'actor_user_id'=>$actorUserId,'correlation_id'=>$correlationId];
        if ($error instanceof MgLocationClaimAuthorityException && $error->resultCode==='rate_limited') {
            mg_location_claim_record_attempt($pdo,$context+['result'=>'rate_limited','reason_code'=>'rate_limited']);
            mg_claim_create_escalation($pdo,$context,'rate_limit','high','Merchant claim rate limit exceeded.');
        }
        throw $error;
    }

    try {
        $claimInput = $input;
        $claimInput['merchant_user_id'] = $merchantUserId;
        $claimInput['correlation_id'] = $correlationId;
        return mg_microgift_atomic_merchant_redeem($pdo,$actorUserId,$claimInput);
    } catch (Throwable $error) {
        $normalized = mg_microgift_attempt_result($error);
        $context = ['merchant_user_id'=>$merchantUserId,'actor_user_id'=>$actorUserId,'correlation_id'=>$correlationId];
        $escalation = mg_claim_should_escalate($pdo,$context,$normalized);
        if ($escalation) {
            [$trigger,$severity] = $escalation;
            mg_claim_create_escalation($pdo,$context,$trigger,$severity,'Merchant claim requires operational review.',['result'=>$normalized]);
        }
        throw $error;
    }
}

function mg_claim_history(PDO $pdo,int $merchantUserId,array $filters=[]): array
{
    $limit = max(1,min((int)($filters['limit'] ?? 100),250));
    $result = trim((string)($filters['result'] ?? ''));
    $location = trim((string)($filters['location_id'] ?? ''));
    $sql = "SELECT a.public_id attempt_id,a.result,a.reason_code,a.correlation_id,a.attempted_at,
                   i.public_id instance_id,l.public_id location_id,l.name location_name,
                   r.public_id redemption_id,r.status redemption_status,r.amount_cents,r.currency,r.redeemed_at,
                   a.actor_user_id
            FROM microgift_claim_attempts a
            LEFT JOIN microgift_instances i ON i.id=a.instance_id
            LEFT JOIN merchant_locations l ON l.id=a.location_id
            LEFT JOIN microgift_redemptions r ON r.claim_attempt_id=a.id
            WHERE a.merchant_user_id=?";
    $params = [$merchantUserId];
    if ($result!=='') {$sql.=' AND a.result=?';$params[]=$result;}
    if ($location!=='') {$sql.=' AND l.public_id=?';$params[]=$location;}
    $sql .= " ORDER BY a.attempted_at DESC,a.id DESC LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
