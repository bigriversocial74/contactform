<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pricing-packages.php';

$cart_user = mg_current_user();
$requestedSubscriptionPlanId = strtolower(trim((string)($_GET['subscription_plan'] ?? '')));
$requestedSubscriptionPlanId = preg_match('/^[a-z0-9-]{1,40}$/', $requestedSubscriptionPlanId) ? $requestedSubscriptionPlanId : '';
$requestedSubscriptionCycle = in_array(strtolower(trim((string)($_GET['billing_cycle'] ?? 'month'))), ['year', 'yearly', 'annual', 'annually'], true) ? 'year' : 'month';
$requestedSubscriptionSource = trim((string)($_GET['source'] ?? 'account_subscription'));

$subscriptionPlan = null;
foreach (mg_public_pricing_packages() as $candidate) {
    if (strtolower((string)($candidate['id'] ?? '')) === $requestedSubscriptionPlanId) {
        $subscriptionPlan = $candidate;
        break;
    }
}

$subscriptionCart = null;
if ($subscriptionPlan && $requestedSubscriptionPlanId !== 'enterprise') {
    $monthlyAmountCents = match ($requestedSubscriptionPlanId) {
        'starter' => 2900,
        'growth' => 7900,
        'pro' => 19900,
        default => 0,
    };
    $yearlyAmountCents = $monthlyAmountCents > 0 ? (int)round($monthlyAmountCents * 12 * .8) : 0;
    $currency = 'USD';
    $features = array_values(array_filter((array)($subscriptionPlan['included_features'] ?? []), 'is_string'));
    $requiresAdminReview = false;
    $isSelfServe = true;

    try {
        $pdo = mg_db();
        $stmt = $pdo->prepare("SELECT package_id,name,monthly_amount_cents,yearly_amount_cents,currency,is_self_serve,requires_admin_review,features_json FROM platform_subscription_packages WHERE package_id=? AND status='active' LIMIT 1");
        $stmt->execute([$requestedSubscriptionPlanId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $subscriptionPlan['name'] = (string)($row['name'] ?? $subscriptionPlan['name'] ?? ucfirst($requestedSubscriptionPlanId));
            $monthlyAmountCents = max(0, (int)($row['monthly_amount_cents'] ?? $monthlyAmountCents));
            $yearlyAmountCents = max(0, (int)($row['yearly_amount_cents'] ?? $yearlyAmountCents));
            $currency = strtoupper(trim((string)($row['currency'] ?? 'USD'))) ?: 'USD';
            $decodedFeatures = json_decode((string)($row['features_json'] ?? ''), true);
            if (is_array($decodedFeatures) && $decodedFeatures !== []) {
                $features = array_values(array_filter($decodedFeatures, 'is_string'));
            }
            $requiresAdminReview = (int)($row['requires_admin_review'] ?? 0) === 1;
            $isSelfServe = (int)($row['is_self_serve'] ?? 1) === 1;
        }
    } catch (Throwable) {
        // Pricing-package defaults keep the cart available if billing metadata is temporarily unavailable.
    }

    $amountCents = $requestedSubscriptionCycle === 'year' ? $yearlyAmountCents : $monthlyAmountCents;
    if ($amountCents > 0 && $isSelfServe && !$requiresAdminReview) {
        $subscriptionCart = [
            'package_id' => $requestedSubscriptionPlanId,
            'name' => (string)($subscriptionPlan['name'] ?? ucfirst($requestedSubscriptionPlanId)),
            'description' => (string)($subscriptionPlan['description'] ?? 'Microgifter subscription package.'),
            'cycle' => $requestedSubscriptionCycle,
            'amount_cents' => $amountCents,
            'monthly_equivalent_cents' => $requestedSubscriptionCycle === 'year' ? (int)round($amountCents / 12) : $amountCents,
            'currency' => $currency,
            'features' => array_slice($features, 0, 8),
            'source' => $requestedSubscriptionSource !== '' ? $requestedSubscriptionSource : 'account_subscription',
        ];
    }
}

$subscriptionCartMode = $cart_user && is_array($subscriptionCart);
$subscriptionQuery = $requestedSubscriptionPlanId !== ''
    ? '?' . http_build_query([
        'subscription_plan' => $requestedSubscriptionPlanId,
        'billing_cycle' => $requestedSubscriptionCycle,
        'source' => $requestedSubscriptionSource !== '' ? $requestedSubscriptionSource : 'account_subscription',
    ])
    : '';
$cartReturnPath = '/cart.php' . $subscriptionQuery;

$formatSubscriptionMoney = static function (int $cents, string $currency): string {
    $amount = number_format($cents / 100, 2);
    return strtoupper($currency) === 'USD' ? '$' . $amount : strtoupper($currency) . ' ' . $amount;
};

$page_title = $subscriptionCartMode ? 'Subscription Cart | Microgifter' : 'Cart | Microgifter';
$page_section = $cart_user ? 'agent' : 'cart';
$header_mode = $cart_user ? 'agent' : 'public';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/checkout.css',
    '/assets/css/account-commerce.css',
    '/assets/css/account-commerce-fixes.css',
    '/assets/css/cart-layout-fixes.css',
    '/assets/css/cart-checkout-runtime-v1.css',
];
if ($subscriptionCartMode) {
    $page_styles[] = '/assets/css/subscription-cart-checkout-v1.css?v=1.0.0';
}
$page_scripts = $subscriptionCartMode ? ['/assets/js/subscription-cart-checkout-v1.js?v=1.0.0'] : [];
$agent_tab = 'cart';
$can_merchant_nav = true;
$can_create_microgift = true;
require __DIR__ . '/includes/header.php';
?>
<?php if (!$cart_user): ?>
<section class="mg-checkout-public-gate">
  <div class="mg-checkout-gate-card">
    <span class="mg-eyebrow">Checkout account required</span>
    <h1>Sign in to review your cart.</h1>
    <p>Your account protects cart ownership, frozen checkout snapshots, payment recovery, receipts, and Microgifter delivery.</p>
    <div class="mg-commerce-actions">
      <a class="mg-btn mg-btn-primary" href="/signin.php?next=<?= rawurlencode($cartReturnPath) ?>">Sign in</a>
      <a class="mg-btn mg-btn-soft" href="/signup.php?next=<?= rawurlencode($cartReturnPath) ?>">Create account</a>
      <a class="mg-btn mg-btn-soft" href="/discover.php">Keep exploring</a>
    </div>
  </div>
