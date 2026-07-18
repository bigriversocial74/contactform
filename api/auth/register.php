<?php
declare(strict_types=1);

require_once __DIR__ . '/_identity_core.php';
require_once dirname(__DIR__) . '/security.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reward-invites.php';
require_once dirname(__DIR__, 2) . '/includes/pricing-packages.php';

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
$email=mg_identity_normalize_email((string)($input['email']??''));
$accountType=strtolower(trim((string)($input['account_type']??'customer'));
if(!in_array($accountType,['customer','merchant'],true))mg_fail('Invalid account type.',422);

$availablePlans=[];
foreach(mg_public_pricing_packages() as $package){
    $id=strtolower(trim((string)($package['id']??'')));
    if($id!=='')$availablePlans[$id]=$package;
}
$selectedPlan=strtolower(trim((string)($input['selected_plan']??'')));
if($accountType==='merchant'&&$selectedPlan==='')$selectedPlan='starter';
if($selectedPlan!==''&&!isset($availablePlans[$selectedPlan]))mg_fail('Selected subscription package is unavailable.',422);
if($selectedPlan!=='')$accountType='merchant';

$ip=mg_client_ip()?:'unknown';
mg_rate_limit('auth.register.ip',$ip,(int)mg_config_value('security','rate_limit_register_max',10),(int)mg_config_value('security','rate_limit_register_window',3600));
if($email!=='')mg_rate_limit('auth.register.email',$email,3,86400);

try{
    $pdo=mg_db();
    $pdo->beginTransaction();
    try{
        /* Every registration starts as the same Free Wallet identity. Merchant roles,
           workspaces, and paid limits are activated only by canonical provider payment
           or an audited complimentary grant. */
        $result=mg_identity_register($pdo,$input);
        $pdo->commit();
    }catch(Throwable $e){
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

    if($selectedPlan==='enterprise'){
        $postVerifyRedirect='/learn-more.php?plan=enterprise&source=signup';
    }elseif($selectedPlan!==''){
        $postVerifyRedirect='/account-subscriptions.php?plan='.rawurlencode($selectedPlan).'&source=signup';
    }else{
        $postVerifyRedirect='/agent.php';
    }

    $_SESSION['mg_pending_subscription_plan']=$selectedPlan!==''?$selectedPlan:null;
    $_SESSION['mg_post_verify_redirect']=$postVerifyRedirect;
    $redirect=mg_email_verification_gate_enabled()?'/verify-email.php?pending=1':$postVerifyRedirect;

    mg_audit('auth.registration_intent','user',[
        'account_type'=>$accountType,
        'selected_plan'=>$selectedPlan!==''?$selectedPlan:null,
        'initial_entitlement'=>'free_wallet',
    ],(int)$result['user_id']);
    mg_event('user.registration_intent',[
        'account_type'=>$accountType,
        'selected_plan'=>$selectedPlan!==''?$selectedPlan:null,
        'initial_entitlement'=>'free_wallet',
    ],(int)$result['user_id']);

    mg_ok([
        'user'=>mg_public_user($user),
        'redirect'=>$redirect,
        'account_type'=>$accountType,
        'selected_plan'=>$selectedPlan!==''?$selectedPlan:null,
        'initial_entitlement'=>'free_wallet',
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
