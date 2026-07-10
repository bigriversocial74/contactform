<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function lqr_auth_db(): PDO { return lqr_sql_db(lqr_config()); }
function lqr_auth_now(): string { return gmdate('Y-m-d H:i:s'); }
function lqr_auth_ip_hash(): string { return hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown')); }
function lqr_auth_email_hash(string $email): string { return hash('sha256',strtolower(trim($email))); }
function lqr_auth_generic_recovery_message(): string { return 'If an account matches that email, a secure recovery message has been prepared.'; }
function lqr_auth_password_valid(string $password): bool { return strlen($password)>=12 && preg_match('/[A-Z]/',$password) && preg_match('/[a-z]/',$password) && preg_match('/\d/',$password); }
function lqr_auth_require_password(string $password,string $confirmation=''): void {
    if(!lqr_auth_password_valid($password)) throw new RuntimeException('Use at least 12 characters with uppercase, lowercase, and a number.');
    if($confirmation!=='' && !hash_equals($password,$confirmation)) throw new RuntimeException('Password confirmation does not match.');
}
function lqr_auth_user_by_email(string $email): ?array {
    $stmt=lqr_auth_db()->prepare('SELECT * FROM lqr_users WHERE email=? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $row=$stmt->fetch(); return is_array($row)?$row:null;
}
function lqr_auth_user_by_id(string $id): ?array {
    $stmt=lqr_auth_db()->prepare('SELECT * FROM lqr_users WHERE public_id=? LIMIT 1');$stmt->execute([$id]);$row=$stmt->fetch();return is_array($row)?$row:null;
}
function lqr_auth_guard_login(string $email): void {
    $pdo=lqr_auth_db();$cutoff=gmdate('Y-m-d H:i:s',time()-900);
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM lqr_participant_login_attempts WHERE email_hash=? AND ip_hash=? AND succeeded=0 AND attempted_at>=?');
    $stmt->execute([lqr_auth_email_hash($email),lqr_auth_ip_hash(),$cutoff]);
    if((int)$stmt->fetchColumn()>=5) throw new RuntimeException('Too many sign-in attempts. Try again in 15 minutes.');
}
function lqr_auth_note_login(string $email,bool $success): void {
    $pdo=lqr_auth_db();$stmt=$pdo->prepare('INSERT INTO lqr_participant_login_attempts (email_hash,ip_hash,attempted_at,succeeded) VALUES (?,?,NOW(),?)');$stmt->execute([lqr_auth_email_hash($email),lqr_auth_ip_hash(),$success?1:0]);
    $pdo->exec("DELETE FROM lqr_participant_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
}
function lqr_auth_issue_token(string $userId,string $purpose,int $ttlMinutes): string {
    $pdo=lqr_auth_db();$raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);
    $pdo->prepare('UPDATE lqr_participant_auth_tokens SET used_at=NOW() WHERE user_public_id=? AND purpose=? AND used_at IS NULL')->execute([$userId,$purpose]);
    $stmt=$pdo->prepare('INSERT INTO lqr_participant_auth_tokens (user_public_id,purpose,token_hash,expires_at,created_at) VALUES (?,?,?,?,NOW())');
    $stmt->execute([$userId,$purpose,$hash,gmdate('Y-m-d H:i:s',time()+($ttlMinutes*60))]);return $raw;
}
function lqr_auth_consume_token(string $raw,string $purpose): ?array {
    if(!preg_match('/^[a-f0-9]{64}$/',$raw)) return null;$hash=hash('sha256',$raw);$pdo=lqr_auth_db();
    $stmt=$pdo->prepare('SELECT * FROM lqr_participant_auth_tokens WHERE token_hash=? AND purpose=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE');
    $pdo->beginTransaction();try{$stmt->execute([$hash,$purpose]);$row=$stmt->fetch();if(!is_array($row)){$pdo->rollBack();return null;}$pdo->prepare('UPDATE lqr_participant_auth_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$row['id']]);$pdo->commit();return $row;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function lqr_auth_url(string $page,string $token): string { return rtrim((string)(lqr_config()['app_public_url']??''),'/').'/'.$page.'?token='.rawurlencode($token); }
function lqr_auth_deliver_link(string $email,string $subject,string $url): void {
    $config=lqr_config();$from=(string)($config['auth']['mail_from']??'no-reply@localhost');$body=$subject."\n\n".$url."\n\nThis link expires automatically.";
    if(!empty($config['auth']['mail_enabled'])) @mail($email,$subject,$body,'From: '.$from);
    $_SESSION['lqr_auth_preview_link']=$url;
}
function lqr_auth_register(string $name,string $email,string $password,string $confirmation): array {
    $name=trim($name);$email=strtolower(trim($email));if($name==='')throw new RuntimeException('Enter your name.');if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');lqr_auth_require_password($password,$confirmation);
    if(lqr_auth_user_by_email($email))throw new RuntimeException('That email already has an account. Sign in instead.');
    $id='user_'.bin2hex(random_bytes(8));$external=lqr_external_user_id($id,$email);$pdo=lqr_auth_db();
    $stmt=$pdo->prepare("INSERT INTO lqr_users (public_id,display_name,email,password_hash,external_user_id,link_status,session_version,created_at,updated_at) VALUES (?,?,?,?,?,'not_linked',1,NOW(),NOW())");$stmt->execute([$id,$name,$email,password_hash($password,PASSWORD_DEFAULT),$external]);
    $token=lqr_auth_issue_token($id,'email_verification',1440);lqr_auth_deliver_link($email,'Verify your Microgifter Local Quest account',lqr_auth_url('verify-email.php',$token));
    session_regenerate_id(true);$_SESSION['lqr_auth_user_id']=$id;$_SESSION['lqr_auth_session_version']=1;return lqr_auth_user_by_id($id)??[];
}
function lqr_auth_login(string $email,string $password): array {
    $email=strtolower(trim($email));lqr_auth_guard_login($email);$user=lqr_auth_user_by_email($email);
    if(!$user||!password_verify($password,(string)$user['password_hash'])){lqr_auth_note_login($email,false);throw new RuntimeException('Email or password is incorrect.');}
    lqr_auth_note_login($email,true);$pdo=lqr_auth_db();$pdo->prepare('UPDATE lqr_users SET last_login_at=NOW(),updated_at=NOW() WHERE public_id=?')->execute([(string)$user['public_id']]);
    session_regenerate_id(true);$_SESSION['lqr_auth_user_id']=(string)$user['public_id'];$_SESSION['lqr_auth_session_version']=(int)($user['session_version']??1);return $user;
}
function lqr_auth_request_reset(string $email): void {$user=lqr_auth_user_by_email($email);if(!$user)return;$token=lqr_auth_issue_token((string)$user['public_id'],'password_reset',30);lqr_auth_deliver_link((string)$user['email'],'Reset your Microgifter Local Quest password',lqr_auth_url('reset-password.php',$token));}
function lqr_auth_reset_password(string $token,string $password,string $confirmation): void {lqr_auth_require_password($password,$confirmation);$row=lqr_auth_consume_token($token,'password_reset');if(!$row)throw new RuntimeException('This password-reset link is invalid or expired.');$stmt=lqr_auth_db()->prepare('UPDATE lqr_users SET password_hash=?,password_changed_at=NOW(),session_version=session_version+1,updated_at=NOW() WHERE public_id=?');$stmt->execute([password_hash($password,PASSWORD_DEFAULT),(string)$row['user_public_id']]);unset($_SESSION['lqr_auth_user_id'],$_SESSION['lqr_auth_session_version']);session_regenerate_id(true);}
function lqr_auth_verify_email(string $token): bool {$row=lqr_auth_consume_token($token,'email_verification');if(!$row)return false;lqr_auth_db()->prepare('UPDATE lqr_users SET email_verified_at=COALESCE(email_verified_at,NOW()),updated_at=NOW() WHERE public_id=?')->execute([(string)$row['user_public_id']]);return true;}
function lqr_auth_resend_verification(string $email): void {$user=lqr_auth_user_by_email($email);if(!$user||!empty($user['email_verified_at']))return;$token=lqr_auth_issue_token((string)$user['public_id'],'email_verification',1440);lqr_auth_deliver_link((string)$user['email'],'Verify your Microgifter Local Quest account',lqr_auth_url('verify-email.php',$token));}
function lqr_auth_current_row(): ?array {$id=(string)($_SESSION['lqr_auth_user_id']??'');if($id==='')return null;$user=lqr_auth_user_by_id($id);if(!$user)return null;if((int)($_SESSION['lqr_auth_session_version']??0)!==(int)($user['session_version']??1)){unset($_SESSION['lqr_auth_user_id'],$_SESSION['lqr_auth_session_version']);return null;}return $user;}
function lqr_auth_require_user(): array {$user=lqr_auth_current_row();if(!$user){header('Location: signin.php');exit;}return $user;}
function lqr_auth_change_password(array $user,string $current,string $password,string $confirmation): void {if(!password_verify($current,(string)$user['password_hash']))throw new RuntimeException('Current password is incorrect.');lqr_auth_require_password($password,$confirmation);$stmt=lqr_auth_db()->prepare('UPDATE lqr_users SET password_hash=?,password_changed_at=NOW(),session_version=session_version+1,updated_at=NOW() WHERE public_id=?');$stmt->execute([password_hash($password,PASSWORD_DEFAULT),(string)$user['public_id']]);$_SESSION['lqr_auth_session_version']=(int)($user['session_version']??1)+1;session_regenerate_id(true);}
function lqr_auth_logout(): void {$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();}
