<?php
declare(strict_types=1);
require_once __DIR__ . '/_identity_core.php';
require_once dirname(__DIR__) . '/security.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reward-invites.php';
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
$email=mg_identity_normalize_email((string)($input['email']??''));
$password=(string)($input['password']??'');
$ip=mg_client_ip()?:'unknown';
mg_rate_limit('auth.login.ip',$ip,(int)mg_config_value('security','rate_limit_login_max',8),(int)mg_config_value('security','rate_limit_login_window',900));
if($email!=='')mg_rate_limit('auth.login.email',$email,(int)mg_config_value('security','rate_limit_login_max',8),(int)mg_config_value('security','rate_limit_login_window',900));
try{
    $pdo=mg_db();
    $found=mg_identity_authenticate($pdo,$email,$password);
    $user=mg_load_user_auth((int)$found['id']);
    if(!$user)throw new RuntimeException('Unable to load account.');
    mg_set_session_user($user);
    mg_rate_limit_clear('auth.login.ip',$ip);
    mg_rate_limit_clear('auth.login.email',$email);
    mg_audit('auth.login','user',[],(int)$user['id']);
    mg_event('user.logged_in',[],(int)$user['id']);
    $inviteBridge=mg_crm_reward_invites_link_for_user($pdo,(int)$user['id'],$email);
    mg_ok(['user'=>mg_public_user($user),'redirect'=>'/inbox.php','crm_reward_invites'=>$inviteBridge],'Signed in.');
}catch(MgIdentityException $e){
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    unset($_SESSION['mg_user']);
    mg_fail('Unable to sign in right now.',500);
}