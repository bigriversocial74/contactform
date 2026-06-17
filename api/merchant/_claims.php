<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_claim_workspace(PDO $pdo, array $user): array
{
    return mg_merchant_ensure_workspace($pdo,$user);
}

function mg_claim_location(PDO $pdo, array $user, string $publicId, bool $forUpdate=false): array
{
    $workspace=mg_claim_workspace($pdo,$user);
    $sql='SELECT ml.* FROM merchant_locations ml WHERE ml.public_id=? AND ml.workspace_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId,(int)$workspace['id']]);$row=$stmt->fetch();
    if(!$row)mg_fail('Merchant location not found.',404);
    return $row;
}

function mg_claim_lookup(PDO $pdo, int $merchantUserId, string $identifier): array
{
    $stmt=$pdo->prepare("SELECT g.id gift_db_id,g.public_id gift_id,g.status gift_status,g.expires_at,g.sender_user_id,g.recipient_user_id,g.title,g.value_cents,g.currency,gc.id claim_db_id,gc.public_id claim_id,gc.status claim_status,gc.failed_attempts,gc.locked_at,gc.verified_at,gc.redeemed_at,gc.expires_at claim_expires_at,gc.location_id,gc.merchant_claim_code_id,m.pppm_item_id,pi.public_id pppm_id,pi.status pppm_status FROM gifts g LEFT JOIN gift_claims gc ON gc.gift_id=g.id LEFT JOIN pppm_legacy_gift_map m ON m.gift_id=g.id LEFT JOIN pppm_items pi ON pi.id=m.pppm_item_id WHERE (g.public_id=? OR pi.public_id=?) AND EXISTS (SELECT 1 FROM gift_merchant_eligibility e WHERE e.gift_id=g.id AND e.merchant_user_id=?) LIMIT 1");
    $stmt->execute([$identifier,$identifier,$merchantUserId]);$row=$stmt->fetch();
    if(!$row)mg_fail('Eligible gift or PPPM item not found.',404);
    return $row;
}

function mg_claim_code_pepper(): string
{
    $config=require dirname(__DIR__).'/config.php';
    $pepper=(string)($config['security']['claim_code_pepper']??'');
    if($pepper==='')mg_fail('Merchant claim-code management is not configured.',503);
    return $pepper;
}
