<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_billing.php';

function mg_subscription_history_label(string $eventType, array $payload, ?string $toStatus): string
{
    $providerType = strtolower(trim((string)($payload['event_type'] ?? '')));
    if ($eventType === 'platform_subscription.payment_received' || $providerType === 'invoice.paid') return 'Payment received';
    if ($eventType === 'platform_subscription.payment_attention_required' || in_array($providerType, ['invoice.payment_failed', 'invoice.payment_action_required'], true)) return 'Payment needs attention';
    if ($eventType === 'platform_subscription.checkout_completed') return 'Checkout completed';
    if ($providerType === 'customer.subscription.deleted') return 'Subscription canceled';
    if ($providerType === 'customer.subscription.resumed') return 'Subscription reactivated';
    if ($eventType === 'platform_subscription.checkout_return_confirmed') return 'Checkout confirmed';
    if ($eventType === 'platform_subscription.activated') return 'Subscription activated';
    if ($eventType === 'platform_subscription.package_changed') return 'Package changed';
    if ($eventType === 'platform_subscription.schedule_lifecycle') return 'Scheduled change updated';
    if ($eventType === 'platform_subscription.provider_lifecycle_v2') return 'Subscription updated';
    if ($toStatus !== null && $toStatus !== '') return 'Status changed to ' . ucwords(str_replace('_', ' ', $toStatus));
    return 'Subscription activity';
}

function mg_subscription_history_tone(string $eventType, array $payload, ?string $toStatus): string
{
    $providerType = strtolower(trim((string)($payload['event_type'] ?? '')));
    if ($eventType === 'platform_subscription.payment_attention_required' || in_array($providerType, ['invoice.payment_failed', 'invoice.payment_action_required'], true)) return 'warning';
    if (in_array((string)$toStatus, ['past_due', 'paused', 'incomplete'], true)) return 'warning';
    if (in_array((string)$toStatus, ['canceled', 'expired'], true)) return 'muted';
    if ($eventType === 'platform_subscription.payment_received' || $providerType === 'invoice.paid' || in_array($eventType, ['platform_subscription.activated', 'platform_subscription.checkout_return_confirmed', 'platform_subscription.checkout_completed'], true)) return 'success';
    return 'info';
}

mg_require_method('GET');
$user = mg_require_api_user();

try {
    $pdo = mg_db();
    $userId = (int)($user['id'] ?? 0);
    $snapshot = mg_platform_account_subscription_snapshot($pdo, $userId, false);
    $history = [];

    if ($snapshot) {
        $stmt = $pdo->prepare("SELECT e.event_type,e.from_status,e.to_status,e.provider_key,e.provider_event_id,e.payload_json,e.created_at
            FROM platform_subscription_events e
            INNER JOIN platform_account_subscriptions s ON s.id=e.account_subscription_id
            WHERE s.user_id=?
            ORDER BY e.id DESC
            LIMIT 24");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload = mg_platform_package_json($row['payload_json'] ?? null);
            $invoiceId = trim((string)($payload['invoice_id'] ?? $payload['provider_latest_invoice_id'] ?? ''));
            $invoiceUrl = trim((string)($payload['invoice_url'] ?? $payload['provider_latest_invoice_url'] ?? ''));
            $invoicePdf = trim((string)($payload['invoice_pdf'] ?? $payload['provider_latest_invoice_pdf'] ?? ''));
            if ($invoiceId !== '' && $invoiceId === trim((string)($snapshot['provider_latest_invoice_id'] ?? ''))) {
                if ($invoiceUrl === '') $invoiceUrl = trim((string)($snapshot['provider_latest_invoice_url'] ?? ''));
                if ($invoicePdf === '') $invoicePdf = trim((string)($snapshot['provider_latest_invoice_pdf'] ?? ''));
            }
            $history[] = [
                'event_type' => (string)$row['event_type'],
                'label' => mg_subscription_history_label((string)$row['event_type'], $payload, $row['to_status'] !== null ? (string)$row['to_status'] : null),
                'tone' => mg_subscription_history_tone((string)$row['event_type'], $payload, $row['to_status'] !== null ? (string)$row['to_status'] : null),
                'from_status' => $row['from_status'] !== null ? (string)$row['from_status'] : null,
                'to_status' => $row['to_status'] !== null ? (string)$row['to_status'] : null,
                'provider' => $row['provider_key'] !== null ? (string)$row['provider_key'] : null,
                'provider_event_type' => trim((string)($payload['event_type'] ?? '')) ?: null,
                'package_id' => trim((string)($payload['package_id'] ?? '')) ?: null,
                'billing_cycle' => trim((string)($payload['billing_cycle'] ?? '')) ?: null,
                'amount_cents' => isset($payload['amount_cents']) && is_numeric($payload['amount_cents']) ? (int)$payload['amount_cents'] : null,
                'currency' => trim((string)($payload['currency'] ?? '')) ?: null,
                'invoice_id' => $invoiceId !== '' ? $invoiceId : null,
                'invoice_status' => trim((string)($payload['invoice_status'] ?? '')) ?: null,
                'invoice_url' => $invoiceUrl !== '' ? $invoiceUrl : null,
                'invoice_pdf' => $invoicePdf !== '' ? $invoicePdf : null,
                'created_at' => (string)$row['created_at'],
            ];
        }
    }

    mg_ok([
        'subscription' => $snapshot ? [
            'subscription_id' => (string)$snapshot['public_id'],
            'package_id' => (string)$snapshot['package_id'],
            'billing_cycle' => (string)$snapshot['billing_cycle'],
            'status' => (string)$snapshot['status'],
            'amount_cents' => (int)$snapshot['amount_cents'],
            'currency' => strtoupper((string)$snapshot['currency']),
            'current_period_start' => $snapshot['current_period_start'] ?? null,
            'current_period_end' => $snapshot['current_period_end'] ?? null,
            'last_payment_at' => $snapshot['last_payment_at'] ?? null,
            'last_payment_failed_at' => $snapshot['last_payment_failed_at'] ?? null,
            'latest_invoice_id' => $snapshot['provider_latest_invoice_id'] ?? null,
            'latest_invoice_status' => $snapshot['provider_latest_invoice_status'] ?? null,
            'latest_invoice_url' => $snapshot['provider_latest_invoice_url'] ?? null,
            'latest_invoice_pdf' => $snapshot['provider_latest_invoice_pdf'] ?? null,
        ] : null,
        'history' => $history,
    ], 'Subscription billing history loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'subscription.history_failed', 'Unable to load subscription billing history.', [
        'exception_class' => $error::class,
    ], (int)($user['id'] ?? 0));
    mg_fail('Unable to load subscription billing history.', 500);
}
