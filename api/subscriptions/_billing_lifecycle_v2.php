<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';
require_once __DIR__ . '/_package_billing.php';
require_once dirname(__DIR__) . '/payments/_payments.php';
require_once dirname(__DIR__) . '/payments/_stripe.php';

final class MgSubscriptionBillingV2Exception extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_subscription_billing_v2_cycle(mixed $value): string
{
    return mg_platform_package_interval_unit((string)$value);
}

function mg_subscription_billing_v2_request(PDO $pdo, array $user, string $requestedPlanId, string $billingCycle = 'month', string $note = ''): array
{
    mg_platform_package_sync_defaults($pdo);
    $plans = mg_subscription_package_change_plans();
    $requestedPlan = mg_subscription_package_change_plan($plans, $requestedPlanId);
    if (!$requestedPlan) throw new InvalidArgumentException('Selected package is not available.');

    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) throw new RuntimeException('User identity is unavailable.');

    $requestedPackageId = mg_subscription_package_change_slug((string)($requestedPlan['id'] ?? $requestedPlan['name'] ?? ''));
    $package = mg_platform_package_get($pdo, $requestedPackageId);
    if (!$package) throw new InvalidArgumentException('Selected package is not billable yet.');

    $billingCycle = mg_subscription_billing_v2_cycle($billingCycle);
    $canonical = mg_platform_account_subscription_snapshot($pdo, $userId, false);
    $currentPackageId = $canonical && in_array((string)($canonical['status'] ?? ''), MG_SUBSCRIPTION_PACKAGE_CHANGE_ACTIVE_STATUSES, true)
        ? mg_platform_package_slug((string)($canonical['package_id'] ?? 'free'))
        : 'free';
    $currentCycle = $canonical ? mg_subscription_billing_v2_cycle((string)($canonical['billing_cycle'] ?? 'month')) : 'month';

    if ($requestedPackageId === $currentPackageId && $billingCycle === $currentCycle) {
        throw new InvalidArgumentException('That package and billing cycle are already active on your account.');
    }

    $existing = mg_subscription_package_change_latest($pdo, $userId, true);
    if ($existing
        && (string)$existing['requested_package_id'] === $requestedPackageId
        && mg_subscription_billing_v2_cycle((string)($existing['billing_cycle'] ?? 'month')) === $billingCycle) {
        return mg_subscription_package_change_public($existing) + ['duplicate' => true];
    }
    if ($existing) {
        $pdo->prepare("UPDATE subscription_package_change_requests SET status='canceled',admin_note=COALESCE(admin_note,'Replaced by a newer package request.'),updated_at=NOW() WHERE id=? AND status IN ('pending_payment','pending_admin_review','approved')")
            ->execute([(int)$existing['id']]);
    }

    $requestType = 'lateral';
    if ($requestedPackageId === 'enterprise') $requestType = 'enterprise';
    elseif (mg_subscription_package_change_sort_order($plans, $requestedPackageId) > mg_subscription_package_change_sort_order($plans, $currentPackageId)) $requestType = 'upgrade';
    elseif (mg_subscription_package_change_sort_order($plans, $requestedPackageId) < mg_subscription_package_change_sort_order($plans, $currentPackageId)) $requestType = 'downgrade';

    $amountCents = mg_platform_package_amount_cents($package, $billingCycle);
    $currency = strtoupper((string)($package['currency'] ?? 'USD'));
    $selfServe = (int)($package['is_self_serve'] ?? 0) === 1 && (int)($package['requires_admin_review'] ?? 0) !== 1;
    $status = $selfServe && $amountCents > 0 ? 'pending_payment' : 'pending_admin_review';
    $publicId = mg_public_uuid();
    $metadata = [
        'source' => 'account_subscription_v2',
        'requested_plan_name' => (string)($requestedPlan['name'] ?? $requestedPackageId),
        'pricing_package_id' => $requestedPackageId,
        'billing_cycle' => $billingCycle,
        'previous_billing_cycle' => $currentCycle,
        'is_self_serve' => $selfServe,
        'requires_admin_review' => (int)($package['requires_admin_review'] ?? 0) === 1,
    ];

    $pdo->prepare("INSERT INTO subscription_package_change_requests
      (public_id,user_id,current_package_id,requested_package_id,request_type,status,checkout_url,amount_cents,currency,billing_cycle,user_note,metadata_json,created_at,updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([
            $publicId,$userId,$currentPackageId,$requestedPackageId,$requestType,$status,null,$amountCents,$currency,$billingCycle,
            mb_substr($note,0,2000),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);

    $stmt = $pdo->prepare('SELECT * FROM subscription_package_change_requests WHERE public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    mg_audit('subscription.package_change_requested_v2','subscription_package_change_request',[
        'request_id'=>$publicId,'current_package_id'=>$currentPackageId,'requested_package_id'=>$requestedPackageId,
        'request_type'=>$requestType,'status'=>$status,'billing_cycle'=>$billingCycle,
    ],$userId);
    mg_event('subscription.package_change_requested_v2',['request_id'=>$publicId,'requested_package_id'=>$requestedPackageId,'status'=>$status,'billing_cycle'=>$billingCycle],$userId);

    return mg_subscription_package_change_public($row) + ['duplicate' => false];
}

function mg_subscription_billing_v2_stripe_subscription(PDO $pdo, string $subscriptionId): array
{
    $subscriptionId = trim($subscriptionId);
    if ($subscriptionId === '') throw new MgSubscriptionBillingV2Exception('Stripe subscription reference is unavailable.', 409);
    return mg_stripe_api_request($pdo, 'GET', '/v1/subscriptions/' . rawurlencode($subscriptionId));
}

function mg_subscription_billing_v2_subscription_item(array $subscription): array
{
    $items = $subscription['items']['data'] ?? [];
    if (!is_array($items) || !$items || !is_array($items[0] ?? null)) {
        throw new MgSubscriptionBillingV2Exception('Stripe subscription item is unavailable.', 409);
    }
    return $items[0];
}

function mg_subscription_billing_v2_store_price_id(PDO $pdo, string $packageId, string $billingCycle, string $priceId): void
{
    $mode = function_exists('mg_payment_mode') ? mg_payment_mode() : 'test';
    $cycle = mg_subscription_billing_v2_cycle($billingCycle);
    $field = 'stripe_' . $cycle . 'ly_price_id_' . ($mode === 'live' ? 'live' : 'test');
    $allowed = ['stripe_monthly_price_id_test','stripe_monthly_price_id_live','stripe_yearly_price_id_test','stripe_yearly_price_id_live'];
    if (!in_array($field, $allowed, true)) return;
    $pdo->prepare('UPDATE platform_subscription_packages SET `' . $field . '`=?,updated_at=NOW() WHERE package_id=?')
        ->execute([$priceId, mg_platform_package_slug($packageId)]);
}

function mg_subscription_billing_v2_price_id(PDO $pdo, array $package, string $billingCycle): string
{
    $billingCycle = mg_subscription_billing_v2_cycle($billingCycle);
    $priceId = mg_platform_package_stripe_price_id($package, $billingCycle);
    if ($priceId !== '') return $priceId;

    $productId = mg_platform_package_stripe_product_id($package);
    if ($productId === '') {
        throw new MgSubscriptionBillingV2Exception('Stripe product and cycle-specific Price IDs must be configured for package changes.', 503);
    }

    $amount = mg_platform_package_amount_cents($package, $billingCycle);
    if ($amount < 1) throw new MgSubscriptionBillingV2Exception('Selected package does not have a billable amount.', 422);

    $created = mg_stripe_api_request($pdo, 'POST', '/v1/prices', [
        'currency' => strtolower((string)($package['currency'] ?? 'USD')),
        'unit_amount' => $amount,
        'recurring' => ['interval' => $billingCycle],
        'product' => $productId,
        'nickname' => (string)($package['name'] ?? ucfirst((string)$package['package_id'])) . ' ' . ucfirst($billingCycle),
        'metadata' => ['package_id' => (string)$package['package_id'], 'billing_cycle' => $billingCycle, 'source' => 'microgifter_billing_v2'],
    ], 'subscription-price:' . (string)$package['package_id'] . ':' . $billingCycle . ':' . (function_exists('mg_payment_mode') ? mg_payment_mode() : 'test'));

    $priceId = trim((string)($created['id'] ?? ''));
    if ($priceId === '') throw new MgSubscriptionBillingV2Exception('Stripe did not return a Price ID.', 502);
    mg_subscription_billing_v2_store_price_id($pdo, (string)$package['package_id'], $billingCycle, $priceId);
    return $priceId;
}

function mg_subscription_billing_v2_portal_session(PDO $pdo, array $user, ?string $targetPackageId = null, string $billingCycle = 'month'): array
{
    $userId = (int)($user['id'] ?? 0);
    $snapshot = mg_platform_account_subscription_snapshot($pdo, $userId, false);
    if (!$snapshot || (string)($snapshot['provider_key'] ?? '') !== 'stripe') {
        throw new MgSubscriptionBillingV2Exception('A Stripe-backed subscription is required to open billing management.', 409);
    }
    $customerId = trim((string)($snapshot['provider_customer_id'] ?? ''));
    if ($customerId === '') throw new MgSubscriptionBillingV2Exception('Stripe customer reference is unavailable.', 409);

    $params = [
        'customer' => $customerId,
        'return_url' => mg_payment_absolute_url('/account-subscriptions.php?billing=returned'),
    ];

    $targetPackageId = $targetPackageId !== null ? mg_platform_package_slug($targetPackageId) : '';
    if ($targetPackageId !== '') {
        $subscriptionId = trim((string)($snapshot['provider_subscription_id'] ?? ''));
        $subscription = mg_subscription_billing_v2_stripe_subscription($pdo, $subscriptionId);
        $item = mg_subscription_billing_v2_subscription_item($subscription);
        $package = mg_platform_package_get($pdo, $targetPackageId);
        if (!$package) throw new MgSubscriptionBillingV2Exception('Selected package is unavailable.', 422);
        $priceId = mg_subscription_billing_v2_price_id($pdo, $package, $billingCycle);
        $params['flow_data'] = [
            'type' => 'subscription_update_confirm',
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => ['return_url' => mg_payment_absolute_url('/account-subscriptions.php?billing=updated')],
            ],
            'subscription_update_confirm' => [
                'subscription' => $subscriptionId,
                'items' => [[
                    'id' => (string)($item['id'] ?? ''),
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
            ],
        ];
    }

    try {
        $session = mg_stripe_api_request($pdo, 'POST', '/v1/billing_portal/sessions', $params, null);
    } catch (Throwable $error) {
        if ($targetPackageId === '') throw $error;
        $session = mg_stripe_api_request($pdo, 'POST', '/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => mg_payment_absolute_url('/account-subscriptions.php?billing=returned'),
        ], null);
    }

    $url = trim((string)($session['url'] ?? ''));
    if ($url === '') throw new MgSubscriptionBillingV2Exception('Stripe did not return a billing portal URL.', 502);
    return ['url'=>$url,'target_package_id'=>$targetPackageId?:null,'billing_cycle'=>mg_subscription_billing_v2_cycle($billingCycle)];
}

function mg_subscription_billing_v2_schedule_change(PDO $pdo, array $user, array $request): array
{
    $userId = (int)($user['id'] ?? 0);
    $snapshot = mg_platform_account_subscription_snapshot($pdo, $userId, false);
    if (!$snapshot || (string)($snapshot['provider_key'] ?? '') !== 'stripe') {
        throw new MgSubscriptionBillingV2Exception('A Stripe-backed subscription is required to schedule this change.', 409);
    }

    $subscriptionId = trim((string)($snapshot['provider_subscription_id'] ?? ''));
    $subscription = mg_subscription_billing_v2_stripe_subscription($pdo, $subscriptionId);
    $item = mg_subscription_billing_v2_subscription_item($subscription);
    $package = mg_platform_package_get($pdo, (string)$request['requested_package_id']);
    if (!$package) throw new MgSubscriptionBillingV2Exception('Selected package is unavailable.', 422);
    $cycle = mg_subscription_billing_v2_cycle((string)($request['billing_cycle'] ?? 'month'));
    $priceId = mg_subscription_billing_v2_price_id($pdo, $package, $cycle);
    $periodEndTimestamp = (int)($subscription['current_period_end'] ?? 0);
    if ($periodEndTimestamp < 1) {
        $periodEndTimestamp = strtotime((string)($snapshot['current_period_end'] ?? '')) ?: 0;
    }
    if ($periodEndTimestamp < 1) throw new MgSubscriptionBillingV2Exception('Current Stripe billing period is unavailable.', 409);
    $effectiveAt = gmdate('Y-m-d H:i:s', $periodEndTimestamp);

    mg_stripe_api_request($pdo, 'POST', '/v1/subscriptions/' . rawurlencode($subscriptionId), [
        'items' => [[
            'id' => (string)($item['id'] ?? ''),
            'price' => $priceId,
            'quantity' => 1,
        ]],
        'proration_behavior' => 'none',
        'cancel_at_period_end' => false,
        'metadata' => [
            'source_type' => 'subscription_package_change',
            'package_change_request_id' => (string)$request['request_id'],
            'scheduled_package_id' => (string)$request['requested_package_id'],
            'scheduled_billing_cycle' => $cycle,
            'scheduled_effective_at' => gmdate('c', $periodEndTimestamp),
            'user_id' => (string)$userId,
        ],
    ], 'subscription-scheduled-change:' . (string)$request['request_id']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE platform_account_subscriptions
          SET scheduled_package_id=?,scheduled_billing_cycle=?,scheduled_effective_at=?,cancel_at_period_end=0,
              package_change_request_public_id=?,updated_at=NOW()
          WHERE user_id=?")
            ->execute([(string)$request['requested_package_id'],$cycle,$effectiveAt,(string)$request['request_id'],$userId]);

        $stmt = $pdo->prepare('SELECT metadata_json FROM subscription_package_change_requests WHERE public_id=? AND user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(string)$request['request_id'],$userId]);
        $meta = mg_platform_package_json($stmt->fetchColumn());
        $meta['scheduled_provider_change'] = ['provider'=>'stripe','effective_at'=>$effectiveAt,'provider_price_id'=>$priceId,'created_at'=>gmdate('c')];
        $pdo->prepare("UPDATE subscription_package_change_requests SET status='approved',checkout_url=NULL,admin_note='Scheduled for the next billing period.',metadata_json=?,updated_at=NOW() WHERE public_id=? AND user_id=?")
            ->execute([json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(string)$request['request_id'],$userId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $row = mg_subscription_package_change_latest($pdo, $userId, false);
    mg_platform_account_subscription_event($pdo,(int)$snapshot['id'],'platform_subscription.change_scheduled',(string)$snapshot['status'],(string)$snapshot['status'],$userId,[
        'provider_key'=>'stripe','request_id'=>(string)$request['request_id'],'scheduled_package_id'=>(string)$request['requested_package_id'],'scheduled_billing_cycle'=>$cycle,'scheduled_effective_at'=>$effectiveAt,
    ]);
    mg_audit('subscription.change_scheduled','platform_account_subscription',['request_id'=>(string)$request['request_id'],'scheduled_package_id'=>(string)$request['requested_package_id'],'scheduled_billing_cycle'=>$cycle,'scheduled_effective_at'=>$effectiveAt],$userId);

    return $row ? mg_subscription_package_change_public($row) + ['scheduled_effective_at'=>$effectiveAt] : $request + ['scheduled_effective_at'=>$effectiveAt];
}

function mg_subscription_billing_v2_attach_portal(PDO $pdo, array $user, array $request): array
{
    $portal = mg_subscription_billing_v2_portal_session($pdo, $user, (string)$request['requested_package_id'], (string)($request['billing_cycle'] ?? 'month'));
    $pdo->prepare("UPDATE subscription_package_change_requests SET status='pending_payment',checkout_url=?,updated_at=NOW() WHERE public_id=? AND user_id=?")
        ->execute([$portal['url'],(string)$request['request_id'],(int)$user['id']]);
    $row = mg_subscription_package_change_latest($pdo,(int)$user['id'],false);
    return $row ? mg_subscription_package_change_public($row) : $request + ['checkout_url'=>$portal['url']];
}
