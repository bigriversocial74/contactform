<?php
declare(strict_types=1);

require_once __DIR__ . '/_identity_core.php';
require_once dirname(__DIR__) . '/security.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reward-invites.php';

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
$email=mg_identity_normalize_email((string)($input['email']??''));
$accountType=strtolower(trim((string)($input['account_type']??'customer')));
if(!in_array($accountType,['customer','merchant'],true))mg_fail('Invalid account type.',422);
$ip=mg_client_ip()?:'unknown';
mg_rate_limit('auth.register.ip',$ip,(int)mg_config_value('security','rate_limit_register_max',10),(int)mg_config_value('security','rate_limit_register_window',3600));
if($email!=='')mg_rate_limit('auth.register.email',$email,3,86400);

try{
    $pdo=mg_db();
    $pdo->beginTransaction();
    try {
        $result=mg_identity_register($pdo,$input);
        if($accountType==='merchant'){
            $pdo->prepare("INSERT INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug='merchant' ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)")->execute([(int)$result['user_id']]);
            $displayName=trim((string)($input['business_name']??$input['full_name']??'Merchant workspace'))?:'Merchant workspace';
            $workspaceId=mg_uuid();
            $pdo->prepare("INSERT INTO merchant_workspaces (public_id,merchant_user_id,display_name,status,eligibility_status,onboarding_percent,created_at,updated_at) VALUES (?,?,?,'draft','not_started',0,NOW(),NOW()) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),updated_at=NOW()")
                ->execute([$workspaceId,(int)$result['user_id'],$displayName]);
            $workspaceDb=$pdo->prepare('SELECT id FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1');
            $workspaceDb->execute([(int)$result['user_id']]);
            $workspaceDbId=(int)$workspaceDb->fetchColumn();
            if($workspaceDbId<1)throw new RuntimeException('Merchant workspace provisioning failed.');
            $steps=[['business_profile',1],['eligibility',2],['first_location',3],['claim_configuration',4],['first_product',5],['storefront',6],['payment_readiness',7],['test_pppm',8],['test_claim',9],['analytics_verification',10],['beta_readiness',11]];
            $insert=$pdo->prepare("INSERT INTO merchant_onboarding_steps (workspace_id,step_key,step_order,status,created_at,updated_at) VALUES (?,?,?, ?,NOW(),NOW()) ON DUPLICATE KEY UPDATE step_order=VALUES(step_order),updated_at=NOW()");
            foreach($steps as $index=>$step)$insert->execute([$workspaceDbId,$step[0],$step[1],$index===0?'available':'locked']);
            $pdo->prepare('INSERT IGNORE INTO merchant_payment_readiness (workspace_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([$workspaceDbId]);
            $pdo->prepare("INSERT INTO merchant_team_members (public_id,workspace_id,user_id,display_name,role_key,status,invited_by_user_id,invited_at,accepted_at,created_at,updated_at) VALUES (?,?,?,?, 'owner','active',?,NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE role_key='owner',status='active',updated_at=NOW()")
                ->execute([mg_uuid(),$workspaceDbId,(int)$result['user_id'],$displayName,(int)$result['user_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }

    $user=mg_load_user_auth((int)$result['user_id']);
    if(!$user)throw new RuntimeException('Account created but could not be loaded.');
    mg_set_session_user($user,'password');
    mg_rate_limit_clear('auth.register.ip',$ip);
    mg_rate_limit_clear('auth.register.email',$email);
    $verificationSent=mg_queue_verification_email((int)$result['user_id'],$email,(string)($input['full_name']??''));
    if(!$verificationSent)mg_security_log('critical','auth.register_verification_delivery_pending','Account created but verification email was not delivered.',[],(int)$result['user_id']);
    $inviteBridge=mg_crm_reward_invites_link_for_user($pdo,(int)$result['user_id'],$email);
    $postVerifyRedirect=$accountType==='merchant'?'/merchant-onboarding.php':'/inbox.php';
    $_SESSION['mg_post_verify_redirect']=$postVerifyRedirect;
    $redirect=mg_email_verification_gate_enabled()?'/verify-email.php?pending=1':$postVerifyRedirect;
    mg_ok([
        'user'=>mg_public_user($user),
        'redirect'=>$redirect,
        'account_type'=>$accountType,
        'verification_email_sent'=>$verificationSent,
        'crm_reward_invites'=>$inviteBridge,
    ],'Account created.',201);
}catch(MgIdentityException $e){
    mg_security_log('warning','auth.register_rejected',$e->getMessage(),['email'=>$email]);
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    mg_security_log('error','auth.register_error','Registration endpoint failed.',['exception_type'=>get_class($e)]);
    mg_fail('Unable to create account right now.',500);
}
