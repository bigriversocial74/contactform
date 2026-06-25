<?php
declare(strict_types=1);

function mg_campaign_email_suppression_uuid(): string
{
    $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));
}
function mg_campaign_email_suppression_install(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_email_suppressions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NULL,email_hash CHAR(64) NOT NULL,scope ENUM('campaign','merchant') NOT NULL DEFAULT 'campaign',reason VARCHAR(120) NOT NULL DEFAULT 'unsubscribe',status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_campaign_email_suppressions_public_id(public_id),UNIQUE KEY uq_campaign_email_suppressions_scope(merchant_user_id,campaign_id,email_hash,scope),KEY idx_campaign_email_suppressions_lookup(merchant_user_id,email_hash,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function mg_campaign_email_hash(string $email): string { return hash('sha256',strtolower(trim($email))); }
function mg_campaign_email_secret(): string
{
    return (string)mg_config_value('security','email_unsubscribe_secret',(string)mg_config_value('app','secret','microgifter-launch-secret'));
}
function mg_campaign_email_token(int $merchantId, ?int $campaignId, string $email, string $scope='campaign'): string
{
    $scope=$scope==='merchant'?'merchant':'campaign';$campaignId=$scope==='merchant'?0:max(0,(int)$campaignId);$hash=mg_campaign_email_hash($email);$body=$merchantId.'|'.$campaignId.'|'.$hash.'|'.$scope;$sig=hash_hmac('sha256',$body,mg_campaign_email_secret());
    return rtrim(strtr(base64_encode(json_encode(['m'=>$merchantId,'c'=>$campaignId,'h'=>$hash,'s'=>$scope,'x'=>$sig],JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');
}
function mg_campaign_email_decode_token(string $token): ?array
{
    $raw=base64_decode(strtr($token,'-_','+/'),true);if(!is_string($raw)||$raw==='')return null;$data=json_decode($raw,true);if(!is_array($data))return null;
    $m=(int)($data['m']??0);$c=(int)($data['c']??0);$h=(string)($data['h']??'');$s=((string)($data['s']??'campaign'))==='merchant'?'merchant':'campaign';$x=(string)($data['x']??'');
    if($m<1||!preg_match('/^[a-f0-9]{64}$/',$h))return null;$body=$m.'|'.($s==='merchant'?0:$c).'|'.$h.'|'.$s;$expected=hash_hmac('sha256',$body,mg_campaign_email_secret());
    return hash_equals($expected,$x)?['merchant_user_id'=>$m,'campaign_id'=>$s==='merchant'?null:$c,'email_hash'=>$h,'scope'=>$s]:null;
}
function mg_campaign_email_unsubscribe_url(int $merchantId, ?int $campaignId, string $email, string $scope='campaign'): string
{
    return rtrim(mg_app_base_url(),'/') . '/api/public/campaigns/unsubscribe.php?token=' . rawurlencode(mg_campaign_email_token($merchantId,$campaignId,$email,$scope));
}
function mg_campaign_email_is_suppressed(PDO $pdo,int $merchantId,?int $campaignId,string $email): bool
{
    try{ if(!$pdo->inTransaction())mg_campaign_email_suppression_install($pdo);$hash=mg_campaign_email_hash($email);$stmt=$pdo->prepare("SELECT COUNT(*) FROM campaign_email_suppressions WHERE merchant_user_id=? AND email_hash=? AND status='active' AND (scope='merchant' OR campaign_id <=> ?)");$stmt->execute([$merchantId,$hash,$campaignId]);return (int)$stmt->fetchColumn()>0; }catch(Throwable){ return false; }
}
function mg_campaign_email_suppress(PDO $pdo,array $decoded,string $reason='unsubscribe'): array
{
    mg_campaign_email_suppression_install($pdo);$stmt=$pdo->prepare("INSERT INTO campaign_email_suppressions (public_id,merchant_user_id,campaign_id,email_hash,scope,reason,status,created_at,updated_at) VALUES (?,?,?,?,?,?,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE reason=VALUES(reason),status='active',updated_at=NOW()");
    $stmt->execute([mg_campaign_email_suppression_uuid(),(int)$decoded['merchant_user_id'],$decoded['campaign_id']??null,(string)$decoded['email_hash'],(string)$decoded['scope'],$reason]);
    return ['suppressed'=>true,'scope'=>(string)$decoded['scope']];
}
