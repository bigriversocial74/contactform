<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/intelligence/_intelligence.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-provisioning.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-location-scope.php';

$mgDesignStudioEndpoint=basename((string)($_SERVER['SCRIPT_NAME']??''));
if(in_array($mgDesignStudioEndpoint,['brand-kit.php','design-export.php','design-studio-assets.php','qr-library.php'],true)){
    require_once __DIR__ . '/_design_studio_guard.php';
    if(function_exists('mg_db')&&function_exists('mg_design_studio_require_tables')){
        try{mg_design_studio_require_tables(mg_db(),mg_design_studio_core_tables());}
        catch(Throwable $e){
            if(function_exists('mg_security_log'))mg_security_log('error','merchant.design_studio_setup_check_failed','Design Studio setup check failed.',['exception_type'=>get_class($e)],null);
            if(function_exists('mg_fail'))mg_fail('Design Studio setup is incomplete. Import database/stage_19_design_studio_qr_library.sql before using this endpoint.',503);
            throw $e;
        }
    }
}

function mg_merchant_uuid(): string{return mg_intelligence_uuid();}
function mg_merchant_email_hash(string $email): string
{
    $email=strtolower(trim($email));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))mg_fail('Invalid team email address.',422);
    $secret=trim((string)getenv('MG_MERCHANT_INVITE_SECRET'))?:trim((string)getenv('MG_DISTRIBUTION_HASH_SECRET'));
    if($secret==='')mg_fail('Merchant invitation hashing is not configured.',503);
    return hash_hmac('sha256',$email,$secret);
}
function mg_merchant_package_context(PDO $pdo,array $user): array{return mg_user_package_context($pdo,$user);}
function mg_merchant_require_access(PDO $pdo,array $user): array
{
    return mg_package_require_merchant_access($pdo,$user,'Upgrade to a paid Microgifter package or receive a complimentary subscription to use merchant tools.');
}

function mg_merchant_require_permission(string $permission): array
{
    $user=mg_require_api_user();
    $pdo=mg_db();
    $context=mg_merchant_require_access($pdo,$user);
    $hasPlatformPermission=mg_api_user_has_permission($user,$permission);
    $hasWorkspacePermission=mg_workspace_role_allows_permission($context,$permission);
    if($hasPlatformPermission||$hasWorkspacePermission)return $user;
    mg_audit('permission_denied','security',['permission'=>$permission,'entitlement_source'=>$context['entitlement_source']??null,'workspace_role'=>$context['workspace_role']??null],(int)$user['id']);
    mg_security_log('warning','permission.denied','Merchant permission denied.',['permission'=>$permission,'entitlement_source'=>$context['entitlement_source']??null,'workspace_role'=>$context['workspace_role']??null],(int)$user['id']);
    mg_fail('Merchant permission is not enabled for this account or workspace role.',403);
}

function mg_merchant_prepare_workspace_locations(PDO $pdo,array $workspace): array
{
    $scope=mg_merchant_location_scope_context($workspace);
    mg_merchant_location_normalize_scope(
        $pdo,
        (int)$scope['workspace_id'],
        (int)$scope['owner_merchant_id']
    );
    return $workspace;
}

function mg_merchant_workspace(PDO $pdo,int $userId,bool $forUpdate=false): array
{
    $user=function_exists('mg_load_user_auth')?mg_load_user_auth($userId):['id'=>$userId];
    $context=mg_user_package_context($pdo,is_array($user)?$user:['id'=>$userId]);
    if(!empty($context['workspace_id'])){
        $stmt=$pdo->prepare('SELECT * FROM merchant_workspaces WHERE id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
        $stmt->execute([(int)$context['workspace_id']]);
    }else{
        $ownerUserId=(int)($context['entitlement_user_id']??$userId);
        $stmt=$pdo->prepare('SELECT * FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
        $stmt->execute([$ownerUserId]);
    }
    $workspace=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$workspace)mg_fail('Merchant workspace has not been created.',404);
    return mg_merchant_prepare_workspace_locations($pdo,$workspace);
}

function mg_merchant_ensure_workspace(PDO $pdo,array $user): array
{
    $context=mg_merchant_require_access($pdo,$user);
    if(!empty($context['workspace_id'])){
        $stmt=$pdo->prepare('SELECT * FROM merchant_workspaces WHERE id=? LIMIT 1');
        $stmt->execute([(int)$context['workspace_id']]);
        $workspace=$stmt->fetch(PDO::FETCH_ASSOC);
        if($workspace)return mg_merchant_prepare_workspace_locations($pdo,$workspace);
        mg_fail('Assigned merchant workspace is unavailable.',404);
    }
    $ownerUserId=(int)($context['entitlement_user_id']??$user['id']??0);
    if($ownerUserId!==(int)($user['id']??0))mg_fail('Assigned merchant workspace is unavailable.',404);
    try{
        $workspace=mg_subscription_provision_merchant_workspace($pdo,$ownerUserId,trim((string)($user['display_name']??$user['full_name']??'Merchant workspace')),'merchant_access');
        return mg_merchant_prepare_workspace_locations($pdo,$workspace);
    }catch(Throwable $error){
        mg_security_log('error','merchant.workspace_provision_failed','Unable to initialize merchant workspace.',['exception_class'=>$error::class],$ownerUserId);
        mg_fail('Unable to initialize merchant workspace.',500);
    }
}

function mg_merchant_recalculate_onboarding(PDO $pdo,int $workspaceId): int
{
    $stmt=$pdo->prepare("SELECT COUNT(*) total_steps,SUM(status='completed') completed_steps FROM merchant_onboarding_steps WHERE workspace_id=?");
    $stmt->execute([$workspaceId]);
    $counts=$stmt->fetch(PDO::FETCH_ASSOC)?:['total_steps'=>0,'completed_steps'=>0];
    $percent=(int)round(100*((int)$counts['completed_steps'])/max(1,(int)$counts['total_steps']));
    $pdo->prepare('UPDATE merchant_workspaces SET onboarding_percent=?,updated_at=NOW() WHERE id=?')->execute([$percent,$workspaceId]);
    return $percent;
}
