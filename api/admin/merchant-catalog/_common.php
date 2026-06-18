<?php
declare(strict_types=1);

require_once dirname(__DIR__,2) . '/bootstrap.php';

const MG_ADMIN_MC_DEFAULT_LIMIT=25;
const MG_ADMIN_MC_MAX_LIMIT=50;
const MG_ADMIN_MC_MAX_PAGE=1000;
const MG_ADMIN_MC_SUBJECT_TYPES=['workspace','storefront','product','asset'];

final class MgAdminMerchantCatalogException extends RuntimeException
{
    public function __construct(string $message,private readonly int $httpStatus=422){parent::__construct($message);}
    public function httpStatus(): int{return $this->httpStatus;}
}

function mg_admin_mc_has(array $user,string $permission): bool{return mg_api_user_has_permission($user,$permission);}

function mg_admin_mc_require_user(): array
{
    $user=mg_require_api_user();
    if(!mg_admin_mc_has($user,'admin.merchants.view')&&!mg_admin_mc_has($user,'admin.catalog.view')){
        mg_security_log('warning','admin.merchant_catalog.denied','Merchant catalog operations access denied.',[],(int)$user['id']);
        mg_fail('Permission denied.',403);
    }
    return $user;
}

function mg_admin_mc_text(mixed $value,int $max,bool $required=false): string
{
    $text=preg_replace('/\s+/u',' ',trim((string)$value))??'';
    if(($required&&$text==='')||mb_strlen($text)>$max)throw new MgAdminMerchantCatalogException('Invalid merchant catalog operations input.',422);
    return $text;
}

function mg_admin_mc_reason(mixed $value): string
{
    $reason=mg_admin_mc_text($value,1000,true);
    if(mb_strlen($reason)<8)throw new MgAdminMerchantCatalogException('Provide an action reason of at least 8 characters.',422);
    return $reason;
}

function mg_admin_mc_reference(mixed $value): string
{
    $reference=trim((string)$value);
    if($reference===''||strlen($reference)>190||preg_match('/^[A-Za-z0-9._:-]+$/',$reference)!==1)throw new MgAdminMerchantCatalogException('Invalid merchant catalog subject reference.',422);
    return $reference;
}

function mg_admin_mc_subject_type(mixed $value): string
{
    $type=strtolower(trim((string)$value));
    if(!in_array($type,MG_ADMIN_MC_SUBJECT_TYPES,true))throw new MgAdminMerchantCatalogException('Invalid merchant catalog subject type.',422);
    return $type;
}

function mg_admin_mc_limit(mixed $value): int
{
    $limit=filter_var($value,FILTER_VALIDATE_INT,['options'=>['default'=>MG_ADMIN_MC_DEFAULT_LIMIT]]);
    return max(1,min((int)$limit,MG_ADMIN_MC_MAX_LIMIT));
}

function mg_admin_mc_page(mixed $value): int
{
    $page=filter_var($value,FILTER_VALIDATE_INT,['options'=>['default'=>1]]);
    return max(1,min((int)$page,MG_ADMIN_MC_MAX_PAGE));
}

function mg_admin_mc_user_id(mixed $value): ?int
{
    $raw=trim((string)$value);if($raw==='')return null;
    if(preg_match('/^[1-9][0-9]{0,19}$/',$raw)!==1)throw new MgAdminMerchantCatalogException('Invalid merchant user filter.',422);
    $id=filter_var($raw,FILTER_VALIDATE_INT);if($id===false||$id<1)throw new MgAdminMerchantCatalogException('Invalid merchant user filter.',422);
    return (int)$id;
}

function mg_admin_mc_date(mixed $value): ?string
{
    $value=trim((string)$value);if($value==='')return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
    if(!$date||$date->format('Y-m-d')!==$value)throw new MgAdminMerchantCatalogException('Invalid merchant catalog date filter.',422);
    return $value;
}

function mg_admin_mc_one(PDO $pdo,string $sql,array $params=[]): ?array
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
}

function mg_admin_mc_all(PDO $pdo,string $sql,array $params=[]): array
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}

function mg_admin_mc_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_admin_mc_event(PDO $pdo,string $type,string $reference,string $action,?string $from,?string $to,int $actorId,string $reason,array $metadata=[]): void
{
    $pdo->prepare('INSERT INTO merchant_catalog_operation_events (public_id,subject_type,subject_reference,action_type,from_status,to_status,actor_user_id,reason,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$type,$reference,$action,$from,$to,$actorId,mb_substr($reason,0,1000),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}

function mg_admin_mc_events(PDO $pdo,string $type,string $reference): array
{
    return mg_admin_mc_all($pdo,'SELECT e.public_id,e.action_type,e.from_status,e.to_status,e.reason,e.metadata_json,e.created_at,e.actor_user_id,COALESCE(u.display_name,u.full_name,u.email) actor_name FROM merchant_catalog_operation_events e INNER JOIN users u ON u.id=e.actor_user_id WHERE e.subject_type=? AND e.subject_reference=? ORDER BY e.created_at DESC,e.id DESC LIMIT 100',[$type,$reference]);
}
