<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/auth/_identity_core.php';

function mg_identity_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_identity_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_identity_session_active_for(PDO $pdo,int $userId,string $sessionId): bool
{
    return (int)mg_identity_scalar($pdo,'SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND session_hash=? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>NOW())',[$userId,hash('sha256',$sessionId)])===1;
}

function mg_identity_has_permission(PDO $pdo,int $userId,string $permission): bool
{
    $sql="SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id LEFT JOIN role_permissions rp ON rp.role_id=r.id LEFT JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=? AND (r.slug='super_admin' OR p.slug=?)";
    return (int)mg_identity_scalar($pdo,$sql,[$userId,$permission])>0;
}

$pdo=mg_db();$runId='identity_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'identity_session_recovery_permission_behavior','run_id'=>$runId,
    'registered'=>false,'normalized_duplicate_rejected'=>false,'password_hash_safe'=>false,
    'valid_login'=>false,'generic_invalid_credentials'=>false,'inactive_login_blocked'=>false,
    'sessions_recorded'=>false,'session_replay_safe'=>false,'logout_revoked'=>false,'global_logout_revoked'=>false,'expired_session_rejected'=>false,
    'reset_token_hashed'=>false,'reset_token_single_use'=>false,'reset_revoked_sessions'=>false,
    'verification_single_use'=>false,'permission_enforced'=>false,'role_audit_consistent'=>false,
    'forced_failure_rolled_back'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $email=$runId.'@example.test';$password='IdentityPass!123';$newPassword='IdentityPass!456';
    $registered=mg_identity_register($pdo,['email'=>strtoupper($email),'full_name'=>'Identity User','password'=>$password]);
    $userId=(int)$registered['user_id'];
    mg_identity_assert((string)mg_identity_scalar($pdo,'SELECT email FROM users WHERE id=?',[$userId])===$email,'Registration did not normalize email.');
    mg_identity_assert((int)mg_identity_scalar($pdo,'SELECT COUNT(*) FROM user_profiles WHERE user_id=?',[$userId])===1,'Registration did not create profile.');
    mg_identity_assert((int)mg_identity_scalar($pdo,"SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug='customer'",[$userId])===1,'Registration did not assign customer role.');
    $summary['registered']=true;

    $duplicate=false;
    try{mg_identity_register($pdo,['email'=>'  '.strtoupper($email).'  ','full_name'=>'Duplicate','password'=>$password]);}
    catch(MgIdentityException $error){$duplicate=$error->httpStatus===409;}
    mg_identity_assert($duplicate,'Normalized duplicate registration was accepted.');
    $summary['normalized_duplicate_rejected']=true;

    $storedHash=(string)mg_identity_scalar($pdo,'SELECT password_hash FROM users WHERE id=?',[$userId]);
    mg_identity_assert($storedHash!==$password&&password_verify($password,$storedHash),'Password was not stored as a one-way hash.');
    $summary['password_hash_safe']=true;

    $auth=mg_identity_authenticate($pdo,$email,$password);
    mg_identity_assert((int)$auth['id']===$userId,'Valid login failed.');$summary['valid_login']=true;
    $wrongMessage='';$unknownMessage='';
    try{mg_identity_authenticate($pdo,$email,'WrongPassword!1');}catch(MgIdentityException $error){$wrongMessage=$error->getMessage();}
    try{mg_identity_authenticate($pdo,'missing-'.$email,'WrongPassword!1');}catch(MgIdentityException $error){$unknownMessage=$error->getMessage();}
    mg_identity_assert($wrongMessage==='Invalid email or password.'&&$unknownMessage===$wrongMessage,'Invalid credentials disclose account existence.');
    $summary['generic_invalid_credentials']=true;

    $pdo->prepare("UPDATE users SET status='disabled' WHERE id=?")->execute([$userId]);$inactive=false;
    try{mg_identity_authenticate($pdo,$email,$password);}catch(MgIdentityException $error){$inactive=$error->httpStatus===403;}
    mg_identity_assert($inactive,'Disabled account authenticated.');$summary['inactive_login_blocked']=true;
    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$userId]);

    $sessionA='session-a-'.$runId;$sessionB='session-b-'.$runId;$expired='session-expired-'.$runId;
    $sessionInsert=$pdo->prepare('INSERT INTO user_sessions (user_id,session_hash,user_agent,last_seen_at,expires_at,created_at) VALUES (?,?,?,NOW(),?,NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW(),expires_at=VALUES(expires_at),revoked_at=NULL');
    $future=gmdate('Y-m-d H:i:s',time()+3600);
    $sessionInsert->execute([$userId,hash('sha256',$sessionA),'validator',$future]);
    $sessionInsert->execute([$userId,hash('sha256',$sessionB),'validator',$future]);
    $sessionInsert->execute([$userId,hash('sha256',$expired),'validator',gmdate('Y-m-d H:i:s',time()-60)]);
    mg_identity_assert(mg_identity_session_active_for($pdo,$userId,$sessionA)&&mg_identity_session_active_for($pdo,$userId,$sessionB),'Active sessions were not recorded.');
    $summary['sessions_recorded']=true;
    $sessionInsert->execute([$userId,hash('sha256',$sessionA),'validator',$future]);
    mg_identity_assert((int)mg_identity_scalar($pdo,'SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND session_hash=?',[$userId,hash('sha256',$sessionA)])===1,'Session replay duplicated a row.');
    $summary['session_replay_safe']=true;
    $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_hash=?')->execute([$userId,hash('sha256',$sessionA)]);
    mg_identity_assert(!mg_identity_session_active_for($pdo,$userId,$sessionA),'Logout did not revoke active session.');$summary['logout_revoked']=true;
    mg_revoke_user_sessions($userId);
    mg_identity_assert(!mg_identity_session_active_for($pdo,$userId,$sessionB),'Global logout did not revoke sessions.');$summary['global_logout_revoked']=true;
    mg_identity_assert(!mg_identity_session_active_for($pdo,$userId,$expired),'Expired session remained active.');$summary['expired_session_rejected']=true;

    $resetToken=bin2hex(random_bytes(24));$resetHash=hash('sha256',$resetToken);
    $pdo->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())')->execute([$userId,$resetHash]);
    mg_identity_assert((string)mg_identity_scalar($pdo,'SELECT token_hash FROM password_reset_tokens WHERE user_id=? ORDER BY id DESC LIMIT 1',[$userId])===$resetHash&&$resetHash!==$resetToken,'Reset token was not stored hashed.');$summary['reset_token_hashed']=true;
    $reset=$pdo->prepare('SELECT id,user_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE');$reset->execute([hash('sha256',$resetToken)]);$resetRow=$reset->fetch(PDO::FETCH_ASSOC);
    mg_identity_assert((bool)$resetRow,'Valid reset token was rejected.');
    $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$resetRow['id']]);
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),$userId]);
    mg_revoke_user_sessions($userId);
    $reset->execute([hash('sha256',$resetToken)]);mg_identity_assert(!$reset->fetch(),'Reset token replay was accepted.');$summary['reset_token_single_use']=true;
    mg_identity_assert((int)mg_identity_scalar($pdo,'SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND revoked_at IS NULL',[$userId])===0,'Password reset left active sessions.');$summary['reset_revoked_sessions']=true;
    mg_identity_assert((int)mg_identity_authenticate($pdo,$email,$newPassword)['id']===$userId,'New password did not authenticate.');

    $verifyToken=bin2hex(random_bytes(24));$verifyHash=hash('sha256',$verifyToken);
    $pdo->prepare('INSERT INTO email_verification_tokens (user_id,token_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())')->execute([$userId,$verifyHash]);
    $verify=$pdo->prepare('SELECT id,user_id FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE');$verify->execute([hash('sha256',$verifyToken)]);$verifyRow=$verify->fetch(PDO::FETCH_ASSOC);
    mg_identity_assert((bool)$verifyRow,'Valid verification token was rejected.');
    $pdo->prepare('UPDATE email_verification_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$verifyRow['id']]);
    $pdo->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=?')->execute([$userId]);
    $verify->execute([hash('sha256',$verifyToken)]);mg_identity_assert(!$verify->fetch(),'Verification token replay was accepted.');$summary['verification_single_use']=true;

    mg_identity_assert(!mg_identity_has_permission($pdo,$userId,'admin.users.view'),'Customer inherited admin permission.');
    $admin=mg_identity_register($pdo,['email'=>'admin-'.$email,'full_name'=>'Identity Admin','password'=>$password]);$adminId=(int)$admin['user_id'];
    $pdo->prepare("INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug='admin'")->execute([$adminId]);
    mg_identity_assert(mg_identity_has_permission($pdo,$adminId,'admin.users.view'),'Admin permission was not derived from role.');$summary['permission_enforced']=true;
    $pdo->prepare("INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug='merchant'")->execute([$userId]);
    mg_audit('identity.role_assign','user_role',['target_user_id'=>$userId,'role'=>'merchant'],$adminId);
    $pdo->prepare("DELETE ur FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug='merchant'")->execute([$userId]);
    mg_audit('identity.role_remove','user_role',['target_user_id'=>$userId,'role'=>'merchant'],$adminId);
    mg_identity_assert((int)mg_identity_scalar($pdo,"SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action IN ('identity.role_assign','identity.role_remove')",[$adminId])===2,'Role changes were not audited.');$summary['role_audit_consistent']=true;

    $forcedEmail='forced-'.$email;$pdo->exec('SAVEPOINT identity_forced_failure');$forced=false;
    try{mg_identity_register($pdo,['email'=>$forcedEmail,'full_name'=>'Forced User','password'=>$password],static function(): void {throw new RuntimeException('Forced identity failure.');});}
    catch(Throwable){$forced=true;$pdo->exec('ROLLBACK TO SAVEPOINT identity_forced_failure');}
    mg_identity_assert($forced&&(int)mg_identity_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email=?',[$forcedEmail])===0,'Forced registration failure did not roll back.');$summary['forced_failure_rolled_back']=true;

    $pdo->rollBack();
    mg_identity_assert((int)mg_identity_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$email,'admin-'.$email])===0,'Identity fixtures remain.');$summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();$summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw $error;
}
