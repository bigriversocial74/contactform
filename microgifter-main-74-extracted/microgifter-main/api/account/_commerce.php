<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_account_scope(string $scope, array $allowed, string $default): string
{
    return in_array($scope, $allowed, true) ? $scope : $default;
}

function mg_account_limit(mixed $value, int $default = 50, int $max = 100): int
{
    $limit = (int) $value;
    return $limit > 0 ? min($limit, $max) : $default;
}

function mg_account_item_filter(string $scope, int $userId, array &$params): string
{
    if ($scope === 'purchased') {
        $params = [$userId];
        return "i.owner_user_id=? AND i.funding_type='customer_purchase'";
    }
    if ($scope === 'sent') {
        $params = [$userId, $userId];
        return 'i.issuer_user_id=? AND (i.recipient_user_id IS NOT NULL OR i.recipient_external_id IS NOT NULL) AND (i.recipient_user_id IS NULL OR i.recipient_user_id<>?)';
    }
    if ($scope === 'received') {
        $params = [$userId, $userId, $userId];
        return '(i.recipient_user_id=? OR (i.owner_user_id=? AND (i.issuer_user_id IS NULL OR i.issuer_user_id<>?)))';
    }
    if ($scope === 'redeemed') {
        $params = [$userId, $userId];
        return "i.status='redeemed' AND (i.owner_user_id=? OR i.recipient_user_id=?)";
    }
    $params = [$userId];
    return 'i.owner_user_id=?';
}

function mg_account_items(PDO $pdo, int $userId, string $scope, int $limit): array
{
    $params = [];
    $where = mg_account_item_filter($scope, $userId, $params);
    $sql = "SELECT DISTINCT i.public_id item_id,i.item_type,i.funding_type,i.title_snapshot,i.description_snapshot,i.value_cents_snapshot,i.currency_snapshot,i.status,i.issued_at,i.assigned_at,i.sent_at,i.delivered_at,i.viewed_at,i.claimed_at,i.redeemed_at,i.expires_at,i.updated_at,i.recipient_external_id,
                   COALESCE(mu.display_name,mu.full_name) merchant_name,
                   COALESCE(iu.display_name,iu.full_name) issuer_name,
                   COALESCE(ru.display_name,ru.full_name) recipient_name,
                   r.public_id issuance_request_id,r.status issuance_status,
                   CASE WHEN o.buyer_user_id={$userId} THEN o.public_id ELSE NULL END order_id,
                   (SELECT COUNT(*) FROM entitlements e WHERE e.pppm_item_id=i.id AND e.entitled_user_id={$userId}) entitlement_count,
                   (SELECT COUNT(*) FROM entitlements e WHERE e.pppm_item_id=i.id AND e.entitled_user_id={$userId} AND e.status='active') active_entitlement_count,
                   (SELECT MAX(e.updated_at) FROM entitlements e WHERE e.pppm_item_id=i.id AND e.entitled_user_id={$userId}) entitlement_updated_at
            FROM pppm_items i
            INNER JOIN pppm_issuance_requests r ON r.id=i.issuance_request_id
            LEFT JOIN users mu ON mu.id=i.merchant_user_id
            LEFT JOIN users iu ON iu.id=i.issuer_user_id
            LEFT JOIN users ru ON ru.id=i.recipient_user_id
            LEFT JOIN commerce_order_items oi ON oi.pppm_issuance_request_id=r.id
            LEFT JOIN commerce_orders o ON o.id=oi.order_id
            WHERE {$where}
            ORDER BY i.updated_at DESC,i.public_id DESC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function mg_account_gifts(PDO $pdo, int $userId, string $scope, int $limit): array
{
    $field = $scope === 'sent' ? 'g.sender_user_id' : 'g.recipient_user_id';
    $sql = "SELECT g.public_id gift_id,g.slug,g.title,g.description,g.gift_type,g.value_cents,g.currency,g.status,g.visibility,g.recipient_name,g.sent_at,g.delivered_at,g.claimed_at,g.expires_at,g.updated_at,
                   COALESCE(s.display_name,s.full_name) sender_name,
                   COALESCE(r.display_name,r.full_name) recipient_display_name,
                   c.public_id claim_id,c.status claim_status,c.verified_at,c.redeemed_at,
                   i.public_id pppm_item_id,i.status pppm_status
            FROM gifts g
            INNER JOIN users s ON s.id=g.sender_user_id
            LEFT JOIN users r ON r.id=g.recipient_user_id
            LEFT JOIN gift_claims c ON c.gift_id=g.id
            LEFT JOIN pppm_legacy_gift_map m ON m.gift_id=g.id
            LEFT JOIN pppm_items i ON i.id=m.pppm_item_id
            WHERE {$field}=?
            ORDER BY g.updated_at DESC,g.id DESC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function mg_account_claims(PDO $pdo, int $userId, string $status, int $limit): array
{
    $params = [$userId, $userId];
    $where = '(c.claimant_user_id=? OR g.recipient_user_id=?)';
    if ($status !== 'all') {
        $where .= ' AND c.status=?';
        $params[] = $status;
    }
    $sql = "SELECT c.public_id claim_id,c.status,c.code_last4,c.failed_attempts,c.locked_at,c.verified_at,c.redeemed_at,c.expires_at,c.created_at,c.updated_at,
                   g.public_id gift_id,g.title,g.description,g.gift_type,g.value_cents,g.currency,g.status gift_status,
                   COALESCE(s.display_name,s.full_name) sender_name,
                   i.public_id pppm_item_id,i.status pppm_status
            FROM gift_claims c
            INNER JOIN gifts g ON g.id=c.gift_id
            LEFT JOIN users s ON s.id=g.sender_user_id
            LEFT JOIN pppm_legacy_gift_map m ON m.gift_id=g.id
            LEFT JOIN pppm_items i ON i.id=m.pppm_item_id
            WHERE {$where}
            ORDER BY c.updated_at DESC,c.id DESC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
