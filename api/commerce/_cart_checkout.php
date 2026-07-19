<?php
declare(strict_types=1);

require_once __DIR__ . '/_checkout.php';
require_once dirname(__DIR__) . '/payments/_checkout_session.php';

final class MgCartCheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_cart_checkout_workflow_key(mixed $value): string
{
    $key = trim((string) $value);
    if ($key === '' || strlen($key) < 12 || strlen($key) > 120 || preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
        throw new MgCartCheckoutException('A valid checkout workflow key is required.', 422);
    }
    return $key;
}

function mg_cart_checkout_provider(mixed $value): string
{
    $provider = strtolower(trim((string) $value));
    if ($provider === 'card') $provider = 'stripe';
    if (!in_array($provider, ['stripe', 'cash', 'sandbox'], true)) {
        throw new MgCartCheckoutException('Choose an available payment method.', 422);
    }
    return $provider;
}

function mg_cart_checkout_local_path(mixed $value, string $fallback): string
{
    $path = trim((string) $value);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, "\r") || str_contains($path, "\n")) {
        return $fallback;
    }
    return mb_substr($path, 0, 500);
}

function mg_cart_checkout_validate_current_items(PDO $pdo, int $cartId): void
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM cart_items ci
         LEFT JOIN catalog_product_versions cpv ON cpv.id=ci.product_version_id
         LEFT JOIN catalog_products cp ON cp.id=ci.product_id
         WHERE ci.cart_id=? AND (
             cpv.id IS NULL OR cp.id IS NULL OR cp.status<>'published'
             OR cpv.version_status<>'published' OR cp.current_version_id<>cpv.id
             OR cpv.unit_value_cents<>ci.unit_amount_cents OR cpv.currency<>ci.currency
         )"
    );
    $stmt->execute([$cartId]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new MgCartCheckoutException('One or more cart items changed or are no longer available. Remove them and add the current product version before checkout.', 409);
    }
}

function mg_cart_checkout_draft_payload(array $draft, bool $duplicate): array
{
    return [
        'checkout_draft_id' => (string) $draft['public_id'],
        'status' => (string) $draft['status'],
        'expires_at' => $draft['expires_at'],
        'duplicate' => $duplicate,
        'reused' => $duplicate,
        'totals' => [
            'currency' => (string) $draft['currency'],
            'subtotal_cents' => (int) $draft['subtotal_cents'],
            'discount_cents' => (int) $draft['discount_cents'],
            'tax_cents' => (int) $draft['tax_cents'],
            'platform_fee_cents' => (int) $draft['platform_fee_cents'],
            'total_cents' => (int) $draft['total_cents'],
        ],
        'items' => json_decode((string) $draft['items_json'], true) ?: [],
    ];
}

