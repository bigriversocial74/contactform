<?php
declare(strict_types=1);

require_once __DIR__ . '/_stamps.php';
require_once __DIR__ . '/_receipt_notifications.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

function mg_stamp_purchase_payload(PDO $pdo, array $purchase, ?array $credited = null, ?array $intent = null): array
{
    return [
        'purchase' => [
            'id' => (string)$purchase['public_id'],
            'bundle_key' => (string)$purchase['bundle_key'],
            'label' => (string)$purchase['label_snapshot'],
            'stamps' => (int)$purchase['stamps_snapshot'],
            'price_cents' => (int)$purchase['price_cents_snapshot'],
            'currency' => (string)$purchase['currency_snapshot'],
            'status' => (string)$purchase['status'],
            'checkout_reference' => (string)($purchase['checkout_reference'] ?? ''),
            'credited_ledger_entry_id' => (string)($purchase['credited_ledger_entry_public_id'] ?? ''),
            'paid_at' => $purchase['paid_at'] ?? null,
            'credited_at' => $purchase['credited_at'] ?? null,
            'checkout_url' => '/stamp-checkout.php?purchase=' . rawurlencode((string)$purchase['public_id']),
            'receipt_url' => '/stamp-receipt.php?purchase=' . rawurlencode((string)$purchase['public_id']),
            'requires_verified_payment' => (string)$purchase['status'] !== 'credited',
        ],
        'payment_intent' => $intent,
        'stamp_ledger' => $credited,
        'ledger' => mg_stamp_ledger_payload($pdo, (int)$purchase['account_user_id']),
    ];
}

function mg_stamp_purchase_load(PDO $pdo, int $accountUserId, string $purchaseId = '', string $checkoutReference = '', bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($purchaseId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM stamp_purchases WHERE public_id=? AND account_user_id=? LIMIT 1' . $suffix);
        $stmt->execute([$purchaseId, $accountUserId]);
    } elseif ($checkoutReference !== '') {
        $stmt = $pdo->prepare('SELECT * FROM stamp_purchases WHERE checkout_reference=? AND account_user_id=? LIMIT 1' . $suffix);
        $stmt->execute([$checkoutReference, $accountUserId]);
    } else {
        mg_fail('purchase_id or checkout_reference is required.', 422);
    }
    $purchase = $stmt->fetch();
    if (!$purchase) mg_fail('Stamp purchase not found.', 404);
    return $purchase;
}

function mg_stamp_purchase_find_intent(PDO $pdo, string $purchaseId, bool $lock = false): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM payment_intents WHERE source_type='stamp_purchase' AND source_reference=? ORDER BY id DESC LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$purchaseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_stamp_purchase_create_intent(PDO $pdo, array $purchase, string $idempotencyKey): array
{
    $purchaseId = (string)$purchase['public_id'];
    $existing = mg_stamp_purchase_find_intent($pdo, $purchaseId, true);
    if ($existing) return $existing + ['duplicate' => true];
    return mg_payment_create_source_intent($pdo, [
        'provider_key' => mg_payment_checkout_provider_key($pdo, null),
        'source_type' => 'stamp_purchase',
        'source_reference' => $purchaseId,
        'idempotency_key' => 'stamp:payment:' . $idempotencyKey,
        'amount_cents' => (int)$purchase['price_cents_snapshot'],
        'currency' => (string)$purchase['currency_snapshot'],
        'metadata' => [
            'source_type' => 'stamp_purchase',
            'stamp_purchase_id' => $purchaseId,
            'account_user_id' => (int)$purchase['account_user_id'],
            'bundle_key' => (string)$purchase['bundle_key'],
            'stamps' => (int)$purchase['stamps_snapshot'],
        ],
    ]);
}

function mg_stamp_purchase_provider_metadata(array $purchase, array $intent): array
{
    return [
        'source_type' => 'stamp_purchase',
        'source_reference' => (string)$purchase['public_id'],
        'stamp_purchase_id' => (string)$purchase['public_id'],
        'payment_intent_id' => (string)($intent['public_id'] ?? ''),
        'account_user_id' => (string)(int)$purchase['account_user_id'],
        'bundle_key' => (string)$purchase['bundle_key'],
        'stamps' => (string)(int)$purchase['stamps_snapshot'],
        'price_cents' => (string)(int)$purchase['price_cents_snapshot'],
        'currency' => strtolower((string)$purchase['currency_snapshot']),
    ];
}