</section>
<?php elseif ($subscriptionCartMode): ?>
<section class="mg-app-shell mg-account-commerce-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main
    class="mg-app-workspace mg-account-shell"
    data-subscription-cart
    data-subscription-plan="<?= mg_e((string)$subscriptionCart['package_id']) ?>"
    data-subscription-cycle="<?= mg_e((string)$subscriptionCart['cycle']) ?>"
  >
    <section class="mg-commerce-page">
      <section class="mg-commerce-shell">
        <header class="mg-commerce-hero mg-checkout-hero">
          <span class="mg-eyebrow">Subscription checkout</span>
          <h1>Review your Microgifter plan</h1>
          <p>Confirm the package and billing cycle before opening secure payment. Your subscription changes only after verified checkout completion.</p>
        </header>

        <div class="mg-checkout-process" aria-label="Subscription checkout process">
          <div class="is-active"><span>01</span><strong>Cart</strong><small>Review package</small></div>
          <div><span>02</span><strong>Billing</strong><small>Secure session</small></div>
          <div><span>03</span><strong>Payment</strong><small>Stripe hosted</small></div>
          <div><span>04</span><strong>Activation</strong><small>Verified event</small></div>
          <div><span>05</span><strong>Access</strong><small>Package unlocked</small></div>
        </div>

        <div class="mg-commerce-grid mg-cart-grid mg-subscription-cart-grid">
          <section class="mg-commerce-panel mg-cart-items-panel">
            <div class="mg-section-head">
              <div><span class="mg-eyebrow">Subscription item</span><h2>Your cart</h2></div>
              <a class="mg-btn mg-btn-soft" href="/account-subscriptions.php">Change plan</a>
            </div>

            <article class="mg-subscription-cart-item">
              <div class="mg-subscription-cart-icon" aria-hidden="true">✦</div>
              <div class="mg-subscription-cart-copy">
                <span>Microgifter subscription</span>
                <h2><?= mg_e((string)$subscriptionCart['name']) ?></h2>
                <p><?= mg_e((string)$subscriptionCart['description']) ?></p>
              </div>
              <div class="mg-subscription-cart-price">
                <strong><?= mg_e($formatSubscriptionMoney((int)$subscriptionCart['amount_cents'], (string)$subscriptionCart['currency'])) ?></strong>
                <span><?= $subscriptionCart['cycle'] === 'year' ? 'Billed yearly' : 'Billed monthly' ?></span>
              </div>
            </article>

            <?php if ($subscriptionCart['features'] !== []): ?>
              <ul class="mg-subscription-cart-features" aria-label="Included package features">
                <?php foreach ($subscriptionCart['features'] as $feature): ?><li><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <div class="mg-subscription-cart-note">
              This subscription is separate from merchant product purchases. It will not change or clear products already stored in your shopping cart.
            </div>
          </section>

          <aside class="mg-commerce-panel mg-commerce-summary mg-sticky-summary">
            <div class="mg-section-head">
              <div><span class="mg-eyebrow">Summary</span><h2>Subscription total</h2></div>
            </div>
            <div class="mg-checkout-totals">
              <div class="mg-checkout-total"><span><?= mg_e((string)$subscriptionCart['name']) ?></span><strong><?= mg_e($formatSubscriptionMoney((int)$subscriptionCart['amount_cents'], (string)$subscriptionCart['currency'])) ?></strong></div>
              <div class="mg-checkout-total"><span>Billing cycle</span><strong><?= $subscriptionCart['cycle'] === 'year' ? 'Yearly' : 'Monthly' ?></strong></div>
              <?php if ($subscriptionCart['cycle'] === 'year'): ?>
                <div class="mg-checkout-total"><span>Monthly equivalent</span><strong><?= mg_e($formatSubscriptionMoney((int)$subscriptionCart['monthly_equivalent_cents'], (string)$subscriptionCart['currency'])) ?></strong></div>
              <?php endif; ?>
              <div class="mg-checkout-total"><span>Tax</span><strong>$0.00</strong></div>
              <div class="mg-checkout-total is-grand"><span>Due at checkout</span><strong><?= mg_e($formatSubscriptionMoney((int)$subscriptionCart['amount_cents'], (string)$subscriptionCart['currency'])) ?></strong></div>
            </div>
            <div class="mg-commerce-actions is-stack">
              <button class="mg-btn mg-btn-primary" type="button" data-subscription-cart-checkout>Continue to secure checkout</button>
              <a class="mg-btn mg-btn-soft" href="/account-subscriptions.php">Return to subscriptions</a>
            </div>
            <p class="mg-commerce-note">Stripe securely collects payment details. Microgifter activates the package only after a verified billing event.</p>
            <div data-subscription-cart-status class="mg-commerce-status" role="status" aria-live="polite"></div>
          </aside>
        </div>
      </section>
    </section>
  </main>
