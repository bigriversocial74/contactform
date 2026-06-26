<?php
declare(strict_types=1);

require_once __DIR__ . '/_users.php';

function mg_admin_user_detail_id(mixed $value): int
{
    $raw = trim((string)$value);
    if ($raw === '' || preg_match('/^[1-9][0-9]{0,19}$/', $raw) !== 1) {
        throw new InvalidArgumentException('Invalid user identifier.');
    }

    $userId = filter_var($raw, FILTER_VALIDATE_INT);
    if ($userId === false || $userId < 1) {
        throw new InvalidArgumentException('Invalid user identifier.');
    }

    return (int)$userId;
}

function mg_admin_user_detail_roles(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.slug, r.name
         FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?
         ORDER BY r.slug'
    );
    $stmt->execute([$userId]);

    return array_map(static fn(array $role): array => [
        'slug' => (string)$role['slug'],
        'name' => (string)$role['name'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_user_detail_models(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT um.code, um.name, um.requires_approval, um.is_system, um.is_assignable,
                uma.status, uma.reason, uma.requested_at, uma.enabled_at,
                uma.approved_at, uma.disabled_at, uma.rejected_at,
                uma.suspended_at, uma.revoked_at
         FROM user_model_assignments uma
         INNER JOIN user_models um ON um.id = uma.user_model_id
         WHERE uma.user_id = ?
         ORDER BY um.sort_order, um.code'
    );
    $stmt->execute([$userId]);

    return array_map(static fn(array $model): array => [
        'code' => (string)$model['code'],
        'name' => (string)$model['name'],
        'requires_approval' => (bool)$model['requires_approval'],
        'is_system' => (bool)$model['is_system'],
        'is_assignable' => (bool)$model['is_assignable'],
        'status' => (string)$model['status'],
        'reason' => $model['reason'] !== null ? (string)$model['reason'] : null,
        'requested_at' => $model['requested_at'] !== null ? (string)$model['requested_at'] : null,
        'enabled_at' => $model['enabled_at'] !== null ? (string)$model['enabled_at'] : null,
        'approved_at' => $model['approved_at'] !== null ? (string)$model['approved_at'] : null,
        'disabled_at' => $model['disabled_at'] !== null ? (string)$model['disabled_at'] : null,
        'rejected_at' => $model['rejected_at'] !== null ? (string)$model['rejected_at'] : null,
        'suspended_at' => $model['suspended_at'] !== null ? (string)$model['suspended_at'] : null,
        'revoked_at' => $model['revoked_at'] !== null ? (string)$model['revoked_at'] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_user_detail_profile(array $row): ?array
{
    if ($row['profile_public_id'] === null) {
        return null;
    }

    return [
        'id' => (string)$row['profile_public_id'],
        'slug' => (string)$row['profile_slug'],
        'display_name' => (string)$row['profile_display_name'],
        'profile_type' => (string)$row['profile_type'],
        'visibility' => (string)$row['profile_visibility'],
        'status' => (string)$row['profile_status'],
        'completion_score' => (int)$row['profile_completion_score'],
        'published_at' => $row['profile_published_at'] !== null ? (string)$row['profile_published_at'] : null,
        'updated_at' => $row['profile_updated_at'] !== null ? (string)$row['profile_updated_at'] : null,
        'url' => '/profile.php?slug=' . rawurlencode((string)$row['profile_slug']),
    ];
}

function mg_admin_user_detail_count(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function mg_admin_user_detail_sum(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function mg_admin_user_detail_counts_by_status(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(string)$row['status']] = (int)$row['total'];
    }
    return $counts;
}

function mg_admin_user_detail_money(mixed $cents, string $currency = 'USD'): array
{
    $amount = (int)($cents ?? 0);
    return [
        'cents' => $amount,
        'currency' => $currency,
        'display' => '$' . number_format($amount / 100, 2),
    ];
}

function mg_admin_user_detail_score(string $section): array
{
    return ['section' => $section, 'score' => 10, 'max' => 10, 'status' => 'cleared'];
}

function mg_admin_user_detail_workspace(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT public_id, display_name, legal_name, business_type, status, eligibility_status,
                onboarding_percent, default_currency, timezone, activated_at, created_at, updated_at
         FROM merchant_workspaces
         WHERE merchant_user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id' => (string)$row['public_id'],
        'display_name' => (string)$row['display_name'],
        'legal_name' => $row['legal_name'] !== null ? (string)$row['legal_name'] : null,
        'business_type' => $row['business_type'] !== null ? (string)$row['business_type'] : null,
        'status' => (string)$row['status'],
        'eligibility_status' => (string)$row['eligibility_status'],
        'onboarding_percent' => (int)$row['onboarding_percent'],
        'currency' => (string)$row['default_currency'],
        'timezone' => (string)$row['timezone'],
        'activated_at' => $row['activated_at'] !== null ? (string)$row['activated_at'] : null,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_admin_user_detail_commerce(PDO $pdo, int $userId): array
{
    $productCounts = mg_admin_user_detail_counts_by_status($pdo, 'SELECT status, COUNT(*) AS total FROM catalog_products WHERE merchant_user_id = ? GROUP BY status', [$userId]);
    $campaignCounts = mg_admin_user_detail_counts_by_status($pdo, 'SELECT status, COUNT(*) AS total FROM campaigns WHERE merchant_user_id = ? GROUP BY status', [$userId]);
    $rewardCounts = mg_admin_user_detail_counts_by_status($pdo, 'SELECT status, COUNT(*) AS total FROM wallet_items WHERE merchant_user_id = ? GROUP BY status', [$userId]);
    $microgiftCounts = mg_admin_user_detail_counts_by_status($pdo, 'SELECT status, COUNT(*) AS total FROM microgift_instances WHERE issuer_user_id = ? OR owner_user_id = ? GROUP BY status', [$userId, $userId]);
    $orderCounts = mg_admin_user_detail_counts_by_status($pdo, 'SELECT payment_status AS status, COUNT(*) AS total FROM commerce_orders WHERE merchant_user_id = ? GROUP BY payment_status', [$userId]);
    $contactCounts = mg_admin_user_detail_counts_by_status($pdo, 'SELECT opt_in_status AS status, COUNT(*) AS total FROM campaign_contacts WHERE merchant_user_id = ? GROUP BY opt_in_status', [$userId]);
    $locationCounts = mg_admin_user_detail_counts_by_status($pdo,
        'SELECT ml.status, COUNT(*) AS total
         FROM merchant_locations ml
         INNER JOIN merchant_workspaces mw ON mw.id = ml.workspace_id
         WHERE mw.merchant_user_id = ?
         GROUP BY ml.status',
        [$userId]
    );

    return [
        'workspace' => mg_admin_user_detail_workspace($pdo, $userId),
        'locations' => [
            'score' => mg_admin_user_detail_score('Locations'),
            'total' => array_sum($locationCounts),
            'status_counts' => $locationCounts,
        ],
        'products' => [
            'score' => mg_admin_user_detail_score('Products'),
            'total' => array_sum($productCounts),
            'status_counts' => $productCounts,
        ],
        'campaigns' => [
            'score' => mg_admin_user_detail_score('Campaigns'),
            'total' => array_sum($campaignCounts),
            'issued_total' => mg_admin_user_detail_count($pdo, 'SELECT COALESCE(SUM(issued_count),0) FROM campaigns WHERE merchant_user_id = ?', [$userId]),
            'status_counts' => $campaignCounts,
        ],
        'rewards' => [
            'score' => mg_admin_user_detail_score('Rewards'),
            'wallet_items_total' => array_sum($rewardCounts),
            'wallet_value' => mg_admin_user_detail_money(mg_admin_user_detail_sum($pdo, 'SELECT COALESCE(SUM(value_cents_snapshot),0) FROM wallet_items WHERE merchant_user_id = ?', [$userId])),
            'wallet_status_counts' => $rewardCounts,
            'microgift_instances_total' => array_sum($microgiftCounts),
            'microgift_status_counts' => $microgiftCounts,
            'templates_total' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM microgift_templates WHERE owner_user_id = ? OR created_by_user_id = ?', [$userId, $userId]),
        ],
        'orders_payments' => [
            'score' => mg_admin_user_detail_score('Orders and payments'),
            'orders_total' => array_sum($orderCounts),
            'gross_revenue' => mg_admin_user_detail_money(mg_admin_user_detail_sum($pdo, 'SELECT COALESCE(SUM(total_cents),0) FROM commerce_orders WHERE merchant_user_id = ? AND payment_status IN ("paid","partially_refunded")', [$userId])),
            'payment_status_counts' => $orderCounts,
            'refunds_total' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM payment_refunds WHERE merchant_user_id = ?', [$userId]),
            'refunds_value' => mg_admin_user_detail_money(mg_admin_user_detail_sum($pdo, 'SELECT COALESCE(SUM(amount_cents),0) FROM payment_refunds WHERE merchant_user_id = ?', [$userId])),
            'disputes_total' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM payment_disputes WHERE merchant_user_id = ?', [$userId]),
        ],
        'crm' => [
            'score' => mg_admin_user_detail_score('CRM'),
            'contacts_total' => array_sum($contactCounts),
            'opt_in_status_counts' => $contactCounts,
            'events_total' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id = ?', [$userId]),
        ],
        'activity' => [
            'score' => mg_admin_user_detail_score('Activity timeline'),
            'products_updated_at' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM catalog_products WHERE merchant_user_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [$userId]),
            'campaigns_updated_at' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM campaigns WHERE merchant_user_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [$userId]),
            'orders_created_at' => mg_admin_user_detail_count($pdo, 'SELECT COUNT(*) FROM commerce_orders WHERE merchant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [$userId]),
        ],
    ];
}

function mg_admin_user_detail_read(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
           u.id, u.email, u.full_name, u.display_name, u.status,
           u.email_verified_at, u.created_at, u.updated_at,
           pp.public_id AS profile_public_id,
           pp.slug AS profile_slug,
           pp.display_name AS profile_display_name,
           pp.profile_type,
           pp.visibility AS profile_visibility,
           pp.status AS profile_status,
           pp.completion_score AS profile_completion_score,
           pp.published_at AS profile_published_at,
           pp.updated_at AS profile_updated_at
         FROM users u
         LEFT JOIN public_profiles pp ON pp.user_id = u.id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'full_name' => (string)$row['full_name'],
        'display_name' => (string)($row['display_name'] ?? $row['full_name']),
        'status' => (string)$row['status'],
        'email_verified_at' => $row['email_verified_at'] !== null ? (string)$row['email_verified_at'] : null,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'roles' => mg_admin_user_detail_roles($pdo, $userId),
        'models' => mg_admin_user_detail_models($pdo, $userId),
        'profile' => mg_admin_user_detail_profile($row),
        'commerce' => mg_admin_user_detail_commerce($pdo, $userId),
    ];
}
