<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_webhook.php';
require_once __DIR__ . '/_package_billing.php';

function mg_subscription_package_webhook_v2_subscription_id(string $type, array $object): string
{
    if (str_starts_with($type, 'customer.subscription.')) {
        return mg_subscription_package_webhook_provider_reference($object['id'] ?? '');
    }
    $direct = mg_subscription_package_webhook_provider_reference($object['subscription'] ?? '');
    if ($direct !== '') return $direct;
    return mg_subscription_package_webhook_provider_reference($object['parent']['subscription_details']['subscription'] ?? '');
}

function mg_subscription_package_webhook_v2_price_id(array $object): string
{
    $items = $object['items']['data'] ?? [];
    if (is_array($items)) {
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $priceId = mg_subscription_package_webhook_provider_reference($item['price'] ?? $item['pricing']['price_details']['price'] ?? '');
            if ($priceId !== '') return $priceId;
        }
    }
    $lines = $object['lines']['data'] ?? [];
    if (is_array($lines)) {
        foreach ($lines as $line) {
            if (!is_array($line)) continue;
            $priceId = mg_subscription_package_webhook_provider_reference($line['price'] ?? $line['pricing']['price_details']['price'] ?? '');
            if ($priceId !== '') return $priceId;
        }
    }
    return '';
}

function mg_subscription_package_webhook_v2_period(array $object): array
{
    $start = mg_subscription_package_webhook_datetime($object['current_period_start'] ?? null);
    $end = mg_subscription_package_webhook_datetime($object['current_period_end'] ?? null);
    if ($start !== null || $end !== null) return [$start, $end];
    $lines = $object['lines']['data'] ?? [];
    if (is_array($lines)) {
        foreach ($lines as $line) {
            if (!is_array($line)) continue;
            $lineStart = mg_subscription_package_webhook_datetime($line['period']['start'] ?? null);
            $lineEnd = mg_subscription_package_webhook_datetime($line['period']['end'] ?? null);
            if ($lineStart !== null || $lineEnd !== null) return [$lineStart, $lineEnd];
        }
    }
    return [null, null];
}

function mg_subscription_package_webhook_v2_status(string $type, array $object, string $fromStatus): string
{
    $providerStatus = strtolower(trim((string)($object['status'] ?? '')));
    if ($type === 'customer.subscription.deleted') return 'canceled';
    if ($type === 'customer.subscription.paused') return 'paused';
    if ($type === 'customer.subscription.resumed') return 'active';
    if ($type === 'invoice.paid') return 'active';
    if (in_array($type, ['invoice.payment_failed','invoice.payment_action_required'], true)) return 'past_due';
    if (str_starts_with($type, 'customer.subscription.')) {
        return match ($providerStatus) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due', 'unpaid' => 'past_due',
            'paused' => 'paused',
            'canceled' => 'canceled',
            'incomplete', 'incomplete_expired' => 'incomplete',
            default => $fromStatus,
        };
    }
    return $fromStatus;
}

function mg_subscription_package_webhook_v2_reconcile_request(PDO $pdo, array $subscriptionRow, string $packageId, string $billingCycle, bool $providerChangeApplied): ?string
{
    $requestId = trim((string)($subscriptionRow['package_change_request_public_id'] ?? ''));
    if ($requestId === '' || !$providerChangeApplied) return $requestId !== '' ? $requestId : null;

    $stmt = $pdo->prepare('SELECT id,requested_package_id,billing_cycle,status FROM subscription_package_change_requests WHERE public_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$requestId,(int)$subscriptionRow['user_id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) return $requestId;

    $expectedPackage = mg_platform_package_slug((string)$request['requested_package_id']);
    $expectedCycle = mg_platform_package_interval_unit((string)($request['billing_cycle'] ?? 'month'));
    if ($expectedPackage === $packageId && $expectedCycle === $billingCycle) {
        $pdo->prepare("UPDATE subscription_package_change_requests SET status='completed',completed_at=COALESCE(completed_at,NOW()),checkout_url=NULL,admin_note=COALESCE(admin_note,'Confirmed by Stripe lifecycle event.'),updated_at=NOW() WHERE id=?")
            ->execute([(int)$request['id']]);
    } elseif (in_array((string)$request['status'], ['pending_payment','approved'], true)) {
        $pdo->prepare("UPDATE subscription_package_change_requests SET status='canceled',checkout_url=NULL,admin_note='Stripe completed a different package or billing cycle than this request.',updated_at=NOW() WHERE id=?")
            ->execute([(int)$request['id']]);
    }
    return $requestId;
}

