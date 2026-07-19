<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/_bundles.php';

mg_require_method('GET');
$pdo = mg_db();
mg_bundle_require_schema($pdo);
$mode = strtolower(trim((string)($_GET['mode'] ?? 'feed')));

function mg_bundle_projection_rows(PDO $pdo, ?int $merchantId = null, int $limit = 12): array
{
    $limit = max(1, min(30, $limit));
    $merchantWhere = '';
    $params = [];
    if ($merchantId !== null) {
        $merchantWhere = ' AND (b.owner_merchant_user_id=? OR EXISTS(
            SELECT 1 FROM gift_bundle_participants gp
            WHERE gp.bundle_id=b.id AND gp.merchant_user_id=? AND gp.invitation_status=\'accepted\'
        ))';
        $params = [$merchantId, $merchantId];
    }
    $sql = "SELECT b.public_id,b.title,b.short_statement,b.cover_asset_url,b.currency,b.primary_location,b.published_at,
                   b.owner_merchant_user_id,
                   COALESCE(ms.display_name,u.display_name,u.full_name,u.email) AS master_name,
                   ms.slug AS master_slug,
                   (SELECT COUNT(*) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status='accepted') AS product_count,
                   (SELECT COUNT(DISTINCT c.merchant_user_id) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status='accepted') AS merchant_count,
                   (SELECT COALESCE(SUM(c.customer_amount_cents*c.quantity),0) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status='accepted') AS total_cents
            FROM gift_bundles b
            INNER JOIN users u ON u.id=b.owner_merchant_user_id
            LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=b.owner_merchant_user_id AND ms.status<>'archived'
            WHERE b.status='published' AND b.visibility IN ('public','unlisted')
              AND (b.sales_start_at IS NULL OR b.sales_start_at<=NOW())
              AND (b.sales_end_at IS NULL OR b.sales_end_at>=NOW())
              {$merchantWhere}
            ORDER BY b.published_at DESC,b.id DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['url'] = '/bundle.php?id=' . rawurlencode((string)$row['public_id']);
        $row['master_profile_url'] = !empty($row['master_slug']) ? '/profile.php?slug=' . rawurlencode((string)$row['master_slug']) : null;
        $row['product_count'] = (int)$row['product_count'];
        $row['merchant_count'] = (int)$row['merchant_count'];
        $row['total_cents'] = (int)$row['total_cents'];
        $row['is_master'] = $merchantId !== null && (int)$row['owner_merchant_user_id'] === $merchantId;
        unset($row['owner_merchant_user_id']);
    }
    unset($row);
    return $rows;
}

try {
    if ($mode === 'feed') {
        mg_ok(['bundles' => mg_bundle_projection_rows($pdo, null, (int)($_GET['limit'] ?? 8))]);
    }
    if ($mode === 'profile') {
        $slug = strtolower(trim((string)($_GET['slug'] ?? '')));
        if ($slug === '') throw new InvalidArgumentException('Profile slug is required.');
        $stmt = $pdo->prepare("SELECT merchant_user_id FROM merchant_storefronts WHERE slug=? AND status<>'archived' LIMIT 1");
        $stmt->execute([$slug]);
        $merchantId = (int)($stmt->fetchColumn() ?: 0);
        if ($merchantId < 1) {
            $stmt = $pdo->prepare("SELECT user_id FROM public_profiles WHERE slug=? LIMIT 1");
            $stmt->execute([$slug]);
            $merchantId = (int)($stmt->fetchColumn() ?: 0);
        }
        if ($merchantId < 1) throw new RuntimeException('Merchant profile not found.');
        mg_ok(['bundles' => mg_bundle_projection_rows($pdo, $merchantId, (int)($_GET['limit'] ?? 12))]);
    }
    mg_fail('Unsupported projection mode.', 405);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (RuntimeException $e) {
    mg_fail($e->getMessage(), 404);
} catch (Throwable $e) {
    error_log('Bundle projection failed: ' . $e::class . ': ' . $e->getMessage());
    mg_fail('Unable to load published bundles.', 500);
}