</section>
<?php else: ?>
<section class="mg-app-shell mg-account-commerce-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell" data-cart-page>
    <section class="mg-commerce-page">
      <section class="mg-commerce-shell">
        <header class="mg-commerce-hero mg-checkout-hero">
          <span class="mg-eyebrow">Customer checkout</span>
          <h1>Your cart</h1>
          <p>Review the current product versions and choose an available payment method. Interrupted checkout attempts resume safely.</p>
        </header>

        <div class="mg-checkout-process" aria-label="Checkout process">
          <div class="is-active"><span>01</span><strong>Cart</strong><small>Current products</small></div>
          <div><span>02</span><strong>Snapshot</strong><small>Terms frozen</small></div>
          <div><span>03</span><strong>Order</strong><small>Created once</small></div>
          <div><span>04</span><strong>Payment</strong><small>Recoverable session</small></div>
          <div><span>05</span><strong>Delivery</strong><small>Microgifter issued</small></div>
        </div>

        <div class="mg-commerce-grid mg-cart-grid">
          <section class="mg-commerce-panel mg-cart-items-panel" aria-live="polite">
            <div class="mg-section-head">
              <div><span class="mg-eyebrow">Items</span><h2>Cart items</h2></div>
              <button class="mg-btn mg-btn-soft" type="button" data-cart-refresh>Refresh</button>
            </div>
            <div data-cart-items><div class="mg-empty-state">Loading cart…</div></div>
          </section>

          <aside class="mg-commerce-panel mg-commerce-summary mg-sticky-summary">
            <div class="mg-section-head">
              <div><span class="mg-eyebrow">Summary</span><h2>Order preview</h2></div>
            </div>
            <div data-cart-summary><div class="mg-empty-state">Calculating…</div></div>
            <div class="mg-commerce-actions is-stack" data-cart-payment-actions>
              <button class="mg-btn mg-btn-primary" type="button" data-cart-checkout data-cart-checkout-provider="stripe" hidden>Pay with card</button>
              <button class="mg-btn mg-btn-primary" type="button" data-cart-checkout data-cart-checkout-provider="cash" hidden>Pay with cash</button>
              <button class="mg-btn mg-btn-soft" type="button" data-cart-clear>Clear cart</button>
              <a class="mg-btn mg-btn-soft" href="/discover.php">Continue shopping</a>
            </div>
            <p class="mg-commerce-note" data-cart-payment-note>Checking available payment methods…</p>
            <div data-cart-status class="mg-commerce-status" role="status" aria-live="polite"></div>
          </aside>
        </div>
      </section>
    </section>
  </main>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
