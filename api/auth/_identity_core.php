<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

final class MgIdentityException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_identity_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function mg_identity_register(PDO $pdo,array $input,?callable $failureHook=null): array
{
    $email=mg_identity_normalize_email((string)($input['email']??''));
    $fullName=trim((string)($input['full_name']??''));
    $password=(string)($input['password']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new MgIdentityException('Enter a valid email address.',422);
    if($fullName==='')throw new MgIdentityException('Full name is required.',422);
    if(strlen($password)<12)throw new MgIdentityException('Password must be at least 12 characters.',422);

    $owns=!$pdo->inTransaction();
    if($owns)$pdo->beginTransaction();
    try{
        $find=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1 FOR UPDATE');
        $find->execute([$email]);
        if($find->fetch())throw new MgIdentityException('An account already exists for this email.',409);

        $hash=password_hash($password,PASSWORD_DEFAULT);
        if(!is_string($hash)||$hash==='')throw new MgIdentityException('Unable to secure password.',500);
        $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?,'active',NOW(),NOW())")
            ->execute([$email,$hash,$fullName,$fullName]);
        $userId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug='customer' ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)")
            ->execute([$userId]);
        $pdo->prepare('INSERT IGNORE INTO user_profiles (user_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([$userId]);
        mg_audit('auth.register','user',['email'=>$email],$userId);
        mg_event('user.registered',['email'=>$email],$userId);
        if($failureHook)$failureHook('before_complete',['user_id'=>$userId]);
        if($owns)$pdo->commit();
        return ['user_id'=>$userId,'email'=>$email,'password_hash'=>$hash];
    }catch(Throwable $e){
        if($owns&&$pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function mg_identity_authenticate(PDO $pdo,string $email,string $password): array
{
    $email=mg_identity_normalize_email($email);
    $stmt=$pdo->prepare('SELECT id,email,password_hash,status FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user||!password_verify($password,(string)$user['password_hash'])){
        throw new MgIdentityException('Invalid email or password.',401);
    }
    if((string)$user['status']!=='active')throw new MgIdentityException('This account is not active.',403);
    return $user;
}
