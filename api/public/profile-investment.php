<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_public_profile.php';

mg_require_method('GET');

$pdo = mg_db();
$slug = mg_public_profile_slug((string)($_GET['slug'] ?? ''));
$currentUser = mg_current_user();
$viewerId = (int)($currentUser['id'] ?? 0);
$viewerId = $viewerId > 0 ? $viewerId : null;

try {
    $profile = mg_public_profile_read($pdo, $slug, [
        'viewer_id' => $viewerId,
        'preview' => !empty($_GET['preview']),
        'product_limit' => 6,
        'post_limit' => 6,
        'plan_limit' => 6,
    ]);
} catch (Throwable) {
    mg_fail('Profile not found.', 404);
}

$ownerId = 0;
try {
    $ownerStmt = $pdo->prepare('SELECT user_id FROM public_profiles WHERE slug=? LIMIT 1');
    $ownerStmt->execute([$slug]);
    $ownerId = (int)($ownerStmt->fetchColumn() ?: 0);
} catch (Throwable) {
    $ownerId = 0;
}

$displayName = (string)($profile['profile']['display_name'] ?? 'Microgifter Merchant');
$tagline = (string)($profile['profile']['headline'] ?? $profile['profile']['biography'] ?? 'Tokenize local experiences and create future demand.');

function mg_invest_schema_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $allowed = ['wallet_items', 'campaigns', 'campaign_contacts', 'campaign_events', 'reward_templates'];
    if (!in_array($table, $allowed, true)) return false;
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function mg_invest_money(int $cents): string
{
    return '$' . number_format($cents / 100, 0);
}

$activeDrops = (int)($profile['social_counts']['published_products'] ?? 0);
$volume30dCents = 0;
$redeemed = 0;
$issued = 0;

if ($ownerId > 0 && mg_invest_schema_table_exists($pdo, 'campaigns')) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE user_id=? AND COALESCE(status, "") NOT IN ("archived", "deleted")');
        $stmt->execute([$ownerId]);
        $activeDrops = max($activeDrops, (int)$stmt->fetchColumn());
    } catch (Throwable) {}
}

if ($ownerId > 0 && mg_invest_schema_table_exists($pdo, 'wallet_items')) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) issued, SUM(CASE WHEN status IN ("redeemed", "claimed") THEN 1 ELSE 0 END) redeemed, COALESCE(SUM(value_amount_cents),0) value_cents FROM wallet_items WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $issued = (int)($row['issued'] ?? 0);
        $redeemed = (int)($row['redeemed'] ?? 0);
        $volume30dCents = (int)($row['value_cents'] ?? 0);
    } catch (Throwable) {}
}

$redemptionRate = $issued > 0 ? (int)round(($redeemed / $issued) * 100) : 0;
$demandScore = min(100, max(0, (int)round(($activeDrops * 8) + ($issued * 2) + ($redemptionRate * .5))));
$marketValueCents = $volume30dCents + ($activeDrops * 2500);
$floorPriceCents = $activeDrops > 0 ? (int)round($marketValueCents / max(1, $activeDrops)) : 0;
$profileUrl = '/profile.php?slug=' . rawurlencode($slug);

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok([
    'profile' => ['display_name' => $displayName, 'tagline' => $tagline, 'slug' => $slug],
    'metrics' => [
        'demand_value' => mg_invest_money($marketValueCents),
        'floor_price' => mg_invest_money($floorPriceCents),
        'volume_30d' => mg_invest_money($volume30dCents),
        'redemption_rate' => $redemptionRate . '%',
        'demand_score' => (string)$demandScore,
    ],
    'ticker' => [
        ['symbol' => 'MGFTR', 'price' => mg_invest_money($marketValueCents), 'change' => 'Demand', 'direction' => 'up', 'url' => $profileUrl],
        ['symbol' => 'DROPS', 'price' => (string)$activeDrops, 'change' => 'Active', 'direction' => 'flat', 'url' => $profileUrl],
        ['symbol' => 'VOL30', 'price' => mg_invest_money($volume30dCents), 'change' => '30D', 'direction' => 'flat', 'url' => $profileUrl],
    ],
    'source' => $profile,
]);
