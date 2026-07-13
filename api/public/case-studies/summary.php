<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_require_method('GET');
$pdo = mg_db();

function mg_case_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return max(0, (int)$stmt->fetchColumn());
    } catch (Throwable) {
        return 0;
    }
}

function mg_case_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

$businesses = mg_case_scalar($pdo, "SELECT COUNT(*) FROM merchant_workspaces WHERE status NOT IN ('suspended','archived')");
$products = mg_case_scalar($pdo, "SELECT COUNT(*) FROM catalog_products WHERE status='published'");
$campaigns = mg_case_scalar($pdo, "SELECT COUNT(*) FROM campaigns WHERE status<>'archived'");
$activeCampaigns = mg_case_scalar($pdo, "SELECT COUNT(*) FROM campaigns WHERE status='active'");
$salesCents = mg_case_scalar($pdo, "SELECT COALESCE(SUM(amount_cents),0) FROM redemption_settlement_ledger WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)");
$previousSalesCents = mg_case_scalar($pdo, "SELECT COALESCE(SUM(amount_cents),0) FROM redemption_settlement_ledger WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 59 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 29 DAY)");
$redemptions = mg_case_scalar($pdo, "SELECT COUNT(*) FROM redemption_settlement_ledger WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)");

$salesMap = [];
foreach (mg_case_rows($pdo, "SELECT DATE(created_at) day, COALESCE(SUM(amount_cents),0) amount_cents FROM redemption_settlement_ledger WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY day") as $row) {
    $salesMap[(string)$row['day']] = (int)$row['amount_cents'];
}
$redemptionMap = [];
foreach (mg_case_rows($pdo, "SELECT DATE(created_at) day, COUNT(*) total FROM redemption_settlement_ledger WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY day") as $row) {
    $redemptionMap[(string)$row['day']] = (int)$row['total'];
}
$series = [];
for ($offset = 29; $offset >= 0; $offset--) {
    $date = (new DateTimeImmutable('today'))->modify('-' . $offset . ' days');
    $key = $date->format('Y-m-d');
    $series[] = [
        'date' => $key,
        'label' => $date->format('M j'),
        'sales_cents' => (int)($salesMap[$key] ?? 0),
        'redemptions' => (int)($redemptionMap[$key] ?? 0),
    ];
}

$statusRows = mg_case_rows($pdo, "SELECT status, COUNT(*) total FROM campaigns WHERE status<>'archived' GROUP BY status");
$statusMap = ['active' => 0, 'completed' => 0, 'scheduled' => 0, 'draft' => 0];
foreach ($statusRows as $row) {
    $status = strtolower((string)($row['status'] ?? ''));
    if (array_key_exists($status, $statusMap)) $statusMap[$status] = (int)$row['total'];
}
$change = $previousSalesCents > 0 ? (($salesCents - $previousSalesCents) / $previousSalesCents) * 100 : null;

mg_ok([
    'totals' => [
        'businesses' => $businesses,
        'products' => $products,
        'campaigns' => $campaigns,
        'active_campaigns' => $activeCampaigns,
        'sales_cents_30d' => $salesCents,
        'previous_sales_cents_30d' => $previousSalesCents,
        'sales_change_percent' => $change,
        'redemptions_30d' => $redemptions,
    ],
    'campaign_status' => $statusMap,
    'daily' => $series,
    'generated_at' => gmdate(DATE_ATOM),
]);