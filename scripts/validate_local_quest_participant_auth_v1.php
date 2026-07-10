<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$required=[
'examples/local-quest-rewards/participant-auth.php','examples/local-quest-rewards/auth-view.php','examples/local-quest-rewards/assets/auth.css','examples/local-quest-rewards/signin.php','examples/local-quest-rewards/signup.php','examples/local-quest-rewards/forgot-password.php','examples/local-quest-rewards/reset-password.php','examples/local-quest-rewards/verify-email.php','examples/local-quest-rewards/resend-verification.php','examples/local-quest-rewards/profile.php','examples/local-quest-rewards/account-security.php','examples/local-quest-rewards/logout.php','examples/local-quest-rewards/database/local_quest_participant_auth_v1.sql'];
$read=static fn(string $p):string=>is_file($root.'/'.$p)?(string)file_get_contents($root.'/'.$p):'';
$checks=[];foreach($required as $p)$checks[]=['name'=>'file:'.$p,'ok'=>is_file($root.'/'.$p)];
$service=$read('examples/local-quest-rewards/participant-auth.php');$schema=$read('examples/local-quest-rewards/database/local_quest_participant_auth_v1.sql');$signin=$read('examples/local-quest-rewards/signin.php');$signup=$read('examples/local-quest-rewards/signup.php');$forgot=$read('examples/local-quest-rewards/forgot-password.php');$profile=$read('examples/local-quest-rewards/profile.php');$installer=$read('examples/local-quest-rewards/install-functions.php');$workflow=$read('.github/workflows/local-quest-checks.yml');
$checks[]=['name'=>'generic login errors and throttling','ok'=>str_contains($service,'Too many sign-in attempts')&&str_contains($service,'Email or password is incorrect.')&&str_contains($service,'lqr_participant_login_attempts')];
$checks[]=['name'=>'strong password policy','ok'=>str_contains($service,'strlen($password)>=12')&&str_contains($signup,'password_confirmation')&&str_contains($service,'Password confirmation does not match')];
$checks[]=['name'=>'secure token lifecycle','ok'=>str_contains($service,'random_bytes(32)')&&str_contains($service,"hash('sha256',\$raw)")&&str_contains($service,'used_at IS NULL')&&str_contains($service,'expires_at>NOW()')&&str_contains($service,'FOR UPDATE')];
$checks[]=['name'=>'enumeration-safe recovery','ok'=>str_contains($forgot,'response is the same')&&str_contains($service,'If an account matches that email')];
$checks[]=['name'=>'session hardening','ok'=>substr_count($service,'session_regenerate_id(true)')>=3&&str_contains($service,'session_version=session_version+1')];
$checks[]=['name'=>'email verification','ok'=>str_contains($service,'email_verification')&&str_contains($service,'email_verified_at')&&str_contains($profile,'Verification required')];
$checks[]=['name'=>'Microgifter account link entry','ok'=>str_contains($profile,'lqr_action_start_account_link')&&str_contains($profile,'Connect Microgifter')];
$checks[]=['name'=>'dedicated public auth routes','ok'=>str_contains($signin,'forgot-password.php')&&str_contains($signin,'signup.php')&&str_contains($profile,'account-security.php')&&str_contains($profile,'logout.php')];
$checks[]=['name'=>'SQL auth package','ok'=>str_contains($schema,'lqr_participant_auth_tokens')&&str_contains($schema,'lqr_participant_login_attempts')&&str_contains($schema,'session_version')];
$checks[]=['name'=>'fresh installer includes auth','ok'=>str_contains($installer,'local_quest_participant_auth_v1.sql')&&str_contains($installer,'lqr_participant_auth_tokens')&&str_contains($installer,'All 16 required tables')];
$checks[]=['name'=>'CI auth contract','ok'=>str_contains($workflow,'validate_local_quest_participant_auth_v1.php')];
$failed=array_values(array_filter($checks,static fn(array $c):bool=>!$c['ok']));$score=max(0,10-(count($failed)*.5));$result=['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($failed===[]?0:1);
