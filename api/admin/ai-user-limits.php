<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__) . '/subscriptions/_package_billing.php';
require_once dirname(__DIR__, 2) . '/includes/ai/user-credit-service.php';

function mg_admin_ai_access_can_manage(array $user): bool
{
    foreach (['admin.settings.manage','admin.commerce.manage','subscriptions.admin'] as $permission) {
        if (mg_api_user_has_permission($user, $permission)) return true;
    }
    return false;
}

function mg_admin_ai_access_target(PDO $pdo, array $actor, mixed $value): array
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || (int)$id < 1) mg_fail('Choose a valid user.', 422);
    try {
        $target = mg_admin_account_target($pdo, (int)$id);
        mg_admin_account_assert_target_access($actor, $target, true);
        return $target;
    } catch (MgAdminAccountException $error) {
        mg_fail($error->getMessage(), $error->httpStatus());
    }
}

function mg_admin_ai_access_public_user(array $row): array
{
    return [
        'id'=>(int)$row['id'],
        'email'=>(string)$row['email'],
        'display_name'=>(string)($row['display_name'] ?: $row['full_name'] ?: $row['email']),
        'status'=>(string)$row['status'],
        'roles'=>array_values(array_map('strval', is_array($row['roles'] ?? null) ? $row['roles'] : [])),
    ];
}

function mg_admin_ai_access_packages(): array
{
    $packages = [[
        'id'=>'free','name'=>'Free','price_label'=>'$0','billing_label'=>'',
        'monthly_tokens'=>0,'daily_limit'=>0,'weekly_limit'=>0,'monthly_limit'=>0,
    ]];
    foreach (mg_pricing_packages() as $package) {
        $limits = is_array($package['limits'] ?? null) ? $package['limits'] : [];
        $packages[] = [
            'id'=>(string)$package['id'],'name'=>(string)$package['name'],
            'price_label'=>(string)($package['price_label'] ?? ''),'billing_label'=>(string)($package['billing_label'] ?? ''),
            'monthly_tokens'=>$limits['ai_tokens_monthly_included'] ?? 0,
            'daily_limit'=>$limits['ai_tokens_daily_limit'] ?? null,
            'weekly_limit'=>$limits['ai_tokens_weekly_limit'] ?? null,
            'monthly_limit'=>$limits['ai_tokens_monthly_limit'] ?? null,
        ];
    }
    return $packages;
}

function mg_admin_ai_access_package(string $packageId): array
{
    $packageId = mg_package_entitlement_slug($packageId) ?: 'free';
    foreach (mg_admin_ai_access_packages() as $package) {
        if ($package['id'] === $packageId) return $package;
    }
    mg_fail('Choose a valid subscription package.', 422);
}

