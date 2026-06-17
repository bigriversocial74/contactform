<?php
declare(strict_types=1);

require_once __DIR__ . '/_identity_core.php';
require_once dirname(__DIR__) . '/security.php';

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
$email=mg_identity_normalize_email((string)($input['email']??''));
$ip=mg_client_ip()?:'unknown';
mg_rate_limit('auth.register.ip',$ip,(int)mg_config_value('security','rate_limit_register_max',10),(int)mg_config_value('security','rate_limit_register_window',3600));
if($email!=='')mg_rate_limit('auth.register.email',$email,3,86400);

try{
    $result=mg_identity_register(mg_db(),$input);
    $user=mg_load_user_auth((int)$result['user_id']);
    if(!$user)throw new RuntimeException('Account created but could not be loaded.');
    mg_set_session_user($user);
    mg_rate_limit_clear('auth.register.ip',$ip);
    mg_rate_limit_clear('auth.register.email',$email);
    mg_queue_verification_email((int)$result['user_id'],$email,(string)($input['full_name']??''));
    mg_ok(['user'=>mg_public_user($user),'redirect'=>'/inbox.php'],'Account created.',201);
}catch(MgIdentityException $e){
    mg_security_log('warning','auth.register_rejected',$e->getMessage(),['email'=>$email]);
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    mg_security_log('error','auth.register_error','Registration endpoint failed.',['exception_type'=>get_class($e)]);
    mg_fail('Unable to create account right now.',500);
}