function mg_stamp_purchase_assert_provider_checkout_ready(PDO $pdo, array $intent): void
{
    $provider = strtolower(trim((string)($intent['provider_key'] ?? '')));
    if ($provider !== 'stripe') {
        throw new RuntimeException('Provider checkout is not configured for this Stamp purchase. Configure Stripe as the active payment provider before accepting Stamp bundle payments.');
    }
    $config = mg_payment_platform_config($pdo, 'stripe', mg_payment_mode());
    if (!mg_stripe_stub_enabled() && (!$config['enabled'] || trim((string)$config['secret_key']) === '' || trim((string)$config['webhook_secret']) === '')) {
        throw new RuntimeException('Stripe is not configured for Stamp purchase checkout. Configure the Stripe secret key and webhook signing secret first.');
    }
}

function mg_stamp_purchase_create_provider_checkout_session(PDO $pdo, array $purchase, array $intent): array
{
    $purchaseId = (string)$purchase['public_id'];
    if ((string)$purchase['status'] === 'credited') {
        throw new RuntimeException('This Stamp purchase has already been credited.');
    }
    if (in_array((string)$purchase['status'], ['cancelled', 'failed'], true)) {
        throw new RuntimeException('This Stamp purchase is no longer awaiting payment.');
    }
    if ((string)($intent['source_type'] ?? '') !== 'stamp_purchase' || (string)($intent['source_reference'] ?? '') !== $purchaseId) {
        throw new RuntimeException('Stamp purchase payment intent is not linked to this purchase.');
    }
    if ((int)$intent['amount_cents'] !== (int)$purchase['price_cents_snapshot'] || !hash_equals((string)$intent['currency'], (string)$purchase['currency_snapshot'])) {
        throw new RuntimeException('Stamp purchase payment intent does not match purchase snapshot.');
    }

    mg_stamp_purchase_assert_provider_checkout_ready($pdo, $intent);

    $metadata = mg_stamp_purchase_provider_metadata($purchase, $intent);
    $success = '/stamp-checkout.php?purchase=' . rawurlencode($purchaseId) . '&provider_checkout=success';
    $cancel = '/stamp-checkout.php?purchase=' . rawurlencode($purchaseId) . '&provider_checkout=cancelled';
    $successUrl = mg_payment_absolute_url($success) . '&stripe_session_id={CHECKOUT_SESSION_ID}';

    $params = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => mg_payment_absolute_url($cancel),
        'client_reference_id' => $purchaseId,
        'metadata' => $metadata,
        'payment_intent_data' => [
            'metadata' => $metadata,
        ],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => strtolower((string)$purchase['currency_snapshot']),
                'unit_amount' => (int)$purchase['price_cents_snapshot'],
                'product_data' => [
                    'name' => mb_substr((string)$purchase['label_snapshot'] . ' Stamp bundle', 0, 240),
                    'description' => (int)$purchase['stamps_snapshot'] . ' Microgifter Stamps',
                ],
            ],
        ]],
    ];

    try {
        $session = mg_stripe_api_request($pdo, 'POST', '/v1/checkout/sessions', $params, 'stamp_checkout:' . $purchaseId . ':' . (string)($intent['public_id'] ?? $intent['id']));
    } catch (MgStripeProviderException $error) {
        throw new RuntimeException($error->getMessage(), $error->httpStatus, $error);
    }

    $providerSession = trim((string)($session['id'] ?? ''));
    $checkoutUrl = trim((string)($session['url'] ?? ''));
    if ($providerSession === '' || $checkoutUrl === '') {
        throw new RuntimeException('The payment provider did not return a hosted checkout URL.');
    }

    $providerIntent = trim((string)($session['payment_intent'] ?? ''));
    if ($providerIntent !== '') {
        $pdo->prepare("UPDATE payment_intents SET provider_intent_reference=?,status='requires_action',updated_at=NOW() WHERE id=? AND status<>'succeeded'")
            ->execute([$providerIntent, (int)$intent['id']]);
    } else {
        $pdo->prepare("UPDATE payment_intents SET status='requires_action',updated_at=NOW() WHERE id=? AND status<>'succeeded'")
            ->execute([(int)$intent['id']]);
    }
    $pdo->prepare("UPDATE stamp_purchases SET status='checkout_created',updated_at=NOW() WHERE id=? AND status IN ('pending','checkout_created')")
        ->execute([(int)$purchase['id']]);

    return [
        'purchase_id' => $purchaseId,
        'payment_intent_id' => (string)($intent['public_id'] ?? ''),
        'provider' => 'stripe',
        'mode' => mg_payment_is_live() ? 'live' : 'test',
        'checkout_url' => $checkoutUrl,
        'provider_session_reference' => $providerSession,
        'provider_intent_reference' => $providerIntent,
        'expires_at' => date('Y-m-d H:i:s', (int)($session['expires_at'] ?? time() + 1800)),
        'metadata' => $metadata,
    ];
}

