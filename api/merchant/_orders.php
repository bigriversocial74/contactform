<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/commerce/_order_issuance_summary.php';

const MG_MERCHANT_ORDERS_DEFAULT_LIMIT = 25;
const MG_MERCHANT_ORDERS_MAX_LIMIT = 50;

function mg_merchant_orders_text(mixed $value, int $maxLength): string
{
    $value = trim((string) $value);
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function mg_merchant_orders_date(mixed $value): ?string
{
    $value = mg_merchant_orders_text($value, 10);
    if ($value === '') return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
        mg_fail('Invalid order date filter.', 422);
    }
    if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
        mg_fail('Invalid order date filter.', 422);
    }
    return $value;
}

function mg_merchant_orders_email_mask(?string $email): ?string
{
    $email = strtolower(trim((string) $email));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) return null;
    [$local, $domain] = explode('@', $email, 2);
    $prefix = mb_substr($local, 0, 1);
    return ($prefix !== '' ? $prefix : '*') . '***@' . $domain;
}

function mg_merchant_orders_filters(array $input): array
{
    $payment = strtolower(mg_merchant_orders_text($input['payment_status'] ?? 'all', 32));
    $fulfillment = strtolower(mg_merchant_orders_text($input['fulfillment_status'] ?? 'all', 32));
    $allowedPayment = ['all','unpaid','requires_action','authorized','paid','partially_refunded','refunded','disputed','failed','cancelled'];
    $allowedFulfillment = ['all','pending','issuing','issued','partial','failed','cancelled'];
    if (!in_array($payment, $allowedPayment, true)) mg_fail('Invalid payment status filter.', 422);
    if (!in_array($fulfillment, $allowedFulfillment, true)) mg_fail('Invalid fulfillment status filter.', 422);

    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    $limit = filter_var($input['limit'] ?? MG_MERCHANT_ORDERS_DEFAULT_LIMIT, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MG_MERCHANT_ORDERS_MAX_LIMIT]]);
    if ($page === false) mg_fail('Invalid order page.', 422);
    if ($limit === false) mg_fail('Invalid order page size.', 422);

    return [
        'q' => mb_strtolower(mg_merchant_orders_text($input['q'] ?? '', 120)),
        'payment_status' => $payment,
        'fulfillment_status' => $fulfillment,
        'attention' => filter_var($input['attention'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'date_from' => mg_merchant_orders_date($input['date_from'] ?? null),
        'date_to' => mg_merchant_orders_date($input['date_to'] ?? null),
        'page' => (int) $page,
        'limit' => (int) $limit,
    ];
}

function mg_merchant_orders_summary(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare(<<<'SQL'
SELECT
    COUNT(*) total_orders,
    COALESCE(SUM(payment_status='paid'),0) paid_orders,
    COALESCE(SUM(payment_status IN ('requires_action','failed','disputed') OR fulfillment_status='failed' OR (payment_status='paid' AND fulfillment_status<>'issued')),0) attention_orders,
    COALESCE(SUM(payment_status='paid' AND fulfillment_status='issued'),0) fulfilled_orders,
    COALESCE(SUM(CASE WHEN payment_status IN ('paid','partially_refunded','refunded') THEN total_cents ELSE 0 END),0) paid_volume_cents,
    COUNT(DISTINCT CASE WHEN payment_status IN ('paid','partially_refunded','refunded') THEN currency END) currency_count,
    MIN(CASE WHEN payment_status IN ('paid','partially_refunded','refunded') THEN currency END) currency
FROM commerce_orders
WHERE merchant_user_id=?
SQL);
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $refunds = $pdo->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM payment_refunds WHERE merchant_user_id=? AND status='succeeded'");
    $refunds->execute([$merchantUserId]);

    return [
        'total_orders' => (int) ($row['total_orders'] ?? 0),
        'paid_orders' => (int) ($row['paid_orders'] ?? 0),
        'attention_orders' => (int) ($row['attention_orders'] ?? 0),
        'fulfilled_orders' => (int) ($row['fulfilled_orders'] ?? 0),
        'paid_volume_cents' => (int) ($row['paid_volume_cents'] ?? 0),
        'refunded_cents' => (int) $refunds->fetchColumn(),
        'currency' => (int) ($row['currency_count'] ?? 0) <= 1 ? (string) ($row['currency'] ?? 'USD') : 'MIXED',
        'generated_at' => gmdate('c'),
    ];
}

function mg_merchant_orders_list(PDO $pdo, int $merchantUserId, array $input): array
{
    $filters = mg_merchant_orders_filters($input);
    $sql = <<<'SQL'
SELECT
    o.id,o.public_id,o.currency,o.total_cents,o.payment_status,o.fulfillment_status,o.source_type,
    o.paid_at,o.cancelled_at,o.created_at,o.updated_at,
    COALESCE(u.display_name,u.full_name,u.email,'Customer') customer_name,u.email customer_email,
    (SELECT COUNT(*) FROM commerce_order_items oi WHERE oi.order_id=o.id) line_count,
    (SELECT COALESCE(SUM(oi.quantity),0) FROM commerce_order_items oi WHERE oi.order_id=o.id) unit_count,
    (SELECT COALESCE(SUM(r.amount_cents),0) FROM payment_refunds r WHERE r.order_id=o.id AND r.status='succeeded') refunded_cents,
    (SELECT COUNT(*) FROM pppm_items pi INNER JOIN commerce_order_items oi ON oi.public_id=pi.source_line_reference WHERE pi.source_reference=o.public_id AND oi.order_id=o.id) pppm_count,
    (SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=o.id) microgift_count,
    (SELECT COUNT(DISTINCT inbox.instance_id) FROM microgift_inbox_items inbox INNER JOIN microgift_instances mi ON mi.id=inbox.instance_id INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=o.id AND inbox.user_id=o.buyer_user_id) action_center_count
FROM commerce_orders o
INNER JOIN users u ON u.id=o.buyer_user_id
WHERE o.merchant_user_id=?
SQL;
    $params = [$merchantUserId];

    if ($filters['q'] !== '') {
        $needle = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $filters['q']) . '%';
        $sql .= ' AND (LOWER(o.public_id) LIKE ? ESCAPE "!" OR LOWER(COALESCE(u.display_name,u.full_name,u.email,"")) LIKE ? ESCAPE "!" OR EXISTS (SELECT 1 FROM commerce_order_items search_item WHERE search_item.order_id=o.id AND LOWER(search_item.title_snapshot) LIKE ? ESCAPE "!"))';
        array_push($params, $needle, $needle, $needle);
    }
    if ($filters['payment_status'] !== 'all') {
        $sql .= ' AND o.payment_status=?';
        $params[] = $filters['payment_status'];
    }
    if ($filters['fulfillment_status'] !== 'all') {
        $sql .= ' AND o.fulfillment_status=?';
        $params[] = $filters['fulfillment_status'];
    }
    if ($filters['attention']) {
        $sql .= " AND (o.payment_status IN ('requires_action','failed','disputed') OR o.fulfillment_status='failed' OR (o.payment_status='paid' AND o.fulfillment_status<>'issued'))";
    }
    if ($filters['date_from'] !== null) {
        $sql .= ' AND o.created_at>=?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if ($filters['date_to'] !== null) {
        $until = (new DateTimeImmutable($filters['date_to'], new DateTimeZone('UTC')))->modify('+1 day');
        $sql .= ' AND o.created_at<?';
        $params[] = $until->format('Y-m-d 00:00:00');
    }

    $offset = ($filters['page'] - 1) * $filters['limit'];
    $sql .= ' ORDER BY o.created_at DESC,o.id DESC LIMIT ' . ($filters['limit'] + 1) . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $filters['limit'];
    if ($hasMore) array_pop($rows);

    $items = array_map(static function (array $row): array {
        $expected = (int) ($row['unit_count'] ?? 0);
        $issued = min($expected, (int) ($row['pppm_count'] ?? 0), (int) ($row['microgift_count'] ?? 0), (int) ($row['action_center_count'] ?? 0));
        $attention = in_array((string) $row['payment_status'], ['requires_action','failed','disputed'], true)
            || (string) $row['fulfillment_status'] === 'failed'
            || ((string) $row['payment_status'] === 'paid' && (string) $row['fulfillment_status'] !== 'issued')
            || ($expected > 0 && $issued < $expected && (string) $row['payment_status'] === 'paid');
        return [
            'order_id' => (string) $row['public_id'],
            'payment_status' => (string) $row['payment_status'],
            'fulfillment_status' => (string) $row['fulfillment_status'],
            'source_type' => (string) $row['source_type'],
            'currency' => (string) $row['currency'],
            'total_cents' => (int) $row['total_cents'],
            'refunded_cents' => (int) $row['refunded_cents'],
            'line_count' => (int) $row['line_count'],
            'unit_count' => $expected,
            'issued_units' => $issued,
            'customer' => [
                'display_name' => (string) $row['customer_name'],
                'email_masked' => mg_merchant_orders_email_mask($row['customer_email'] !== null ? (string) $row['customer_email'] : null),
            ],
            'paid_at' => $row['paid_at'],
            'cancelled_at' => $row['cancelled_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'attention' => $attention,
            'can_reconcile' => (string) $row['payment_status'] === 'paid',
        ];
    }, $rows);

    return [
        'orders' => $items,
        'summary' => mg_merchant_orders_summary($pdo, $merchantUserId),
        'page' => $filters['page'],
        'limit' => $filters['limit'],
        'has_more' => $hasMore,
        'next_page' => $hasMore ? $filters['page'] + 1 : null,
        'filters' => $filters,
    ];
}

function mg_merchant_order_detail(PDO $pdo, int $merchantUserId, string $orderPublicId): array
{
    $orderStmt = $pdo->prepare(<<<'SQL'
SELECT o.*,COALESCE(u.display_name,u.full_name,u.email,'Customer') customer_name,u.email customer_email
FROM commerce_orders o
INNER JOIN users u ON u.id=o.buyer_user_id
WHERE o.public_id=? AND o.merchant_user_id=?
LIMIT 1
SQL);
    $orderStmt->execute([$orderPublicId, $merchantUserId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) mg_fail('Order not found.', 404);

    $items = $pdo->prepare(<<<'SQL'
SELECT oi.public_id item_id,oi.title_snapshot,oi.quantity,oi.unit_amount_cents,oi.discount_cents,oi.tax_cents,oi.line_total_cents,oi.currency,
       cp.public_id product_id,cp.slug product_slug,cpv.public_id product_version_id,
       (SELECT COUNT(*) FROM pppm_items pi WHERE pi.source_reference=? AND pi.source_line_reference=oi.public_id) pppm_count,
       (SELECT COUNT(*) FROM microgift_instances mi WHERE mi.commerce_order_item_id=oi.id) microgift_count,
       (SELECT COUNT(DISTINCT inbox.instance_id) FROM microgift_inbox_items inbox INNER JOIN microgift_instances mi ON mi.id=inbox.instance_id WHERE mi.commerce_order_item_id=oi.id AND inbox.user_id=?) action_center_count
FROM commerce_order_items oi
INNER JOIN catalog_products cp ON cp.id=oi.product_id
INNER JOIN catalog_product_versions cpv ON cpv.id=oi.product_version_id
WHERE oi.order_id=?
ORDER BY oi.id
LIMIT 100
SQL);
    $items->execute([(string) $order['public_id'], (int) $order['buyer_user_id'], (int) $order['id']]);
    $itemRows = array_map(static function (array $row): array {
        $expected = (int) $row['quantity'];
        return [
            'item_id' => (string) $row['item_id'],
            'title' => (string) $row['title_snapshot'],
            'quantity' => $expected,
            'unit_amount_cents' => (int) $row['unit_amount_cents'],
            'discount_cents' => (int) $row['discount_cents'],
            'tax_cents' => (int) $row['tax_cents'],
            'line_total_cents' => (int) $row['line_total_cents'],
            'currency' => (string) $row['currency'],
            'product_id' => (string) $row['product_id'],
            'product_version_id' => (string) $row['product_version_id'],
            'product_url' => '/product.php?id=' . rawurlencode((string) $row['product_id']) . '&p=' . rawurlencode((string) $row['product_slug']),
            'issuance' => [
                'expected_units' => $expected,
                'pppm_items' => (int) $row['pppm_count'],
                'microgifts' => (int) $row['microgift_count'],
                'action_center_items' => (int) $row['action_center_count'],
            ],
        ];
    }, $items->fetchAll(PDO::FETCH_ASSOC));

    $intents = $pdo->prepare('SELECT public_id,provider_key,amount_cents,currency,status,capture_method,failure_code,failure_message,authorized_at,captured_at,created_at,updated_at FROM payment_intents WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 20');
    $intents->execute([(int) $order['id']]);
    $transactions = $pdo->prepare('SELECT t.public_id,t.transaction_type,t.amount_cents,t.currency,t.status,t.occurred_at FROM payment_transactions t INNER JOIN payment_intents i ON i.id=t.payment_intent_id WHERE i.order_id=? ORDER BY t.occurred_at DESC,t.id DESC LIMIT 100');
    $transactions->execute([(int) $order['id']]);
    $refunds = $pdo->prepare('SELECT public_id,amount_cents,currency,reason,status,failure_message,processed_at,created_at,updated_at FROM payment_refunds WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 50');
    $refunds->execute([(int) $order['id']]);
    $disputes = $pdo->prepare('SELECT public_id,amount_cents,currency,reason,status,response_due_at,resolved_at,created_at,updated_at FROM payment_disputes WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 50');
    $disputes->execute([(int) $order['id']]);
    $history = $pdo->prepare('SELECT status_domain,from_status,to_status,actor_type,reason_code,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 100');
    $history->execute([(int) $order['id']]);
    $audits = $pdo->prepare('SELECT event_type,created_at FROM order_audit_events WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 100');
    $audits->execute([(int) $order['id']]);
    $microgiftStates = $pdo->prepare('SELECT mi.status,COUNT(*) item_count FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=? GROUP BY mi.status ORDER BY mi.status');
    $microgiftStates->execute([(int) $order['id']]);

    $issuance = mg_order_issuance_summary($pdo, $order, (int) $order['buyer_user_id']);
    return [
        'order' => [
            'order_id' => (string) $order['public_id'],
            'payment_status' => (string) $order['payment_status'],
            'fulfillment_status' => (string) $order['fulfillment_status'],
            'source_type' => (string) $order['source_type'],
            'source_reference' => $order['source_reference'] !== null ? (string) $order['source_reference'] : null,
            'currency' => (string) $order['currency'],
            'subtotal_cents' => (int) $order['subtotal_cents'],
            'discount_cents' => (int) $order['discount_cents'],
            'tax_cents' => (int) $order['tax_cents'],
            'platform_fee_cents' => (int) $order['platform_fee_cents'],
            'total_cents' => (int) $order['total_cents'],
            'customer' => [
                'display_name' => (string) $order['customer_name'],
                'email_masked' => mg_merchant_orders_email_mask($order['customer_email'] !== null ? (string) $order['customer_email'] : null),
            ],
            'paid_at' => $order['paid_at'],
            'cancelled_at' => $order['cancelled_at'],
            'created_at' => (string) $order['created_at'],
            'updated_at' => (string) $order['updated_at'],
            'can_reconcile' => (string) $order['payment_status'] === 'paid',
        ],
        'items' => $itemRows,
        'issuance' => $issuance,
        'payments' => [
            'intents' => $intents->fetchAll(PDO::FETCH_ASSOC),
            'transactions' => $transactions->fetchAll(PDO::FETCH_ASSOC),
            'refunds' => $refunds->fetchAll(PDO::FETCH_ASSOC),
            'disputes' => $disputes->fetchAll(PDO::FETCH_ASSOC),
        ],
        'microgift_states' => $microgiftStates->fetchAll(PDO::FETCH_ASSOC),
        'history' => $history->fetchAll(PDO::FETCH_ASSOC),
        'audit_events' => $audits->fetchAll(PDO::FETCH_ASSOC),
    ];
}
