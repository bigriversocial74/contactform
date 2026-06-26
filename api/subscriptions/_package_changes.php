<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pricing-packages.php';

const MG_SUBSCRIPTION_PACKAGE_CHANGE_PENDING_STATUSES = ['pending_payment','pending_admin_review','approved'];

function mg_subscription_package_change_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_package_change_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        public_id CHAR(36) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        current_package_id VARCHAR(80) NULL,
        requested_package_id VARCHAR(80) NOT NULL,
        request_type ENUM('upgrade','downgrade','enterprise','lateral') NOT NULL DEFAULT 'upgrade',
        status ENUM('pending_payment','pending_admin_review','approved','rejected','canceled','completed') NOT NULL DEFAULT 'pending_admin_review',
        checkout_url VARCHAR(600) NULL,
        amount_cents BIGINT UNSIGNED NULL,
        currency CHAR(3) NOT NULL DEFAULT 'USD',
        billing_cycle VARCHAR(40) NOT NULL DEFAULT 'month',
        user_note TEXT NULL,
        admin_note TEXT NULL,
        metadata_json JSON NULL,
        reviewed_by_user_id BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_subscription_package_change_public_id (public_id),
        KEY idx_subscription_package_change_user_status (user_id,status,updated_at,id),
        KEY idx_subscription_package_change_status_created (status,created_at,id),
        KEY idx_subscription_package_change_requested (requested_package_id,status,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function mg_subscription_package_change_slug(mixed $value): string
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function mg_subscription_package_change_decode_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_subscription_package_change_nested_value(array $source, array $keys): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') return $source[$key];
    }
    foreach (['subscription','billing','package','plan','metadata'] as $group) {
        if (!empty($source[$group]) && is_array($source[$group])) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source[$group]) && $source[$group][$key] !== null && $source[$group][$key] !== '') return $source[$group][$key];
            }
        }
    }
    return null;
}

function mg_subscription_package_change_plans(): array
{
    $plans = mg_public_pricing_packages();
    usort($plans, static fn(array $a, array $b): int => (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
    return $plans;
}

function mg_subscription_package_change_plan(array $plans, string $planId): ?array
{
    $planId = mg_subscription_package_change_slug($planId);
    foreach ($plans as $plan) {
        $id = mg_subscription_package_change_slug((string)($plan['id'] ?? ''));
        $name = mg_subscription_package_change_slug((string)($plan['name'] ?? ''));
        if ($planId !== '' && ($planId === $id || $planId === $name)) return $plan;
    }
    return null;
}

function mg_subscription_package_change_plan_id(array $plans, mixed $value, string $fallback = 'starter'): string
{
    $plan = mg_subscription_package_change_plan($plans, (string)$value);
    if ($plan) return mg_subscription_package_change_slug((string)($plan['id'] ?? $plan['name'] ?? $fallback));
    return $fallback;
}

function mg_subscription_package_change_price_cents(array $plan): int
{
    $label = (string)($plan['price_label'] ?? '0');
    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $label, $match) !== 1) return 0;
    return (int)round(((float)$match[1]) * 100);
}

function mg_subscription_package_change_sort_order(array $plans, string $planId): int
{
    $plan = mg_subscription_package_change_plan($plans, $planId);
    return $plan ? (int)($plan['sort_order'] ?? 0) : 0;
}