function mg_cart_checkout_create_draft(PDO $pdo, int $buyerUserId, string $idempotencyKey): array
{
    $idempotencyKey = trim($idempotencyKey);
    if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 190) {
        throw new MgCartCheckoutException('A valid draft idempotency key is required.', 422);
    }

    $existing = $pdo->prepare('SELECT * FROM checkout_drafts WHERE buyer_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$buyerUserId, $idempotencyKey]);
    if ($draft = $existing->fetch(PDO::FETCH_ASSOC)) {
        $status = (string) $draft['status'];
        if ($status === 'expired' || ($status === 'open' && strtotime((string) $draft['expires_at']) < time())) {
            if ($status === 'open') {
                $pdo->prepare("UPDATE checkout_drafts SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int) $draft['id']]);
            }
            throw new MgCartCheckoutException('This checkout workflow expired. Start checkout again from the current cart.', 409);
        }
        if (!in_array($status, ['open', 'converted'], true)) {
            throw new MgCartCheckoutException('This checkout workflow is no longer available.', 409);
        }
        return mg_cart_checkout_draft_payload($draft, true);
    }

    $cart = mg_cart_active($pdo, $buyerUserId, true);
    $payload = mg_cart_payload($pdo, $cart);
    if (empty($payload['items'])) throw new MgCartCheckoutException('Cart is empty.', 409);
    mg_cart_checkout_validate_current_items($pdo, (int) $cart['id']);

    $merchantIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['merchant_user_id'], $payload['items'])));
    if (count($merchantIds) !== 1) {
        throw new MgCartCheckoutException('Checkout currently supports one merchant. Multi-merchant checkout must use the controlled bundle-commerce flow.', 409);
    }

    $merchantUserId = $merchantIds[0];
    $subtotal = (int) $payload['totals']['subtotal_cents'];
    $currency = (string) $payload['totals']['currency'];
    $commissionQuote = mg_commission_quote_order($pdo, $merchantUserId, $subtotal, $currency, [
        'source_type' => 'storefront_checkout',
        'actor_user_id' => $buyerUserId,
    ]);
    $platformFee = (int)$commissionQuote['commission_amount_cents'];
    $draftId = mg_public_uuid();
    $expires = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');
    $itemsJson = mg_commerce_json($payload['items']);

    $pdo->prepare(
        "INSERT INTO checkout_drafts
         (public_id,cart_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,
          tax_cents,platform_fee_cents,total_cents,items_json,status,idempotency_key,expires_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,'open',?,?,NOW(),NOW())"
    )->execute([
        $draftId,(int) $cart['id'],$buyerUserId,$merchantUserId,$currency,$subtotal,0,0,$platformFee,$subtotal,$itemsJson,$idempotencyKey,$expires,
    ]);
    $draftDbId = (int)$pdo->lastInsertId();
    mg_commission_snapshot_checkout_draft($pdo, $draftDbId, $merchantUserId, $commissionQuote, [
        'source_type' => 'storefront_checkout',
        'cart_id' => (string)$cart['public_id'],
        'checkout_draft_id' => $draftId,
    ]);

    $draft = [
        'id' => $draftDbId,'public_id' => $draftId,'status' => 'open','expires_at' => $expires,'currency' => $currency,
        'subtotal_cents' => $subtotal,'discount_cents' => 0,'tax_cents' => 0,'platform_fee_cents' => $platformFee,
        'total_cents' => $subtotal,'items_json' => $itemsJson,
    ];

    mg_audit('commerce.checkout_draft_created', 'checkout_draft', [
        'checkout_draft_id' => $draftId,'cart_id' => (string) $cart['public_id'],'merchant_user_id' => $merchantUserId,
        'platform_fee_cents' => $platformFee,'commission_rate_bps' => (int)$commissionQuote['commission_rate_bps'],
        'commission_rate_source' => (string)$commissionQuote['rate_source'],'commission_rule_version' => MG_COMMISSION_RULE_VERSION,
    ], $buyerUserId);

    return mg_cart_checkout_draft_payload($draft, false);
}

function mg_cart_checkout_run(PDO $pdo, int $buyerUserId, string $workflowKey, string $provider): array
{
    $workflowKey = mg_cart_checkout_workflow_key($workflowKey);
    $provider = mg_cart_checkout_provider($provider);
    $draftKey = 'draft:' . $workflowKey;
    $orderKey = 'order:' . $workflowKey;
    $paymentKey = 'payment:' . $provider . ':' . $workflowKey;

    try {
        $pdo->beginTransaction();
        $draft = mg_cart_checkout_create_draft($pdo, $buyerUserId, $draftKey);
        $pdo->commit();
        $pdo->beginTransaction();
        $orderResult = mg_checkout_create_order($pdo, $buyerUserId, (string) $draft['checkout_draft_id'], $orderKey);
        $pdo->commit();
        $pdo->beginTransaction();
        $session = mg_payment_create_checkout_session($pdo, $buyerUserId, (string) $orderResult['order']['order_id'], $paymentKey, [
            'provider_key' => $provider,'success_url' => '/checkout-success.php','cancel_url' => '/cart.php',
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'workflow_key' => $workflowKey,'provider' => $provider,'draft' => $draft,'order' => $orderResult['order'],'session' => $session,
        'reused' => !empty($draft['duplicate']) || !empty($orderResult['duplicate']) || !empty($session['duplicate']),
    ];
}
