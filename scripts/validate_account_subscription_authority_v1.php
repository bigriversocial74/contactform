<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$failures=[];

$assertContains=static function(string $path,array $needles)use($root,&$failures):void{
    $full=$root.'/'.$path;
    $content=is_file($full)?(string)file_get_contents($full):'';
    if($content===''){$failures[]=$path.': missing or empty';return;}
    foreach($needles as $needle){if(!str_contains($content,$needle))$failures[]=$path.': missing marker '.$needle;}
};

$assertNotContains=static function(string $path,array $needles)use($root,&$failures):void{
    $full=$root.'/'.$path;
    $content=is_file($full)?(string)file_get_contents($full):'';
    foreach($needles as $needle){if(str_contains($content,$needle))$failures[]=$path.': forbidden legacy marker '.$needle;}
};

$assertContains('signup.php',['name="selected_plan"','Every account begins with the Free Wallet','Create account and continue']);
$assertContains('api/auth/register.php',['initial_entitlement','mg_pending_subscription_plan','account-subscriptions.php?plan=','Every registration starts as the same Free Wallet identity']);
$assertNotContains('api/auth/register.php',["WHERE slug='merchant'",'INSERT INTO merchant_workspaces']);
$assertContains('includes/package-entitlements.php',["'package_name' => 'Free Wallet'",'workspace_subscription','mg_workspace_role_allows_permission','is_complimentary']);
$assertNotContains('includes/package-entitlements.php',["'status' => 'legacy_role'"]);
$assertContains('api/merchant/_merchant.php',['$hasPlatformPermission','$hasWorkspacePermission','mg_merchant_require_access']);
$assertContains('api/admin/subscription-grants.php',["mg_require_permission('admin.subscriptions.manage')","action === 'grant'","action === 'revoke'"]);
$assertContains('api/admin/_subscription_grants.php',['platform_complimentary_subscription_grants',"provider_key='admin_grant'",'role_assignments_preserved','mg_subscription_provision_merchant_workspace']);
$assertContains('api/subscriptions/_package_billing.php',['mg_subscription_provision_merchant_workspace','mg_platform_account_subscription_grant_merchant_role']);
$assertContains('api/subscriptions/_package_webhook.php',['platform_account_subscriptions','mg_subscription_package_webhook_activate']);
$assertContains('api/subscriptions/_package_webhook_v2.php',['customer.subscription.updated','invoice.payment_failed','platform_subscription.provider_lifecycle_v2','mg_subscription_package_webhook_v2_try_process']);
$assertContains('api/subscriptions/_stripe_webhook.php',['mg_subscription_package_webhook_v2_try_process','platform_account_subscription_id']);
$assertContains('includes/account/subscription-authority.php',['Free Wallet','No renewal','No charge',"'promotions_used'=>0"]);
$assertContains('assets/js/subscription-activation-status.js',['Continue to secure checkout','Your Free Wallet is active','Complimentary']);
$assertContains('includes/footer.php',['/assets/js/admin-subscription-grants.js?v=1.0.0']);
$assertContains('config/migrations.php',['stage_18ai_account_subscription_authority.sql']);
$assertContains('database/stage_18ai_account_subscription_authority.sql',['platform_complimentary_subscription_grants','admin.subscriptions.manage','stage_18ai_account_subscription_authority']);

if($failures){fwrite(STDERR,"Account Subscription Authority v1 validation failed:\n- ".implode("\n- ",$failures)."\n");exit(1);}
echo "Account Subscription Authority v1 validation passed.\n";