function mg_subscription_package_change_current_package(PDO $pdo, int $userId, array $plans, ?array $sessionUser = null): string
{
    $fallback = 'starter';
    if ($sessionUser) {
        $sessionPlan = mg_subscription_package_change_nested_value($sessionUser, ['package_id','package_slug','pricing_package_id','pricing_plan_id','plan_id','plan_slug','current_plan','current_package','subscription_plan']);
        if ($sessionPlan !== null) $fallback = mg_subscription_package_change_plan_id($plans, $sessionPlan, $fallback);
    }

    try {
        $stmt = $pdo->prepare("SELECT s.metadata_json,p.metadata_json plan_metadata,p.name plan_name
          FROM subscriptions s
          LEFT JOIN subscription_plans p ON p.id=s.plan_id
          WHERE s.subscriber_user_id=?
          ORDER BY FIELD(s.status,'active','trialing','cancel_pending','past_due','pending_payment','paused','canceled','expired'),s.updated_at DESC,s.id DESC
          LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $fallback;
        $subscriptionMeta = mg_subscription_package_change_decode_json($row['metadata_json'] ?? null);
        $planMeta = mg_subscription_package_change_decode_json($row['plan_metadata'] ?? null);
        $value = mg_subscription_package_change_nested_value($subscriptionMeta, ['package_id','package_slug','pricing_package_id','pricing_plan_id','platform_package_id','plan_slug']);
        if ($value === null) $value = mg_subscription_package_change_nested_value($planMeta, ['package_id','package_slug','pricing_package_id','pricing_plan_id','platform_package_id','plan_slug']);
        if ($value === null && !empty($row['plan_name'])) $value = $row['plan_name'];
        return $value !== null ? mg_subscription_package_change_plan_id($plans, $value, $fallback) : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function mg_subscription_package_change_checkout_url(string $requestId, string $planId): ?string
{
    $template = (string)(getenv('MG_SUBSCRIPTION_CHECKOUT_URL') ?: '');
    if ($template === '') return null;
    $replacements = [
        '{request_id}' => rawurlencode($requestId),
        '{plan}' => rawurlencode($planId),
        '{source}' => 'account_subscription',
    ];
    if (str_contains($template, '{request_id}') || str_contains($template, '{plan}')) {
        return strtr($template, $replacements);
    }
    $separator = str_contains($template, '?') ? '&' : '?';
    return $template . $separator . 'plan=' . rawurlencode($planId) . '&request_id=' . rawurlencode($requestId) . '&source=account_subscription';
}

function mg_subscription_package_change_public(array $row): array
{
    $status = (string)($row['status'] ?? 'pending_admin_review');
    $label = match ($status) {
        'pending_payment' => 'Payment required',
        'pending_admin_review' => 'Pending admin review',
        'approved' => 'Approved',
        'completed' => 'Active',
        'rejected' => 'Rejected',
        'canceled' => 'Canceled',
        default => ucwords(str_replace('_',' ',$status)),
    };
    return [
        'request_id' => (string)($row['public_id'] ?? ''),
        'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
        'current_package_id' => (string)($row['current_package_id'] ?? ''),
        'requested_package_id' => (string)($row['requested_package_id'] ?? ''),
        'request_type' => (string)($row['request_type'] ?? 'upgrade'),
        'status' => $status,
        'status_label' => $label,
        'checkout_url' => $row['checkout_url'] !== null ? (string)$row['checkout_url'] : null,
        'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : null,
        'currency' => (string)($row['currency'] ?? 'USD'),
        'billing_cycle' => (string)($row['billing_cycle'] ?? 'month'),
        'admin_note' => $row['admin_note'] !== null ? (string)$row['admin_note'] : null,
        'user_note' => $row['user_note'] !== null ? (string)$row['user_note'] : null,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'reviewed_at' => $row['reviewed_at'] !== null ? (string)$row['reviewed_at'] : null,
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
    ];
}

function mg_subscription_package_change_latest(PDO $pdo, int $userId, bool $pendingOnly = true): ?array
{
    mg_subscription_package_change_schema($pdo);
    $statuses = $pendingOnly ? MG_SUBSCRIPTION_PACKAGE_CHANGE_PENDING_STATUSES : ['pending_payment','pending_admin_review','approved','completed','rejected','canceled'];
    $stmt = $pdo->prepare('SELECT * FROM subscription_package_change_requests WHERE user_id=? AND status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ') ORDER BY updated_at DESC,id DESC LIMIT 1');
    $stmt->execute(array_merge([$userId], $statuses));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_subscription_package_change_request(PDO $pdo, array $user, string $requestedPlanId, string $note = ''): array
{
    mg_subscription_package_change_schema($pdo);
    $plans = mg_subscription_package_change_plans();
    $requestedPlan = mg_subscription_package_change_plan($plans, $requestedPlanId);
    if (!$requestedPlan) throw new InvalidArgumentException('Selected package is not available.');

    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) throw new RuntimeException('User identity is unavailable.');

    $requestedPackageId = mg_subscription_package_change_slug((string)($requestedPlan['id'] ?? $requestedPlan['name']));
    $currentPackageId = mg_subscription_package_change_current_package($pdo, $userId, $plans, $user);
    if ($requestedPackageId === $currentPackageId) throw new InvalidArgumentException('That package is already active on your account.');

    $existing = mg_subscription_package_change_latest($pdo, $userId, true);
    if ($existing && (string)$existing['requested_package_id'] === $requestedPackageId) {
        return mg_subscription_package_change_public($existing) + ['duplicate' => true];
    }
    if ($existing) {
        $cancel = $pdo->prepare("UPDATE subscription_package_change_requests SET status='canceled',admin_note=COALESCE(admin_note,'Replaced by a newer package request.'),updated_at=NOW() WHERE id=? AND status IN ('pending_payment','pending_admin_review','approved')");
        $cancel->execute([(int)$existing['id']]);
    }

    $publicId = mg_public_uuid();
    $requestType = 'lateral';
    if ($requestedPackageId === 'enterprise') $requestType = 'enterprise';
    elseif (mg_subscription_package_change_sort_order($plans, $requestedPackageId) > mg_subscription_package_change_sort_order($plans, $currentPackageId)) $requestType = 'upgrade';
    elseif (mg_subscription_package_change_sort_order($plans, $requestedPackageId) < mg_subscription_package_change_sort_order($plans, $currentPackageId)) $requestType = 'downgrade';

    $amountCents = mg_subscription_package_change_price_cents($requestedPlan);
    $checkoutUrl = $requestType === 'enterprise' ? null : mg_subscription_package_change_checkout_url($publicId, $requestedPackageId);
    $status = $checkoutUrl ? 'pending_payment' : 'pending_admin_review';
    $metadata = [
        'source' => 'account_subscription',
        'requested_plan_name' => (string)($requestedPlan['name'] ?? $requestedPackageId),
        'pricing_package_id' => $requestedPackageId,
        'pricing_source' => 'includes/pricing-packages.php',
    ];

    $stmt = $pdo->prepare("INSERT INTO subscription_package_change_requests (public_id,user_id,current_package_id,requested_package_id,request_type,status,checkout_url,amount_cents,currency,billing_cycle,user_note,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([$publicId,$userId,$currentPackageId,$requestedPackageId,$requestType,$status,$checkoutUrl,$amountCents,'USD','month',mb_substr($note,0,2000),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);

    $rowStmt = $pdo->prepare('SELECT * FROM subscription_package_change_requests WHERE public_id=? LIMIT 1');
    $rowStmt->execute([$publicId]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    mg_audit('subscription.package_change_requested','subscription_package_change_request',[
        'request_id' => $publicId,
        'current_package_id' => $currentPackageId,
        'requested_package_id' => $requestedPackageId,
        'request_type' => $requestType,
        'status' => $status,
    ], $userId);
    mg_event('subscription.package_change_requested', ['request_id'=>$publicId,'requested_package_id'=>$requestedPackageId,'status'=>$status], $userId);

    return mg_subscription_package_change_public($row) + ['duplicate' => false];
}

function mg_subscription_package_change_apply_to_subscription(PDO $pdo, array $row): bool
{
    $userId = (int)$row['user_id'];
    $packageId = (string)$row['requested_package_id'];
    try {
        $stmt = $pdo->prepare("UPDATE subscriptions SET metadata_json=JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.package_id', ?, '$.pricing_package_id', ?, '$.package_change_request_id', ?), updated_at=NOW() WHERE subscriber_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1");
        $stmt->execute([$packageId,$packageId,(string)$row['public_id'],$userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function mg_subscription_package_change_review(PDO $pdo, string $requestId, string $action, array $adminUser, string $note = ''): array
{
    mg_subscription_package_change_schema($pdo);
    $requestId = trim($requestId);
    $action = strtolower(trim($action));
    if ($requestId === '' || !in_array($action, ['approve','reject','cancel'], true)) throw new InvalidArgumentException('Invalid review action.');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM subscription_package_change_requests WHERE public_id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Package change request not found.');
        if (!in_array((string)$row['status'], MG_SUBSCRIPTION_PACKAGE_CHANGE_PENDING_STATUSES, true)) throw new RuntimeException('Package change request is already closed.');

        $newStatus = match ($action) {
            'approve' => 'completed',
            'reject' => 'rejected',
            default => 'canceled',
        };
        $applied = false;
        if ($action === 'approve') $applied = mg_subscription_package_change_apply_to_subscription($pdo, $row);

        $update = $pdo->prepare("UPDATE subscription_package_change_requests SET status=?,admin_note=?,reviewed_by_user_id=?,reviewed_at=NOW(),completed_at=IF(? IN ('completed','rejected','canceled'),NOW(),completed_at),metadata_json=JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.applied_to_subscription', ?),updated_at=NOW() WHERE id=?");
        $update->execute([$newStatus,mb_substr($note,0,2000),(int)$adminUser['id'],$newStatus,$applied ? 1 : 0,(int)$row['id']]);

        $reload = $pdo->prepare('SELECT * FROM subscription_package_change_requests WHERE id=? LIMIT 1');
        $reload->execute([(int)$row['id']]);
        $updated = $reload->fetch(PDO::FETCH_ASSOC) ?: $row;
        $pdo->commit();

        mg_audit('subscription.package_change_' . $action, 'subscription_package_change_request', [
            'request_id' => $requestId,
            'status' => $newStatus,
            'applied_to_subscription' => $applied,
        ], (int)$adminUser['id']);
        mg_event('subscription.package_change_' . $action, ['request_id'=>$requestId,'status'=>$newStatus,'applied_to_subscription'=>$applied], (int)$adminUser['id']);

        return mg_subscription_package_change_public($updated) + ['applied_to_subscription' => $applied];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_subscription_package_change_admin_list(PDO $pdo, string $status = 'pending', int $limit = 50): array
{
    mg_subscription_package_change_schema($pdo);
    $limit = max(1, min(100, $limit));
    $statuses = match ($status) {
        'closed' => ['completed','rejected','canceled'],
        'all' => ['pending_payment','pending_admin_review','approved','completed','rejected','canceled'],
        default => ['pending_payment','pending_admin_review','approved'],
    };
    $stmt = $pdo->prepare('SELECT r.*,u.email,u.display_name,u.full_name FROM subscription_package_change_requests r LEFT JOIN users u ON u.id=r.user_id WHERE r.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ') ORDER BY r.updated_at DESC,r.id DESC LIMIT ' . $limit);
    $stmt->execute($statuses);
    return array_map(static function (array $row): array {
        $public = mg_subscription_package_change_public($row);
        $public['user'] = [
            'email' => (string)($row['email'] ?? ''),
            'name' => (string)($row['display_name'] ?? $row['full_name'] ?? ''),
        ];
        return $public;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