function mg_admin_ai_access_assign_package(PDO $pdo, array $actor, array $target, string $packageId, string $billingCycle, string $note): array
{
    $package = mg_admin_ai_access_package($packageId);
    $packageId = (string)$package['id'];
    $billingCycle = in_array($billingCycle, ['year','yearly'], true) ? 'year' : 'month';
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $end = $billingCycle === 'year' ? $now->modify('+1 year') : $now->modify('+1 month');
    $metadata = [
        'source'=>'admin_user_ai_access_modal',
        'assigned_by_user_id'=>(int)$actor['id'],
        'assigned_at'=>$now->format(DATE_ATOM),
        'note'=>mb_substr(trim($note),0,500),
        'complimentary'=>true,
    ];
    $existing = mg_platform_account_subscription_snapshot($pdo, (int)$target['id'], true);
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE platform_account_subscriptions SET package_id=?,billing_cycle=?,status='active',amount_cents=0,currency='USD',provider_key='admin',current_period_start=?,current_period_end=?,next_billing_at=?,cancel_at_period_end=0,metadata_json=?,activated_at=COALESCE(activated_at,NOW()),canceled_at=NULL,updated_at=NOW() WHERE id=?");
        $stmt->execute([$packageId,$billingCycle,$now->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$existing['id']]);
        mg_platform_account_subscription_event($pdo,(int)$existing['id'],'platform_subscription.admin_package_assigned',(string)$existing['status'],'active',(int)$actor['id'],$metadata+['from_package_id'=>(string)$existing['package_id'],'package_id'=>$packageId,'provider_key'=>'admin']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO platform_account_subscriptions (public_id,user_id,package_id,billing_cycle,status,amount_cents,currency,provider_key,current_period_start,current_period_end,next_billing_at,cancel_at_period_end,metadata_json,activated_at,created_at,updated_at) VALUES (?,?,?,?, 'active',0,'USD','admin',?,?,?,0,?,NOW(),NOW(),NOW())");
        $stmt->execute([mg_public_uuid(),(int)$target['id'],$packageId,$billingCycle,$now->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        mg_platform_account_subscription_event($pdo,(int)$pdo->lastInsertId(),'platform_subscription.admin_package_assigned',null,'active',(int)$actor['id'],$metadata+['package_id'=>$packageId,'provider_key'=>'admin']);
    }
    if ($packageId !== 'free') mg_platform_account_subscription_grant_merchant_role($pdo,(int)$target['id']);
    return mg_ai_credit_snapshot($pdo,(int)$target['id'],'anthropic');
}

function mg_admin_ai_access_payload(PDO $pdo, array $target): array
{
    $context = mg_ai_credit_package_context($pdo,(int)$target['id']);
    return [
        'user'=>mg_admin_ai_access_public_user($target),
        'credits'=>mg_ai_credit_snapshot($pdo,(int)$target['id'],'anthropic'),
        'package_context'=>[
            'id'=>(string)($context['package_id'] ?? 'free'),
            'name'=>(string)($context['package_name'] ?? 'Free'),
            'status'=>(string)($context['status'] ?? 'free'),
            'billing_cycle'=>$context['billing_cycle'] ?? null,
            'is_paid'=>!empty($context['is_paid']),
        ],
        'packages'=>mg_admin_ai_access_packages(),
        'ledger'=>mg_ai_credit_recent_ledger($pdo,(int)$target['id'],16),
    ];
}

$admin = mg_require_api_user();
if (!mg_admin_ai_access_can_manage($admin)) mg_fail('Permission denied.', 403);
$adminId = (int)$admin['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    mg_rate_limit('admin.ai_user_access.read','user:' . $adminId,120,60);
    $target = mg_admin_ai_access_target($pdo,$admin,$_GET['user_id'] ?? null);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(mg_admin_ai_access_payload($pdo,$target),'AI access loaded.');
}

mg_require_method('POST');
mg_rate_limit('admin.ai_user_access.write','user:' . $adminId,60,300);
$input = mg_input();
mg_require_csrf_for_write($input);
$target = mg_admin_ai_access_target($pdo,$admin,$input['user_id'] ?? null);
$action = strtolower(trim((string)($input['action'] ?? 'save_policy')));
$providerKey = mg_ai_credit_provider_key($input['provider_key'] ?? 'anthropic');
$note = mb_substr(trim((string)($input['note'] ?? '')),0,500);

try {
    $pdo->beginTransaction();
    if ($action === 'save_policy') {
        $credits = mg_ai_credit_save_policy($pdo,(int)$target['id'],$providerKey,$input,$adminId);
        $event = 'admin.ai_user_credit_policy_updated';
        $message = 'AI access policy saved.';
    } elseif ($action === 'grant_tokens') {
        $credits = mg_ai_credit_grant($pdo,(int)$target['id'],$providerKey,(int)($input['tokens'] ?? 0),$adminId,$note);
        $event = 'admin.ai_user_tokens_granted';
        $message = 'AI token credits granted.';
    } elseif ($action === 'assign_package') {
        $credits = mg_admin_ai_access_assign_package($pdo,$admin,$target,(string)($input['package_id'] ?? ''),(string)($input['billing_cycle'] ?? 'month'),$note);
        $event = 'admin.ai_user_package_assigned';
        $message = 'Subscription package assigned.';
    } else {
        throw new InvalidArgumentException('Choose a valid AI access action.');
    }
    $pdo->commit();
    mg_audit($event,'ai_user_credit_account',[
        'target_user_id'=>(int)$target['id'],'provider_key'=>$providerKey,'action'=>$action,
        'package_id'=>(string)($credits['package']['id'] ?? ''),'available_tokens'=>$credits['available_tokens'] ?? null,
    ],$adminId);
    mg_security_log('info',$event,'Admin updated user AI access.',[
        'target_user_id'=>(int)$target['id'],'provider_key'=>$providerKey,'action'=>$action,
    ],$adminId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(mg_admin_ai_access_payload($pdo,$target),$message);
} catch (MgAiCreditException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),$error->httpStatus(),$error->details());
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','admin.ai_user_access.failed','Unable to update user AI access.',[
        'target_user_id'=>(int)$target['id'],'provider_key'=>$providerKey,'action'=>$action,'exception_class'=>$error::class,
    ],$adminId);
    mg_fail('Unable to update user AI access.',500);
}