function mg_stamp_purchase_load_any(PDO $pdo, string $purchaseId, bool $lock = false): array
{
    $stmt = $pdo->prepare('SELECT * FROM stamp_purchases WHERE public_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$purchaseId]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$purchase) throw new RuntimeException('Stamp purchase not found.');
    return $purchase;
}

function mg_stamp_purchase_complete_verified(PDO $pdo, array $purchase, int $actorUserId, string $providerStatus, string $providerReference = '', string $idempotencySuffix = ''): array
{
    if ((string)$purchase['status'] === 'credited' && !empty($purchase['credited_ledger_entry_public_id'])) {
        return mg_stamp_purchase_payload($pdo, $purchase, null, mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'])) + ['idempotent' => true];
    }
    if (!in_array($providerStatus, ['paid','succeeded','complete','completed'], true)) mg_fail('Payment has not completed.', 409);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], true);
    if (!$intent) throw new RuntimeException('Stamp purchase payment intent is missing.');
    if ((int)$intent['amount_cents'] !== (int)$purchase['price_cents_snapshot'] || !hash_equals((string)$intent['currency'], (string)$purchase['currency_snapshot'])) throw new RuntimeException('Stamp purchase payment intent does not match purchase snapshot.');
    if (in_array((string)$intent['status'], ['failed','cancelled'], true)) throw new RuntimeException('Stamp purchase payment intent cannot be credited.');
    $providerReference = trim($providerReference) ?: trim((string)($intent['provider_intent_reference'] ?? ''));
    if ($providerReference === '') throw new RuntimeException('Provider payment reference is required.');
    $pdo->prepare("UPDATE payment_intents SET provider_intent_reference=?,status='succeeded',authorized_at=COALESCE(authorized_at,NOW()),captured_at=COALESCE(captured_at,NOW()),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([$providerReference, (int)$intent['id']]);
    $credit = mg_stamp_credit($pdo, (int)$purchase['account_user_id'], $actorUserId, (int)$purchase['stamps_snapshot'], 'stamp:purchase:' . (string)$purchase['public_id'] . ':' . (string)$purchase['bundle_key'] . ($idempotencySuffix !== '' ? ':' . $idempotencySuffix : ''), [
        'actor_type' => $actorUserId === (int)$purchase['account_user_id'] ? 'provider' : 'admin',
        'source_type' => 'bulk_stamp_purchase',
        'source_id' => (string)$purchase['public_id'],
        'reference' => (string)$purchase['bundle_key'],
        'reason_code' => 'bundle_purchase_payment_complete',
        'metadata' => ['purchase_id'=>(string)$purchase['public_id'],'bundle_key'=>(string)$purchase['bundle_key'],'price_cents'=>(int)$purchase['price_cents_snapshot'],'provider_status'=>$providerStatus,'provider_reference'=>$providerReference,'payment_intent_id'=>(string)($intent['public_id'] ?? ''),'completion_source'=>'verified_payment'],
    ]);
    $pdo->prepare('UPDATE stamp_purchases SET status=?,credited_ledger_entry_public_id=?,paid_at=COALESCE(paid_at,NOW()),credited_at=NOW(),updated_at=NOW() WHERE id=?')->execute(['credited', (string)($credit['entry']['entry_id'] ?? ''), (int)$purchase['id']]);
    $purchase['status'] = 'credited';
    $purchase['credited_ledger_entry_public_id'] = (string)($credit['entry']['entry_id'] ?? '');
    $purchase['paid_at'] = $purchase['paid_at'] ?? date('Y-m-d H:i:s');
    $purchase['credited_at'] = date('Y-m-d H:i:s');
    $notification = mg_stamp_receipt_notify_merchant($pdo, $purchase, 'credited', $actorUserId, ['provider_reference'=>$providerReference,'ledger_entry_id'=>(string)($credit['entry']['entry_id'] ?? '')]);
    return mg_stamp_purchase_payload($pdo, $purchase, $credit, $intent) + ['idempotent' => !empty($credit['idempotent']), 'receipt_notification' => $notification];
}

function mg_stamp_purchase_complete(PDO $pdo, array $purchase, int $actorUserId, string $providerStatus = 'paid', string $idempotencySuffix = ''): array
{
    return mg_stamp_purchase_complete_verified($pdo, $purchase, $actorUserId, $providerStatus, '', $idempotencySuffix);
}