function mg_subscription_package_webhook_v2_apply_checkout_price(PDO $pdo, string $provider, array $event, ?array $result): ?array
{
    if (!$result || empty($result['processed'])) return $result;
    $object = mg_subscription_package_webhook_object($event);
    $metadata = mg_subscription_package_webhook_metadata($object);
    $requestId = trim((string)($metadata['package_change_request_id'] ?? $object['client_reference_id'] ?? ''));
    $priceId = trim((string)($metadata['provider_price_id'] ?? ''));
    $billingCycle = mg_platform_package_interval_unit((string)($metadata['billing_cycle'] ?? 'month'));
    if ($requestId === '' || $priceId === '') return $result;

    $pdo->prepare("UPDATE platform_account_subscriptions s
      INNER JOIN subscription_package_change_requests r ON r.public_id=? AND r.user_id=s.user_id
      SET s.provider_price_id=?,s.billing_cycle=?,s.updated_at=NOW()
      WHERE s.provider_key=? AND s.package_change_request_public_id=?")
        ->execute([$requestId,$priceId,$billingCycle,$provider,$requestId]);
    $result['billing_cycle'] = $billingCycle;
    return $result;
}

function mg_subscription_package_webhook_v2_apply_schedule_event(PDO $pdo, string $provider, string $eventId, string $type, array $object): ?array
{
    $scheduleId = mg_subscription_package_webhook_provider_reference($object['id'] ?? '');
    $providerSubscriptionId = mg_subscription_package_webhook_provider_reference($object['subscription'] ?? $object['released_subscription'] ?? '');
    if ($scheduleId === '' && $providerSubscriptionId === '') return null;

    $stmt = $pdo->prepare('SELECT * FROM platform_account_subscriptions WHERE provider_key=? AND (provider_schedule_id=? OR provider_subscription_id=?) LIMIT 1 FOR UPDATE');
    $stmt->execute([$provider,$scheduleId,$providerSubscriptionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $clear = in_array($type, ['subscription_schedule.completed','subscription_schedule.released','subscription_schedule.canceled'], true);
    $pdo->prepare('UPDATE platform_account_subscriptions SET provider_schedule_id=?,updated_at=NOW() WHERE id=?')
        ->execute([$clear?null:$scheduleId,(int)$row['id']]);
    mg_platform_account_subscription_event($pdo,(int)$row['id'],'platform_subscription.schedule_lifecycle',(string)$row['status'],(string)$row['status'],(int)$row['user_id'],[
        'provider_key'=>$provider,'provider_event_id'=>$eventId,'event_type'=>$type,'provider_schedule_id'=>$scheduleId,'schedule_cleared'=>$clear,
    ]);
    return [
        'processed'=>true,'duplicate'=>false,'request_id'=>$row['package_change_request_public_id']??null,
        'package_id'=>(string)$row['package_id'],'billing_cycle'=>(string)$row['billing_cycle'],
        'platform_account_subscription_id'=>(string)$row['public_id'],'from_status'=>(string)$row['status'],
        'to_status'=>(string)$row['status'],'lifecycle_event'=>$type,'scheduled_applied'=>false,
    ];
}

function mg_subscription_package_webhook_v2_apply_lifecycle(PDO $pdo, string $provider, string $eventId, string $type, array $object): ?array
{
    $providerSubscriptionId = mg_subscription_package_webhook_v2_subscription_id($type, $object);
    if ($providerSubscriptionId === '') return null;

    $stmt = $pdo->prepare('SELECT * FROM platform_account_subscriptions WHERE provider_key=? AND provider_subscription_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$provider, $providerSubscriptionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $originalPackageId = mg_platform_package_slug((string)$row['package_id']);
    $originalBillingCycle = mg_platform_package_interval_unit((string)$row['billing_cycle']);
    $fromStatus = (string)$row['status'];
    $toStatus = mg_subscription_package_webhook_v2_status($type, $object, $fromStatus);
    $cancelAtPeriodEnd = !empty($object['cancel_at_period_end']) ? 1 : (int)($row['cancel_at_period_end'] ?? 0);
    if ($cancelAtPeriodEnd && in_array($toStatus, ['active','trialing'], true)) $toStatus = 'cancel_pending';

    [$periodStart, $periodEnd] = mg_subscription_package_webhook_v2_period($object);
    $priceId = mg_subscription_package_webhook_v2_price_id($object);
    $mappedPackage = $priceId !== '' ? mg_platform_package_find_by_price_id($pdo, $priceId) : null;
    $packageId = $originalPackageId;
    $billingCycle = $originalBillingCycle;
    $amountCents = (int)$row['amount_cents'];
    $scheduledPackageId = trim((string)($row['scheduled_package_id'] ?? ''));
    $scheduledCycle = trim((string)($row['scheduled_billing_cycle'] ?? ''));
    $scheduledEffectiveAt = trim((string)($row['scheduled_effective_at'] ?? ''));
    $applyScheduled = false;

    if ($scheduledPackageId !== '') {
        $scheduledPackage = mg_platform_package_get($pdo, $scheduledPackageId);
        $scheduledPackageCycle = mg_platform_package_interval_unit($scheduledCycle !== '' ? $scheduledCycle : $billingCycle);
        $mappedPackageId = $mappedPackage ? mg_platform_package_slug((string)$mappedPackage['package_id']) : '';
        $mappedCycle = $mappedPackage ? mg_platform_package_cycle_for_price_id($mappedPackage, $priceId) : '';
        $priceMatchesScheduled = $mappedPackageId === $scheduledPackageId && $mappedCycle === $scheduledPackageCycle;
        $effectiveTimestamp = $scheduledEffectiveAt !== '' ? strtotime($scheduledEffectiveAt) : false;
        $periodStartTimestamp = $periodStart !== null ? strtotime($periodStart) : false;
        $effectiveReached = $effectiveTimestamp !== false && (
            time() >= $effectiveTimestamp
            || ($periodStartTimestamp !== false && $periodStartTimestamp >= $effectiveTimestamp)
        );
        $applyScheduled = $priceMatchesScheduled || $effectiveReached;
        if ($applyScheduled && $scheduledPackage) {
            $packageId = $scheduledPackageId;
            $billingCycle = $scheduledPackageCycle;
            $amountCents = mg_platform_package_amount_cents($scheduledPackage, $billingCycle);
            if ($priceId === '') $priceId = mg_platform_package_stripe_price_id($scheduledPackage, $billingCycle);
            $scheduledPackageId = '';
            $scheduledCycle = '';
            $scheduledEffectiveAt = '';
        }
    } elseif ($mappedPackage) {
        $mappedPackageId = mg_platform_package_slug((string)$mappedPackage['package_id']);
        if ($mappedPackageId !== '') {
            $packageId = $mappedPackageId;
            $billingCycle = mg_platform_package_cycle_for_price_id($mappedPackage, $priceId);
            $amountCents = mg_platform_package_amount_cents($mappedPackage, $billingCycle);
        }
    }

    $providerChangeApplied = $applyScheduled || $packageId !== $originalPackageId || $billingCycle !== $originalBillingCycle;
    $providerStatus = strtolower(trim((string)($object['status'] ?? '')));
    $invoiceId = str_starts_with($type, 'invoice.') ? trim((string)($object['id'] ?? '')) : null;
    $invoiceStatus = str_starts_with($type, 'invoice.') ? trim((string)($object['status'] ?? '')) : null;
    $invoiceUrl = str_starts_with($type, 'invoice.') ? trim((string)($object['hosted_invoice_url'] ?? '')) : null;
    $invoicePdf = str_starts_with($type, 'invoice.') ? trim((string)($object['invoice_pdf'] ?? '')) : null;
    $paymentIntentId = str_starts_with($type, 'invoice.') ? mg_subscription_package_webhook_provider_reference($object['payment_intent'] ?? '') : null;
    $metadata = mg_platform_package_json($row['metadata_json'] ?? null);
    $metadata['last_provider_lifecycle_event'] = [
        'provider'=>$provider,'provider_event_id'=>$eventId,'event_type'=>$type,'provider_status'=>$providerStatus,
        'provider_price_id'=>$priceId?:null,'processed_at'=>gmdate('c'),
    ];

    $pdo->prepare("UPDATE platform_account_subscriptions SET
      package_id=?,billing_cycle=?,status=?,amount_cents=?,provider_price_id=COALESCE(NULLIF(?,''),provider_price_id),
      current_period_start=COALESCE(?,current_period_start),current_period_end=COALESCE(?,current_period_end),
      next_billing_at=CASE WHEN ? IN ('canceled','expired','paused') THEN NULL ELSE COALESCE(?,next_billing_at) END,
      cancel_at_period_end=?,scheduled_package_id=?,scheduled_billing_cycle=?,scheduled_effective_at=?,
      provider_latest_invoice_id=COALESCE(?,provider_latest_invoice_id),provider_latest_invoice_status=COALESCE(?,provider_latest_invoice_status),
      provider_latest_invoice_url=COALESCE(?,provider_latest_invoice_url),provider_latest_invoice_pdf=COALESCE(?,provider_latest_invoice_pdf),
      provider_latest_payment_intent_id=COALESCE(?,provider_latest_payment_intent_id),
      last_payment_at=CASE WHEN ?='invoice.paid' THEN NOW() ELSE last_payment_at END,
      last_payment_failed_at=CASE WHEN ? IN ('invoice.payment_failed','invoice.payment_action_required') THEN NOW() ELSE last_payment_failed_at END,
      canceled_at=CASE WHEN ?='canceled' THEN COALESCE(canceled_at,NOW()) ELSE canceled_at END,
      reactivated_at=CASE WHEN ?='customer.subscription.resumed' THEN NOW() ELSE reactivated_at END,
      metadata_json=?,updated_at=NOW() WHERE id=?")
      ->execute([
          $packageId,$billingCycle,$toStatus,$amountCents,$priceId,$periodStart,$periodEnd,$toStatus,$periodEnd,$cancelAtPeriodEnd,
          $scheduledPackageId!==''?$scheduledPackageId:null,$scheduledCycle!==''?$scheduledCycle:null,$scheduledEffectiveAt!==''?$scheduledEffectiveAt:null,
          $invoiceId?:null,$invoiceStatus?:null,$invoiceUrl?:null,$invoicePdf?:null,$paymentIntentId?:null,
          $type,$type,$toStatus,$type,
          json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$row['id'],
      ]);

    $requestId = mg_subscription_package_webhook_v2_reconcile_request($pdo,$row,$packageId,$billingCycle,$providerChangeApplied);
    mg_platform_account_subscription_event($pdo,(int)$row['id'],'platform_subscription.provider_lifecycle_v2',$fromStatus,$toStatus,(int)$row['user_id'],[
        'provider_key'=>$provider,'provider_event_id'=>$eventId,'event_type'=>$type,'provider_subscription_id'=>$providerSubscriptionId,
        'provider_status'=>$providerStatus,'package_id'=>$packageId,'billing_cycle'=>$billingCycle,'scheduled_applied'=>$applyScheduled,
    ]);

    return [
        'processed'=>true,'duplicate'=>false,'request_id'=>$requestId,
        'package_id'=>$packageId,'billing_cycle'=>$billingCycle,'platform_account_subscription_id'=>(string)$row['public_id'],
        'from_status'=>$fromStatus,'to_status'=>$toStatus,'lifecycle_event'=>$type,'scheduled_applied'=>$applyScheduled,
    ];
}

function mg_subscription_package_webhook_v2_try_process(PDO $pdo, string $provider, array $event): ?array
{
    $type = trim((string)($event['type'] ?? ''));
    $object = mg_subscription_package_webhook_object($event);
    if (in_array($type, [
        'subscription_schedule.created','subscription_schedule.updated','subscription_schedule.completed',
        'subscription_schedule.released','subscription_schedule.canceled',
    ], true)) {
        return mg_subscription_package_webhook_v2_apply_schedule_event($pdo,$provider,(string)($event['id']??''),$type,$object);
    }
    if (in_array($type, [
        'customer.subscription.created','customer.subscription.updated','customer.subscription.deleted',
        'customer.subscription.paused','customer.subscription.resumed','invoice.paid','invoice.payment_failed','invoice.payment_action_required',
    ], true)) {
        return mg_subscription_package_webhook_v2_apply_lifecycle($pdo,$provider,(string)($event['id']??''),$type,$object);
    }
    $result = mg_subscription_package_webhook_try_process($pdo,$provider,$event);
    if (in_array($type, ['checkout.session.completed','checkout.session.async_payment_succeeded'], true)) {
        return mg_subscription_package_webhook_v2_apply_checkout_price($pdo,$provider,$event,$result);
    }
    return $result;
}
