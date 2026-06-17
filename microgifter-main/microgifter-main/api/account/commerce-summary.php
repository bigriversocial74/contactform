<?php
declare(strict_types=1);
require_once __DIR__ . '/_commerce.php';
mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int) $user['id'];

$orderStmt = $pdo->prepare("SELECT COUNT(*) total_orders,SUM(payment_status='paid') paid_orders,COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_cents ELSE 0 END),0) paid_total_cents,MAX(currency) currency FROM commerce_orders WHERE buyer_user_id=?");
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetch() ?: [];

$itemStmt = $pdo->prepare("SELECT COUNT(*) owned_items,SUM(funding_type='customer_purchase') purchased_items,SUM(issuer_user_id=? AND (recipient_user_id IS NOT NULL OR recipient_external_id IS NOT NULL) AND (recipient_user_id IS NULL OR recipient_user_id<>?)) sent_items,SUM(recipient_user_id=? OR (owner_user_id=? AND (issuer_user_id IS NULL OR issuer_user_id<>?))) received_items,SUM(status='redeemed' AND (owner_user_id=? OR recipient_user_id=?)) redeemed_items FROM pppm_items WHERE owner_user_id=? OR issuer_user_id=? OR recipient_user_id=?");
$itemStmt->execute([$userId,$userId,$userId,$userId,$userId,$userId,$userId,$userId,$userId,$userId]);
$items = $itemStmt->fetch() ?: [];

$claimStmt = $pdo->prepare("SELECT COUNT(*) total_claims,SUM(c.status IN ('pending','verified')) active_claims,SUM(c.status='redeemed') redeemed_claims,SUM(c.status IN ('locked','expired','cancelled')) exception_claims FROM gift_claims c INNER JOIN gifts g ON g.id=c.gift_id WHERE c.claimant_user_id=? OR g.recipient_user_id=?");
$claimStmt->execute([$userId,$userId]);
$claims = $claimStmt->fetch() ?: [];

mg_ok([
    'summary' => array_merge($orders,$items,$claims),
    'recent' => [
        'items' => mg_account_items($pdo,$userId,'owned',6),
        'gifts' => mg_account_gifts($pdo,$userId,'received',6),
        'claims' => mg_account_claims($pdo,$userId,'all',6),
    ],
]);
