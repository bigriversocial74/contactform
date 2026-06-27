<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/subscriptions/_package_billing.php';

function mg_admin_platform_package_can_view(array $user): bool
{
    return mg_api_user_has_permission($user, 'admin.commerce.view')
        || mg_api_user_has_permission($user, 'admin.commerce.manage')
        || mg_api_user_has_permission($user, 'admin.settings.manage')
        || mg_api_user_has_permission($user, 'subscriptions.admin');
}

function mg_admin_platform_package_can_manage(array $user): bool
{
    return mg_api_user_has_permission($user, 'admin.commerce.manage')
        || mg_api_user_has_permission($user, 'admin.settings.manage')
        || mg_api_user_has_permission($user, 'subscriptions.admin');
}

function mg_admin_platform_package_clean_id(mixed $value, string $prefix, string $label): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (strlen($value) > 190 || !preg_match('/^' . preg_quote($prefix, '/') . '[A-Za-z0-9_]+$/', $value)) {
        mg_fail($label . ' must be blank or a valid ' . $prefix . ' Stripe identifier.', 422);
    }
    return $value;
}

function mg_admin_platform_package_amount(mixed $value, string $label, bool $allowZero = false): int
{
    $amount = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => -1]]);
    $amount = (int)$amount;
    if ($allowZero && $amount === 0) return 0;
    if ($amount < 1) mg_fail($label . ' must be at least 1 cent.', 422);
    return $amount;
}

function mg_admin_platform_package_bool(mixed $value): int
{
    if (is_bool($value)) return $value ? 1 : 0;
    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'yes', 'on', 'active', 'enabled'], true) ? 1 : 0;
}

function mg_admin_platform_package_public(array $row): array
{
    $mode = function_exists('mg_payment_mode') ? mg_payment_mode() : 'test';
    $priceId = mg_platform_package_stripe_price_id($row, $mode);
    return [
        'package_id' => (string)$row['package_id'],
        'name' => (string)$row['name'],
        'billing_cycle' => (string)($row['billing_cycle'] ?? 'month'),
        'monthly_amount_cents' => (int)($row['monthly_amount_cents'] ?? 0),
        'yearly_amount_cents' => (int)($row['yearly_amount_cents'] ?? 0),
        'currency' => strtoupper((string)($row['currency'] ?? 'USD')),
        'stripe_price_id_test' => (string)($row['stripe_price_id_test'] ?? ''),
        'stripe_price_id_live' => (string)($row['stripe_price_id_live'] ?? ''),
        'stripe_product_id_test' => (string)($row['stripe_product_id_test'] ?? ''),
        'stripe_product_id_live' => (string)($row['stripe_product_id_live'] ?? ''),
        'is_self_serve' => (int)($row['is_self_serve'] ?? 0),
        'requires_admin_review' => (int)($row['requires_admin_review'] ?? 0),
        'status' => (string)($row['status'] ?? 'active'),
        'checkout_price_id' => $priceId,
        'checkout_ready' => ((int)($row['is_self_serve'] ?? 0) === 1 && $priceId !== ''),
        'payment_mode' => $mode,
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function mg_admin_platform_package_rows(PDO $pdo): array
{
    mg_platform_package_sync_defaults($pdo);
    $stmt = $pdo->query("SELECT * FROM platform_subscription_packages ORDER BY FIELD(package_id,'starter','growth','pro','enterprise'), sort_order, id");
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = mg_admin_platform_package_public($row);
    }
    return $rows;
}

$user = mg_require_api_user();
if (!mg_admin_platform_package_can_view($user)) mg_fail('Permission denied.', 403);
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    mg_ok(['packages' => mg_admin_platform_package_rows($pdo)]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!mg_admin_platform_package_can_manage($user)) mg_fail('Permission denied.', 403);
$input = mg_input();
mg_require_csrf_for_write($input);

$packageId = mg_platform_package_slug($input['package_id'] ?? '');
if ($packageId === '') mg_fail('Package ID is required.', 422);

try {
    $pdo->beginTransaction();
    $package = mg_platform_package_get($pdo, $packageId);
    if (!$package) mg_fail('Package billing row not found.', 404);

    $isEnterprise = $packageId === 'enterprise';
    $monthly = mg_admin_platform_package_amount($input['monthly_amount_cents'] ?? $package['monthly_amount_cents'] ?? 0, 'Monthly amount');
    $yearly = mg_admin_platform_package_amount($input['yearly_amount_cents'] ?? $package['yearly_amount_cents'] ?? 0, 'Yearly amount', true);
    $currency = strtoupper(trim((string)($input['currency'] ?? $package['currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) mg_fail('Currency must be a 3-letter ISO code.', 422);

    $billingCycle = trim((string)($input['billing_cycle'] ?? $package['billing_cycle'] ?? 'month'));
    $billingCycle = in_array($billingCycle, ['year', 'yearly'], true) ? 'year' : 'month';

    $selfServe = $isEnterprise ? 0 : mg_admin_platform_package_bool($input['is_self_serve'] ?? $package['is_self_serve'] ?? 1);
    $adminReview = $isEnterprise ? 1 : mg_admin_platform_package_bool($input['requires_admin_review'] ?? $package['requires_admin_review'] ?? 0);

    $priceTest = mg_admin_platform_package_clean_id($input['stripe_price_id_test'] ?? $package['stripe_price_id_test'] ?? '', 'price_', 'Test Price ID');
    $priceLive = mg_admin_platform_package_clean_id($input['stripe_price_id_live'] ?? $package['stripe_price_id_live'] ?? '', 'price_', 'Live Price ID');
    $productTest = mg_admin_platform_package_clean_id($input['stripe_product_id_test'] ?? $package['stripe_product_id_test'] ?? '', 'prod_', 'Test Product ID');
    $productLive = mg_admin_platform_package_clean_id($input['stripe_product_id_live'] ?? $package['stripe_product_id_live'] ?? '', 'prod_', 'Live Product ID');

    $stmt = $pdo->prepare("UPDATE platform_subscription_packages SET billing_cycle=?, monthly_amount_cents=?, yearly_amount_cents=?, currency=?, stripe_price_id_test=?, stripe_price_id_live=?, stripe_product_id_test=?, stripe_product_id_live=?, is_self_serve=?, requires_admin_review=?, updated_at=NOW() WHERE package_id=? LIMIT 1");
    $stmt->execute([$billingCycle, $monthly, $yearly, $currency, $priceTest, $priceLive, $productTest, $productLive, $selfServe, $adminReview, $packageId]);

    $updated = mg_platform_package_get($pdo, $packageId);
    $pdo->commit();

    mg_audit('platform_package.billing_saved', 'platform_subscription_package', [
        'package_id' => $packageId,
        'has_test_price' => $priceTest !== null,
        'has_live_price' => $priceLive !== null,
        'has_test_product' => $productTest !== null,
        'has_live_product' => $productLive !== null,
        'is_self_serve' => $selfServe,
        'requires_admin_review' => $adminReview,
    ], (int)$user['id']);

    mg_ok(['package' => mg_admin_platform_package_public($updated ?: []), 'packages' => mg_admin_platform_package_rows($pdo)], 'Platform package billing saved.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'platform_package.billing_save_failed', 'Unable to save platform package billing identifiers.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to save platform package billing identifiers.', 500);
}
