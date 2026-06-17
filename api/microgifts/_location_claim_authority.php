<?php
declare(strict_types=1);

final class MgLocationClaimAuthorityException extends RuntimeException
{
    public function __construct(public string $resultCode,string $message)
    {
        parent::__construct($message);
    }
}

function mg_location_claim_normalize_code(string $value): string
{
    // Stage 5 stores the trimmed merchant-supplied code exactly. Preserve that
    // contract so existing codes remain verifiable and case-sensitive.
    return trim($value);
}

function mg_location_claim_pepper(): string
{
    $config=require dirname(__DIR__).'/config.php';
    $pepper=trim((string)($config['security']['claim_code_pepper']??''));
    if($pepper==='')throw new RuntimeException('Merchant claim-code verification is not configured.');
    return $pepper;
}

function mg_location_claim_hash(string $normalized): string
{
    return hash_hmac('sha256',$normalized,mg_location_claim_pepper());
}

function mg_location_claim_actor_authorized(PDO $pdo,int $merchantId,int $locationId,int $actorId): bool
{
    if ($actorId === $merchantId) return true;

    $staff = $pdo->prepare("SELECT 1 FROM merchant_location_staff WHERE merchant_user_id=? AND location_id=? AND user_id=? AND status='active' LIMIT 1");
    $staff->execute([$merchantId,$locationId,$actorId]);
    if ($staff->fetchColumn()) return true;

    $admin = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug IN ('admin','super_admin') LIMIT 1");
    $admin->execute([$actorId]);
    return (bool)$admin->fetchColumn();
}

function mg_location_claim_resolve_authority(PDO $pdo,int $merchantId,string $locationPublicId,int $actorId,string $submittedCode): array
{
    $stmt = $pdo->prepare('SELECT * FROM merchant_locations WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$locationPublicId]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) throw new MgLocationClaimAuthorityException('invalid_location','Location claim authority could not be verified.');
    if ((int)$location['merchant_user_id'] !== $merchantId) throw new MgLocationClaimAuthorityException('merchant_mismatch','Location claim authority could not be verified.');
    if ((string)$location['status'] !== 'active') throw new MgLocationClaimAuthorityException('invalid_location','Location claim authority could not be verified.');
    if (!mg_location_claim_actor_authorized($pdo,$merchantId,(int)$location['id'],$actorId)) {
        throw new MgLocationClaimAuthorityException('unauthorized_claim_actor','Location claim authority could not be verified.');
    }

    $normalized = mg_location_claim_normalize_code($submittedCode);
    if (mb_strlen($normalized) < 4 || mb_strlen($normalized) > 64) throw new MgLocationClaimAuthorityException('invalid_claim_code','Location claim authority could not be verified.');

    $codeStmt = $pdo->prepare("SELECT * FROM merchant_claim_codes WHERE merchant_user_id=? AND location_id=? AND code_last4=? AND status='active' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $codeStmt->execute([$merchantId,(int)$location['id'],substr($normalized,-4)]);
    $claimCode = $codeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$claimCode || !hash_equals((string)$claimCode['code_hash'],mg_location_claim_hash($normalized))) {
        throw new MgLocationClaimAuthorityException('invalid_claim_code','Location claim authority could not be verified.');
    }

    $now = time();
    if ($claimCode['valid_from'] !== null && strtotime((string)$claimCode['valid_from']) > $now) throw new MgLocationClaimAuthorityException('invalid_claim_code','Location claim authority could not be verified.');
    if ($claimCode['valid_until'] !== null && strtotime((string)$claimCode['valid_until']) <= $now) throw new MgLocationClaimAuthorityException('invalid_claim_code','Location claim authority could not be verified.');
    if ($claimCode['usage_limit'] !== null && (int)$claimCode['usage_count'] >= (int)$claimCode['usage_limit']) throw new MgLocationClaimAuthorityException('invalid_claim_code','Location claim authority could not be verified.');

    return [
        'merchant_user_id'=>$merchantId,
        'location_id'=>(int)$location['id'],
        'location_public_id'=>(string)$location['public_id'],
        'merchant_claim_code_id'=>(int)$claimCode['id'],
        'merchant_claim_code_public_id'=>(string)$claimCode['public_id'],
        'actor_user_id'=>$actorId,
    ];
}

function mg_location_claim_increment_usage(PDO $pdo,int $claimCodeId): void
{
    $stmt = $pdo->prepare("UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=? AND status='active' AND (usage_limit IS NULL OR usage_count<usage_limit)");
    $stmt->execute([$claimCodeId]);
    if ($stmt->rowCount() !== 1) throw new MgLocationClaimAuthorityException('invalid_claim_code','Claim-code usage could not be reserved.');
}

function mg_location_claim_record_attempt(PDO $pdo,array $attempt): string
{
    $results = ['approved','invalid_gift','gift_not_paid','invalid_state','gift_expired','already_claimed','merchant_mismatch','invalid_location','location_not_allowed','invalid_claim_code','unauthorized_claim_actor','rate_limited','idempotency_conflict','internal_error'];
    $result = (string)($attempt['result'] ?? 'internal_error');
    if (!in_array($result,$results,true)) throw new InvalidArgumentException('Unknown claim-attempt result.');

    $publicId = sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(6)));
    $stmt = $pdo->prepare("INSERT INTO microgift_claim_attempts (public_id,instance_id,merchant_user_id,location_id,merchant_claim_code_id,actor_user_id,result,reason_code,idempotency_key,correlation_id,attempted_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([
        $publicId,
        $attempt['instance_id']??null,
        $attempt['merchant_user_id']??null,
        $attempt['location_id']??null,
        $attempt['merchant_claim_code_id']??null,
        $attempt['actor_user_id']??null,
        $result,
        (string)($attempt['reason_code']??$result),
        $attempt['idempotency_key']??null,
        $attempt['correlation_id']??null,
    ]);

    $attemptId = (int)$pdo->lastInsertId();
    $hasSecurity = isset($attempt['request_fingerprint']) || isset($attempt['ip_hash']) || isset($attempt['user_agent_hash']) || isset($attempt['risk']) || isset($attempt['metadata']);
    if ($hasSecurity) {
        $security = $pdo->prepare("INSERT INTO microgift_claim_attempt_security (attempt_id,request_fingerprint,ip_hash,user_agent_hash,risk_json,metadata_json,expires_at,created_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 365 DAY),NOW())");
        $security->execute([
            $attemptId,
            $attempt['request_fingerprint']??null,
            $attempt['ip_hash']??null,
            $attempt['user_agent_hash']??null,
            isset($attempt['risk'])?json_encode($attempt['risk'],JSON_THROW_ON_ERROR):null,
            isset($attempt['metadata'])?json_encode($attempt['metadata'],JSON_THROW_ON_ERROR):null,
        ]);
    }

    return $publicId;
}
