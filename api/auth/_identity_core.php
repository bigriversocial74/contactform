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

function mg_identity_validate_password(string $password): void
{
    if (strlen($password) < 12) throw new MgIdentityException('Password must be at least 12 characters.', 422);
    if (strlen($password) > 4096) throw new MgIdentityException('Password is too long.', 422);
}

function mg_identity_register(PDO $pdo,array $input,?callable $failureHook=null): array
{
    $email=mg_identity_normalize_email((string)($input['email']??''));
    $fullName=trim((string)($input['full_name']??''));
    $password=(string)($input['password']??'');
    $passwordConfirmation=(string)($input['password_confirmation']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new MgIdentityException('Enter a valid email address.',422);
    if($fullName==='')throw new MgIdentityException('Full name is required.',422);
    mg_identity_validate_password($password);
    if ($passwordConfirmation === '' || !hash_equals($password, $passwordConfirmation)) {
        throw new MgIdentityException('Passwords do not match.', 422);
    }
    if (hash_equals(strtolower($password), strtolower($email))) throw new MgIdentityException('Choose a password that is different from your email.', 422);

    $owns=!$pdo->inTransaction();
    if($owns)$pdo->beginTransaction();
    try{
        $find=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1 FOR UPDATE');
        $find->execute([$email]);
        if($find->fetch())throw new MgIdentityException('An account already exists for this email.',409);

        $hash=password_hash($password,PASSWORD_DEFAULT);
        if(!is_string($hash)||$hash==='')throw new MgIdentityException('Unable to secure password.',500);
        if (mg_identity_schema_has_column('users', 'auth_version')) {
            $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,auth_version,password_changed_at,created_at,updated_at) VALUES (?,?,?,?,'active',1,NOW(),NOW(),NOW())")
                ->execute([$email,$hash,$fullName,$fullName]);
        } else {
            $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?,'active',NOW(),NOW())")
                ->execute([$email,$hash,$fullName,$fullName]);
        }
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

function mg_identity_dummy_password_hash(): string
{
    static $hash = null;
    if (!is_string($hash)) $hash = password_hash('Microgifter dummy authentication value 2026!', PASSWORD_DEFAULT);
    return $hash;
}

function mg_identity_authenticate(PDO $pdo,string $email,string $password): array
{
    $email=mg_identity_normalize_email($email);
    $columns = 'id,email,password_hash,status';
    if (mg_identity_schema_has_column('users', 'auth_version')) $columns .= ',auth_version';
    $stmt=$pdo->prepare("SELECT {$columns} FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    $storedHash = is_array($user) ? (string) ($user['password_hash'] ?? '') : mg_identity_dummy_password_hash();
    $valid = password_verify($password, $storedHash);
    if(!$user||!$valid){
        throw new MgIdentityException('Invalid email or password.',401);
    }
    if((string)$user['status']!=='active')throw new MgIdentityException('This account is not active.',403);
    if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
        $replacement = password_hash($password, PASSWORD_DEFAULT);
        if (is_string($replacement) && $replacement !== '') {
            $pdo->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([$replacement, (int) $user['id']]);
            $user['password_hash'] = $replacement;
        }
    }
    if (mg_identity_schema_has_column('users', 'last_login_at')) {
        $pdo->prepare('UPDATE users SET last_login_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $user['id']]);
    }
    if (!isset($user['auth_version'])) $user['auth_version'] = 1;
    return $user;
}
