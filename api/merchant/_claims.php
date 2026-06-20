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

function mg_claim_code_pepper_file(array $config): string
{
    $root=trim((string)($config['storage']['root']??''));
    if($root==='')return '';
    return rtrim($root,"/\\").DIRECTORY_SEPARATOR.'.secrets'.DIRECTORY_SEPARATOR.'claim-code-pepper';
}

function mg_claim_code_read_pepper(string $path): string
{
    if($path===''||!is_file($path)||is_link($path))return '';
    $value=file_get_contents($path);
    if(!is_string($value))return '';
    $value=trim($value);
    return preg_match('/^[a-f0-9]{64}$/i',$value)===1?strtolower($value):'';
}

function mg_claim_code_create_pepper(string $path): string
{
    if($path==='')return '';
    $directory=dirname($path);
    if(!is_dir($directory)&&!mkdir($directory,0770,true)&&!is_dir($directory))return '';
    if(is_link($directory))return '';

    $value=bin2hex(random_bytes(32));
    $handle=@fopen($path,'x');
    if($handle===false)return mg_claim_code_read_pepper($path);

    $written=fwrite($handle,$value.PHP_EOL);
    fflush($handle);
    fclose($handle);
    @chmod($path,0600);
    if($written===false||$written<strlen($value)){
        @unlink($path);
        return '';
    }
    return $value;
}

function mg_claim_code_pepper(): string
{
    $config=require dirname(__DIR__).'/config.php';
    $configured=trim((string)($config['security']['claim_code_pepper']??''));
    if($configured!=='')return $configured;

    $path=mg_claim_code_pepper_file($config);
    $persisted=mg_claim_code_read_pepper($path);
    if($persisted!=='')return $persisted;

    $created=mg_claim_code_create_pepper($path);
    if($created!=='')return $created;

    mg_fail('Merchant claim-code security could not be initialized. Check the persistent storage directory permissions or configure MG_CLAIM_CODE_PEPPER.',503);
}
